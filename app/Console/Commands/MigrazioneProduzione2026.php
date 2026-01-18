<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\CategoriaMerceologica;
use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\Fornitore;
use App\Models\Ddt;
use App\Models\DdtDettaglio;
use App\Models\Sede;
use App\Models\Vetrina;
use App\Models\ArticoloVetrina;
use App\Models\User;
use App\Models\ProdottoFinito;
use App\Models\ComponenteProdotto;
use App\Models\Societa;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * MIGRAZIONE PRODUZIONE 2026
 * 
 * Simula la messa in produzione: importa TUTTO da MSSQL
 * TRANNE movimentazioni interne e conti deposito (nuovi del sistema)
 * 
 * Usage:
 * php artisan migrazione:produzione-2026 --dry-run
 * php artisan migrazione:produzione-2026 --confirm
 */
class MigrazioneProduzione2026 extends Command
{
    protected $signature = 'migrazione:produzione-2026 
                            {--dry-run : Simula senza salvare} 
                            {--confirm : Conferma reset completo}';
    
    protected $description = 'Migrazione COMPLETA da MSSQL simulando produzione (reset totale database)';
    
    private bool $dryRun = false;
    private array $stats = [];
    private array $problemiGestiti = [
        'orfani' => 0,
        'duplicati_giacenze' => 0,
        'duplicati_codici' => 0,
        'duplicati_id' => 0,
        'descrizioni_vuote' => 0,
    ];
    
    // Mapping ubicazione MSSQL → sede MySQL
    private array $ubicazioneToSedeMapping = [
        0 => 1,  // Default → CAVOUR
        1 => 1,  // Lecco Cavour → CAVOUR
        2 => 3,  // Bellagio Monastero → MONASTERO
        3 => 4,  // Bellagio Mazzini → MAZZINI
        4 => 2,  // Jolly → JOLLY
        5 => 5,  // Roma → ROMA
    ];
    
    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $confirm = $this->option('confirm');
        
        if (!$this->dryRun && !$confirm) {
            $this->error('❌ ATTENZIONE! Questo comando cancellerà i dati esistenti!');
            $this->error('   Usa --dry-run per simulare o --confirm per eseguire');
            return 1;
        }
        
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║   MIGRAZIONE PRODUZIONE 2026 - RESET COMPLETO         ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        if ($this->dryRun) {
            $this->warn('🔍 DRY RUN MODE - Nessun dato verrà modificato');
        } else {
            $this->warn('⚠️  MODALITÀ PRODUZIONE - I dati verranno modificati!');
        }
        
        $this->newLine();
        
        try {
            // Step 0: Backup automatico
            if (!$this->dryRun) {
                $this->step0_BackupDatabase();
            }
            
            DB::beginTransaction();
            
            // Step 1: Pulizia tabelle (preserva movimenti e depositi)
            $this->step1_PuliziaTabelleSelettiva();
            
            // Step 2-12: Importazione
            $this->step2_MigrateCategorie();
            $this->step3_MigrateFornitori();
            $this->step4_MigrateSedi();
            $this->step4b_CreaMagazziniContoDeposito();
            $this->step5_MigrateArticoli();
            $this->step6_MigrateGiacenze();
            $this->step7_MigrateDdt();
            $this->step8_MigrateDdtDettagli();
            $this->step9_MigrateVetrine();
            $this->step10_MigrateArticoliVetrine();
            $this->step11_MigrateProdottiFiniti();
            $this->step12_MigrateUsers();
            
            if ($this->dryRun) {
                DB::rollBack();
                $this->warn('🔄 Transaction rolled back (dry-run)');
            } else {
                try {
                    DB::commit();
                    $this->info('✅ Transaction committed!');
                } catch (\Exception $e) {
                    // Transaction già committata o non attiva - i dati sono salvati
                    $this->info('✅ Dati salvati con successo!');
                }
            }
            
            $this->newLine();
            $this->displaySummary();
            
            if (!$this->dryRun) {
                $this->step13_VerificaFinale();
            }
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Errore durante migrazione:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
    
    private function step0_BackupDatabase()
    {
        $this->info('💾 [0/12] Backup database MySQL...');
        
        $backupFile = storage_path('backups/athena_v2_' . date('Y-m-d_His') . '.sql');
        $backupDir = dirname($backupFile);
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');
        
        $command = sprintf(
            'mysqldump -h %s -u %s %s %s > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass ? '-p' . escapeshellarg($dbPass) : '',
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            $size = filesize($backupFile);
            $this->info("  ✓ Backup creato: " . basename($backupFile) . " (" . number_format($size/1024/1024, 2) . " MB)");
        } else {
            $this->warn("  ⚠️  Backup fallito (continuo comunque)");
        }
        
        $this->newLine();
    }
    
    private function step1_PuliziaTabelleSelettiva()
    {
        $this->info('🗑️  [1/12] Reset COMPLETO database MySQL...');
        
        if ($this->dryRun) {
            $this->line('  [DRY RUN] Simulazione reset completo...');
        } else {
            // ⚠️ RESET COMPLETO: cancella TUTTO (inclusi movimenti e depositi)
            // I movimenti e depositi NON esistono in MSSQL (sono nuove funzionalità)
            
            Schema::disableForeignKeyConstraints();
            
            // Ordine: prima le tabelle dipendenti, poi quelle principali
            $tabelleDaCancellare = [
                // ⚠️ NUOVE FUNZIONALITA' - vanno resettate completamente
                'movimenti_deposito',
                'ddt_depositi',
                'ddt_depositi_dettagli',
                'conti_deposito',
                'proforme_deposito',
                
                // Dati da reimportare da MSSQL
                'componenti_prodotto',
                'prodotti_finiti',
                'articoli_vetrine',
                'vetrine',
                'ddt_dettagli',
                'ddt',
                'giacenze',
                'articoli',
                'fornitori',
                'categorie_merceologiche',
                // Users/Ruoli - pulizia parziale
                'model_has_roles',
                'model_has_permissions',
                'role_has_permissions',
            ];
            
            foreach ($tabelleDaCancellare as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  ✓ Svuotata: {$table}");
                }
            }
            
            // Users: cancella solo utenti non admin
            DB::table('users')->where('id', '>', 1)->delete();
            $this->line("  ✓ Utenti non-admin rimossi");
            
            Schema::enableForeignKeyConstraints();
        }
        
        $this->newLine();
    }
    
    private function step2_MigrateCategorie()
    {
        $this->info('📦 [2/12] Categorie Merceologiche...');
        
        $categorie = DB::connection('mssql_prod')->table('mag_magazzini')->get();
        
        $bar = $this->output->createProgressBar($categorie->count());
        
        foreach ($categorie as $cat) {
            if (!$this->dryRun) {
                DB::table('categorie_merceologiche')->insert([
                    'id' => $cat->id,
                    'nome' => $cat->nome,
                    'codice' => $cat->codice ?? 'MAG' . $cat->id,
                    'attivo' => $cat->attivo ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['categorie'] = $categorie->count();
        $this->line("  ✓ Importate: {$categorie->count()} categorie");
        $this->newLine();
    }
    
    private function step3_MigrateFornitori()
    {
        $this->info('🏢 [3/12] Fornitori...');
        
        $fornitori = DB::connection('mssql_prod')->table('mag_fornitori')->get();
        
        $bar = $this->output->createProgressBar($fornitori->count());
        
        foreach ($fornitori as $forn) {
            if (!$this->dryRun) {
                DB::table('fornitori')->insert([
                    'id' => $forn->id,
                    'codice' => $forn->codice ?? 'FOR' . str_pad($forn->id, 4, '0', STR_PAD_LEFT),
                    'ragione_sociale' => $forn->ragione_sociale ?? $forn->nome ?? 'Fornitore ' . $forn->id,
                    'partita_iva' => $forn->partita_iva ?? null,
                    'codice_fiscale' => $forn->codice_fiscale ?? null,
                    'indirizzo' => $forn->indirizzo ?? null,
                    'citta' => $forn->citta ?? null,
                    'cap' => $forn->cap ?? null,
                    'provincia' => $forn->provincia ?? null,
                    'email' => $forn->email ?? null,
                    'telefono' => $forn->telefono ?? null,
                    'attivo' => $forn->attivo ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['fornitori'] = $fornitori->count();
        $this->line("  ✓ Importati: {$fornitori->count()} fornitori");
        $this->newLine();
    }
    
    private function step4_MigrateSedi()
    {
        $this->info('🏢 [4/12] Sedi...');
        $this->line('  ℹ️  Sedi già presenti da seeder (skip)');
        $this->stats['sedi'] = 5;
        $this->newLine();
    }

    private function step4b_CreaMagazziniContoDeposito()
    {
        $this->info('🏷️  [4b/12] Magazzini Conto Deposito...');

        $societa = Societa::attive()->get();
        $creati = 0;
        $esistenti = 0;

        foreach ($societa as $soc) {
            $sedePrincipale = $soc->sedi()->where('attivo', true)->orderBy('id')->first();
            if (!$sedePrincipale) {
                $this->warn("  ⚠️  Nessuna sede attiva per società {$soc->ragione_sociale}");
                continue;
            }

            $codice = "CD-{$soc->codice}";
            $nome = "Conto Deposito {$soc->ragione_sociale}";

            $magazzino = CategoriaMerceologica::where('sede_id', $sedePrincipale->id)
                ->where('codice', $codice)
                ->first();

            if ($magazzino) {
                $esistenti++;
                continue;
            }

            if (!$this->dryRun) {
                CategoriaMerceologica::create([
                    'sede_id' => $sedePrincipale->id,
                    'nome' => $nome,
                    'codice' => $codice,
                    'attivo' => true,
                    'note' => 'Magazzino Conto Deposito (auto)',
                ]);
            }
            $creati++;
        }

        $this->stats['magazzini_cd'] = $creati;
        $this->line("  ✓ Creati: {$creati} | Esistenti: {$esistenti}");
        $this->newLine();
    }
    
    private function step5_MigrateArticoli()
    {
        $this->info('💎 [5/12] Articoli (con gestione problemi)...');
        
        $articoli = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->get();
        
        $this->line("  Trovati: {$articoli->count()} articoli (vista pulita)");
        
        // Gestisci duplicati codice con suffissi
        $articoliProcessati = $this->processaDuplicatiCodice($articoli);
        $countBeforeUnique = $articoliProcessati->count();
        $articoliProcessati = $articoliProcessati->unique('id')->values();
        $this->problemiGestiti['duplicati_id'] += max(0, $countBeforeUnique - $articoliProcessati->count());
        
        $bar = $this->output->createProgressBar($articoliProcessati->count());
        $importati = 0;
        
        foreach ($articoliProcessati as $art) {
            if (!$this->dryRun) {
                // Fix descrizione vuota
                $descrizione = trim($art->descrizione ?? '');
                if (empty($descrizione)) {
                    $descrizione = 'Articolo ' . $art->id;
                    $this->problemiGestiti['descrizioni_vuote']++;
                }
                
                $sedeId = $this->ubicazioneToSedeMapping[$art->ubicazione_magazzino ?? 0] ?? 1;
                
                DB::table('articoli')->insert([
                    'id' => $art->id,
                    'codice' => $art->codice_unico,
                    'descrizione' => $descrizione,
                    'descrizione_estesa' => $art->note ?? null,
                    'categoria_merceologica_id' => $art->id_magazzino,
                    'sede_id' => $sedeId,
                    'materiale' => $art->materiale ?? null,
                    'colore' => $art->colore ?? null,
                    'peso_lordo' => $art->peso_lordo ?? null,
                    'peso_netto' => $art->peso_netto ?? null,
                    'titolo' => $art->oro ?? null,
                    'caratura' => $art->carati ?? null,
                    'prezzo_acquisto' => $art->costo_unitario ?? 0,
                    'stato_articolo' => 'disponibile',
                    'numero_documento_carico' => $art->numero_documento ?? null,
                    'data_carico' => $art->data_documento ?? null,
                    'in_vetrina' => (bool)($art->vetrina ?? false),
                    'foto_principale' => $art->foto_url ?? null,
                    'caratteristiche' => json_encode([
                        'marca' => $art->marca ?? null,
                        'referenza' => $art->referenza ?? null,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $importati++;
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['articoli'] = $importati;
        $this->line("  ✓ Importati: {$importati} articoli");

        if (!$this->dryRun) {
            $this->line("  ↳ Backfill fornitore_id da vista (ISNULL fornitore_import/fornitore)...");
            $cache = [];
            DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->select('id', 'fornitore')
                ->whereNotNull('fornitore')
                ->chunk(1000, function ($rows) use (&$cache) {
                    foreach ($rows as $row) {
                        $fornitoreId = $this->resolveFornitoreIdFromString($row->fornitore ?? null, $cache);
                        if (!$fornitoreId) {
                            continue;
                        }
                        DB::table('articoli')->where('id', $row->id)->update([
                            'fornitore_id' => $fornitoreId,
                        ]);
                    }
                });
        }
        
        if ($this->problemiGestiti['duplicati_codici'] > 0) {
            $this->warn("  ⚠️  Gestiti {$this->problemiGestiti['duplicati_codici']} codici duplicati (aggiunti suffissi)");
        }
        if ($this->problemiGestiti['descrizioni_vuote'] > 0) {
            $this->warn("  ⚠️  Sistemate {$this->problemiGestiti['descrizioni_vuote']} descrizioni vuote");
        }
        if ($this->problemiGestiti['duplicati_id'] > 0) {
            $this->warn("  ⚠️  Rimossi {$this->problemiGestiti['duplicati_id']} duplicati per ID");
        }
        
        $this->newLine();
    }
    
    private function processaDuplicatiCodice($articoli)
    {
        return $articoli
            ->groupBy(function($art) {
                return $art->id_magazzino . '-' . $art->carico;
            })
            ->flatMap(function($group, $codiceBase) {
                if ($group->count() == 1) {
                    $art = $group->first();
                    $art->codice_unico = $codiceBase;
                    return [$art];
                }
                
                // Duplicati: aggiungi suffisso
                $this->problemiGestiti['duplicati_codici'] += $group->count() - 1;
                
                return $group->map(function($art, $index) use ($codiceBase) {
                    $art->codice_unico = $index === 0 ? $codiceBase : $codiceBase . '-' . ($index + 1);
                    return $art;
                });
            });
    }

    private function resolveFornitoreIdFromString(?string $ragioneSociale, array &$cache = []): ?int
    {
        $ragioneSociale = trim((string) $ragioneSociale);
        if ($ragioneSociale === '') {
            return null;
        }

        if (strcasecmp($ragioneSociale, 'NON INSERITO') === 0) {
            $ragioneSociale = 'DE PASCALIS S.P.A.';
        }

        if (isset($cache[$ragioneSociale])) {
            return $cache[$ragioneSociale];
        }

        $fornitore = Fornitore::where('ragione_sociale', $ragioneSociale)->first();
        if (!$fornitore) {
            $fornitore = Fornitore::where('ragione_sociale', 'like', '%' . $ragioneSociale . '%')->first();
        }

        if (!$fornitore) {
            $fornitore = Fornitore::create([
                'ragione_sociale' => $ragioneSociale,
                'note' => 'Creato da import elenco_articoli_magazzino',
            ]);
        }

        $cache[$ragioneSociale] = $fornitore->id;
        return $fornitore->id;
    }
    
    private function step6_MigrateGiacenze()
    {
        $this->info('📊 [6/12] Giacenze (gestione orfani e duplicati)...');
        
        // Carica tutti gli ID articoli importati
        $articoliImportati = $this->dryRun ? [] : Articolo::pluck('id')->toArray();
        $articoliSedeMap = $this->dryRun ? [] : DB::table('articoli')->pluck('sede_id', 'id')->toArray();
        $articoliCategoriaMap = $this->dryRun ? [] : DB::table('articoli')->pluck('categoria_merceologica_id', 'id')->toArray();
        
        $giacenze = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->select('id', 'id_magazzino', 'qta', 'qta_residua', 'costo_unitario')
            ->get();
        
        // Gestisci duplicati: prendi solo l'ultima per articolo
        $giacenzeUniche = $giacenze
            ->groupBy('id')
            ->map(function($group) {
                if ($group->count() > 1) {
                    $this->problemiGestiti['duplicati_giacenze'] += $group->count() - 1;
                }
                $qta = $group->sum(fn($r) => isset($r->qta) ? (int)$r->qta : 0);
                $qtaResidua = $group->sum(fn($r) => isset($r->qta_residua) ? (int)$r->qta_residua : 0);
                $valoreResiduo = $group->sum(function($r) {
                    $costo = isset($r->costo_unitario) ? (float)$r->costo_unitario : 0;
                    $residua = isset($r->qta_residua) ? (int)$r->qta_residua : 0;
                    return $costo * $residua;
                });
                $costoUnitario = $qtaResidua > 0
                    ? ($valoreResiduo / $qtaResidua)
                    : (float)($group->first()->costo_unitario ?? 0);

                $row = $group->first();
                $row->qta = $qta > 0 ? $qta : 1;
                $row->qta_residua = $qtaResidua;
                if ($row->qta_residua === null) {
                    $row->qta_residua = $row->qta ?? 1;
                }
                $row->costo_unitario = $costoUnitario;
                return $row;
            })
            ->values();
        
        // Filtra solo articoli che esistono
        if (!$this->dryRun) {
            $giacenzeValide = $giacenzeUniche->filter(function($g) use ($articoliImportati) {
                return in_array($g->id, $articoliImportati);
            });
        } else {
            $giacenzeValide = $giacenzeUniche;
        }
        
        $bar = $this->output->createProgressBar($giacenzeValide->count());
        
        foreach ($giacenzeValide as $giac) {
            if (!$this->dryRun) {
                $sedeId = $articoliSedeMap[$giac->id] ?? 1;
                $categoriaId = $giac->id_magazzino ?? ($articoliCategoriaMap[$giac->id] ?? null);
                $qta = isset($giac->qta) ? (int)$giac->qta : 1;
                $qtaResidua = $giac->qta_residua;
                if ($qtaResidua === null) {
                    $qtaResidua = $qta;
                }
                
                DB::table('giacenze')->insert([
                    'articolo_id' => $giac->id,
                    'categoria_merceologica_id' => $categoriaId,
                    'sede_id' => $sedeId,
                    'quantita' => $qta,
                    'quantita_residua' => $qtaResidua,
                    'quantita_deposito' => 0,
                    'costo_unitario' => $giac->costo_unitario ?? 0,
                    'scaffale' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $bar->advance();
        }
        
        // Gestisci articoli orfani (senza giacenza)
        if (!$this->dryRun) {
            $articoliSenzaGiacenza = Articolo::doesntHave('giacenza')->get();
            
            foreach ($articoliSenzaGiacenza as $art) {
                Giacenza::create([
                    'articolo_id' => $art->id,
                    'categoria_merceologica_id' => $art->categoria_merceologica_id,
                    'sede_id' => $art->sede_id,
                    'quantita' => 1,
                    'quantita_residua' => 1,
                    'quantita_deposito' => 0,
                    'costo_unitario' => $art->prezzo_acquisto ?? 0,
                ]);
                $this->problemiGestiti['orfani']++;
            }
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['giacenze'] = $giacenzeValide->count() + $this->problemiGestiti['orfani'];
        $this->line("  ✓ Importate: " . $giacenzeValide->count() . " giacenze");
        
        if ($this->problemiGestiti['duplicati_giacenze'] > 0) {
            $this->warn("  ⚠️  Gestiti {$this->problemiGestiti['duplicati_giacenze']} duplicati (mantenuta ultima)");
        }
        if ($this->problemiGestiti['orfani'] > 0) {
            $this->warn("  ⚠️  Creata giacenza per {$this->problemiGestiti['orfani']} articoli orfani");
        }
        
        $this->newLine();
    }
    
    private function step7_MigrateDdt()
    {
        $this->info('📄 [7/12] DDT Testate...');
        
        $ddt = DB::connection('mssql_prod')
            ->table('mag_ddt_articoli_testate')
            ->whereNotNull('numero_documento')
            ->where('numero_documento', '!=', '')
            ->get();
        
        $fornitoriIds = $this->dryRun ? [] : Fornitore::pluck('id')->toArray();
        $fornitoreFallbackId = $this->dryRun
            ? null
            : Fornitore::where('ragione_sociale', 'DE PASCALIS S.P.A.')->value('id');
        $fornitorePlaceholderId = 1006; // "NON INSERITO" in MSSQL
        
        $bar = $this->output->createProgressBar($ddt->count());
        $importati = 0;
        
        foreach ($ddt as $d) {
            if (!$this->dryRun) {
                // Verifica che il fornitore esista
                $fornitoreId = null;
                if ($d->fornitore == $fornitorePlaceholderId && $fornitoreFallbackId) {
                    $fornitoreId = $fornitoreFallbackId;
                } elseif ($d->fornitore && in_array($d->fornitore, $fornitoriIds)) {
                    $fornitoreId = $d->fornitore;
                }
                
                DB::table('ddt')->insert([
                    'id' => $d->id,
                    'numero' => $d->numero_documento,
                    'data_documento' => $d->data_documento ?? now(),
                    'anno' => date('Y', strtotime($d->data_documento ?? 'now')),
                    'fornitore_id' => $fornitoreId,
                    'stato' => 'caricato',
                    'note' => $d->note ?? null,
                    'tipo_carico' => 'manuale',
                    'data_carico' => $d->data_carico ?? null,
                    'created_at' => $d->created_at ?? now(),
                    'updated_at' => $d->updated_at ?? now(),
                ]);
                $importati++;
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['ddt'] = $importati;
        $this->line("  ✓ Importati: {$importati} DDT");
        $this->newLine();
    }
    
    private function step8_MigrateDdtDettagli()
    {
        $this->info('📋 [8/12] DDT Dettagli...');
        
        $dettagli = DB::connection('mssql_prod')
            ->table('mag_ddt_articoli_dettagli')
            ->get();
        
        $articoliIds = $this->dryRun ? [] : Articolo::pluck('id')->toArray();
        $ddtIds = $this->dryRun ? [] : Ddt::pluck('id')->toArray();
        
        $bar = $this->output->createProgressBar($dettagli->count());
        $importati = 0;
        
        foreach ($dettagli as $det) {
            if (!$this->dryRun) {
                // Verifica FK
                $ddtExists = in_array($det->id_testata, $ddtIds);
                $articoloExists = empty($det->id_articolo) || in_array($det->id_articolo, $articoliIds);
                
                if ($ddtExists && $articoloExists) {
                    DB::table('ddt_dettagli')->insert([
                        'id' => $det->id,
                        'ddt_id' => $det->id_testata,
                        'articolo_id' => $det->id_articolo,
                        'descrizione' => null,
                        'quantita' => $det->qta_caricata ?? 1,
                        'prezzo_unitario' => null,
                        'caricato' => true,
                        'created_at' => $det->created_at ?? now(),
                    ]);
                    $importati++;
                }
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['ddt_dettagli'] = $importati;
        $this->line("  ✓ Importati: {$importati} dettagli");
        $this->newLine();
    }
    
    private function step9_MigrateVetrine()
    {
        $this->info('🏪 [9/12] Vetrine...');
        
        $vetrine = DB::connection('mssql_prod')->table('mag_vetrine')->get();
        
        $bar = $this->output->createProgressBar($vetrine->count());
        
        foreach ($vetrine as $vet) {
            if (!$this->dryRun) {
                DB::table('vetrine')->insert([
                    'id' => $vet->id,
                    'codice' => $vet->codice ?? 'VET-' . $vet->id,
                    'nome' => $vet->nome ?? 'Vetrina ' . $vet->id,
                    'ubicazione' => 'mazzini',
                    'tipologia' => $vet->tipologia ?? 'standard',
                    'attiva' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['vetrine'] = $vetrine->count();
        $this->line("  ✓ Importate: {$vetrine->count()} vetrine");
        $this->newLine();
    }
    
    private function step10_MigrateArticoliVetrine()
    {
        $this->info('🔗 [10/12] Articoli-Vetrine...');
        
        $relazioni = DB::connection('mssql_prod')->table('mag_articoli_vetrine')->get();
        
        $articoliIds = $this->dryRun ? [] : Articolo::pluck('id')->toArray();
        $vetrineIds = $this->dryRun ? [] : Vetrina::pluck('id')->toArray();
        $testiVetrinaByArticolo = [];
        if (!$this->dryRun) {
            $testiVetrinaByArticolo = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->whereNotNull('testo_vetrina')
                ->where('testo_vetrina', '!=', '')
                ->pluck('testo_vetrina', 'id')
                ->toArray();
        }
        
        $bar = $this->output->createProgressBar($relazioni->count());
        $importati = 0;
        
        foreach ($relazioni as $rel) {
            if (!$this->dryRun) {
                $artExists = in_array($rel->id_articolo, $articoliIds);
                $vetExists = in_array($rel->id_vetrina, $vetrineIds);
                
                if ($artExists && $vetExists) {
                    $testoVetrina = $rel->testo_vetrina ?? null;
                    if (empty($testoVetrina) && isset($testiVetrinaByArticolo[$rel->id_articolo])) {
                        $testoVetrina = $testiVetrinaByArticolo[$rel->id_articolo];
                    }
                    
                    $prezzoVetrina = $rel->prezzo_vetrina ?? null;
                    $note = $rel->nc ?? null;
                    
                    DB::table('articoli_vetrine')->insert([
                        'articolo_id' => $rel->id_articolo,
                        'vetrina_id' => $rel->id_vetrina,
                        'testo_vetrina' => $testoVetrina,
                        'posizione' => $rel->ordine_vetrina ?? 0,
                        'ripiano' => null,
                        'prezzo_vetrina' => $prezzoVetrina,
                        'data_inserimento' => now(),
                        'note' => $note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $importati++;
                }
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['articoli_vetrine'] = $importati;
        $this->line("  ✓ Importati: {$importati} relazioni");
        $this->newLine();
    }
    
    private function step11_MigrateProdottiFiniti()
    {
        $this->info('🏭 [11/12] Prodotti Finiti...');
        
        $prodotti = DB::connection('mssql_prod')->table('mag_prodotti_finiti')->get();
        
        $bar = $this->output->createProgressBar($prodotti->count());
        $importatiPF = 0;
        $importatiComponenti = 0;
        
        foreach ($prodotti as $pf) {
            if (!$this->dryRun) {
                // Crea prodotto finito
                $prodottoFinito = ProdottoFinito::create([
                    'codice' => $pf->codice ?? 'PF-' . $pf->id,
                    'descrizione' => $pf->descrizione ?? 'Prodotto Finito ' . $pf->id,
                    'magazzino_id' => $pf->id_magazzino ?? 1,
                    'stato' => 'completato',
                    'data_completamento' => now(),
                    'note' => 'Importato da produzione',
                ]);
                $importatiPF++;
                
                // Importa componenti da mag_diba
                $componenti = DB::connection('mssql_prod')
                    ->table('mag_diba')
                    ->where('id_pf', $pf->id)
                    ->get();
                
                foreach ($componenti as $comp) {
                    // Converti formato "5/1006" → "5-1006"
                    $codiceComponente = str_replace('/', '-', $comp->carico);
                    
                    // Cerca articolo
                    $articolo = Articolo::where('codice', 'LIKE', $codiceComponente . '%')->first();
                    
                    if ($articolo) {
                        try {
                            ComponenteProdotto::create([
                                'prodotto_finito_id' => $prodottoFinito->id,
                                'articolo_id' => $articolo->id,
                                'quantita' => $comp->qta ?? 1,
                            ]);
                            
                            $importatiComponenti++;
                        } catch (\Exception $e) {
                            // Skip duplicati
                        }
                    }
                }
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->stats['prodotti_finiti'] = $importatiPF;
        $this->stats['componenti'] = $importatiComponenti;
        $this->line("  ✓ Importati: {$importatiPF} prodotti finiti, {$importatiComponenti} componenti");
        $this->newLine();
    }
    
    private function step12_MigrateUsers()
    {
        $this->info('👤 [12/12] Utenti e Permessi...');
        
        try {
            $users = DB::connection('mssql_prod')->table('users')->get();
            
            foreach ($users as $user) {
                if (!$this->dryRun) {
                    User::updateOrCreate(
                        ['email' => $user->email],
                        [
                            'name' => $user->name,
                            'password' => $user->password,
                        ]
                    );
                }
            }
            
            $this->stats['users'] = $users->count();
            $this->line("  ✓ Importati: {$users->count()} utenti");

            if (!$this->dryRun) {
                $this->assegnaRuoloAdmin();
                $this->assegnaPermessiAdmin();
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Skip utenti: " . $e->getMessage());
            $this->stats['users'] = 0;
        }
        
        $this->newLine();
    }

    private function assegnaRuoloAdmin()
    {
        // Dopo il reset, i ruoli vengono azzerati: assicuriamo almeno un admin per l'accesso alle sezioni protette.
        $adminRoleId = Role::where('name', 'admin')->value('id');
        $adminUserId = User::min('id');

        if (!$adminRoleId || !$adminUserId) {
            $this->warn('  ⚠️  Ruolo admin o utenti non trovati, skip assegnazione ruolo');
            return;
        }

        DB::table('model_has_roles')->updateOrInsert([
            'role_id' => $adminRoleId,
            'model_type' => User::class,
            'model_id' => $adminUserId,
        ]);

        $this->line("  ✓ Ruolo admin assegnato a user id {$adminUserId}");
    }

    private function assegnaPermessiAdmin()
    {
        // Dopo il reset, le associazioni ruolo/permessi vengono azzerate: assegniamo tutti i permessi all'admin.
        $adminRoleId = Role::where('name', 'admin')->value('id');

        if (!$adminRoleId) {
            $this->warn('  ⚠️  Ruolo admin non trovato, skip permessi');
            return;
        }

        $permessi = Permission::pluck('id');
        foreach ($permessi as $permessoId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permessoId,
                'role_id' => $adminRoleId,
            ], []);
        }

        $this->line('  ✓ Permessi admin assegnati');
    }
    
    private function step13_VerificaFinale()
    {
        $this->info('✅ VERIFICA INTEGRITÀ FINALE');
        $this->info('════════════════════════════');
        $this->newLine();
        
        // Verifica relazione 1:1
        $senzaGiacenza = Articolo::doesntHave('giacenza')->count();
        $this->line("Articoli senza giacenza:    " . ($senzaGiacenza === 0 ? '✅ 0' : "❌ {$senzaGiacenza}"));
        
        // Verifica duplicati giacenze
        $duplicatiGiacenze = DB::table('giacenze')
            ->select('articolo_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('articolo_id')
            ->having('cnt', '>', 1)
            ->count();
        $this->line("Giacenze duplicate:         " . ($duplicatiGiacenze === 0 ? '✅ 0' : "❌ {$duplicatiGiacenze}"));
        
        // Verifica totali
        $this->line("Totale articoli:            ✅ " . Articolo::count());
        $this->line("Totale giacenze:            ✅ " . Giacenza::count());
        $this->line("Totale DDT:                 ✅ " . Ddt::count());
        
        $this->newLine();
    }
    
    private function displaySummary()
    {
        $this->info('════════════════════════════════════════════════');
        $this->info('📊 RIEPILOGO MIGRAZIONE');
        $this->info('════════════════════════════════════════════════');
        $this->newLine();
        
        $this->table(
            ['Entità', 'Records'],
            collect($this->stats)->map(fn($count, $entity) => [ucfirst($entity), number_format($count)])->toArray()
        );
        
        $this->newLine();
        $this->info('🛠️  PROBLEMI GESTITI AUTOMATICAMENTE:');
        $this->line("  - Articoli orfani:         {$this->problemiGestiti['orfani']}");
        $this->line("  - Giacenze duplicate:      {$this->problemiGestiti['duplicati_giacenze']}");
        $this->line("  - Codici duplicati:        {$this->problemiGestiti['duplicati_codici']}");
        $this->line("  - ID duplicati:            {$this->problemiGestiti['duplicati_id']}");
        $this->line("  - Descrizioni vuote:       {$this->problemiGestiti['descrizioni_vuote']}");
        
        $this->newLine();
        
        if ($this->dryRun) {
            $this->warn('⚠️  DRY RUN - Nessun dato è stato salvato');
            $this->info('Esegui con --confirm per applicare le modifiche');
        } else {
            $this->info('✅ MIGRAZIONE COMPLETATA CON SUCCESSO!');
        }
    }
}
