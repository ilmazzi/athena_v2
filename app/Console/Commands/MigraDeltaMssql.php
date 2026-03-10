<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\Ddt;
use App\Models\DdtDettaglio;
use App\Models\Fattura;
use App\Models\FatturaDettaglio;
use App\Models\Fornitore;

class MigraDeltaMssql extends Command
{
    protected $signature = 'migra:delta-mssql 
                            {--dry-run : Simula senza salvare}
                            {--force-missing : Importa articoli mancanti dai dettagli DDT ignorando max ID}
                            {--backfill-ddt-descrizioni : Popola descrizione su ddt_dettagli da articoli}
                            {--backfill-ddt-links : Crea/aggancia DDT e dettagli per articoli senza carico}
                            {--backfill-giacenze : Crea giacenze mancanti dagli articoli MSSQL}
                            {--normalize-codici-base : Rimuove suffisso -N se il codice base non esiste}
                            {--normalize-since= : Data minima (YYYY-MM-DD) per normalizzare codici}
                            {--normalize-from-id= : ID minimo per normalizzare codici}
                            {--align-categoria-by-codice : Allinea categoria_merceologica_id al prefisso del codice}
                            {--align-only-prefix= : Limita allineamento al prefisso (es. 5)}
                            {--normalize-skip-categorie= : Categorie da escludere (es. 5,9)}
                            {--rebuild-codici-from-mssql : Ricalcola codici in base a MSSQL (dup per base)}
                            {--rebuild-only-prefix= : Limita ricalcolo al prefisso (es. 2)}
                            {--rebuild-only-base= : Limita ricalcolo al base (es. 2-64443)}
                            {--rebuild-by-id= : Ricalcola codice per ID specifici (es. 53114,52806)}';
    protected $description = 'Migrazione incrementale (delta) da MSSQL per ID: articoli, DDT e fatture';

    private bool $dryRun = false;
    private array $stats = [
        'fornitori' => 0,
        'articoli' => 0,
        'giacenze' => 0,
        'ddt' => 0,
        'ddt_dettagli' => 0,
        'ddt_dettagli_skipped' => 0,
        'fatture' => 0,
        'fatture_dettagli' => 0,
        'fatture_dettagli_skipped' => 0,
        'errori' => 0,
    ];

    private array $ubicazioneToSedeMapping = [
        0 => 1,
        1 => 1,  // Lecco Cavour
        2 => 3,  // Bellagio Monastero
        3 => 4,  // Bellagio Mazzini
        4 => 2,  // Jolly
        5 => 5,  // Roma
    ];

    private array $codiciUsati = [];
    private array $codiceCounters = [];

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $forceMissing = $this->option('force-missing');
        $backfillDescrizioni = $this->option('backfill-ddt-descrizioni');
        $backfillLinks = $this->option('backfill-ddt-links');
        $backfillGiacenze = $this->option('backfill-giacenze');
        $normalizeCodici = $this->option('normalize-codici-base');
        $normalizeSince = $this->option('normalize-since');
        $normalizeFromId = $this->option('normalize-from-id');
        $normalizeSkipCategorie = $this->option('normalize-skip-categorie');
        $alignCategoria = $this->option('align-categoria-by-codice');
        $alignOnlyPrefix = $this->option('align-only-prefix');
        $rebuildCodici = $this->option('rebuild-codici-from-mssql');
        $rebuildOnlyPrefix = $this->option('rebuild-only-prefix');
        $rebuildOnlyBase = $this->option('rebuild-only-base');
        $rebuildById = $this->option('rebuild-by-id');

        $this->info('🚀 MIGRAZIONE DELTA MSSQL (CRITERIO ID)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        if ($this->dryRun) {
            $this->warn('🔍 MODALITÀ DRY-RUN: Nessuna modifica verrà salvata');
        }
        $this->newLine();

        try {
            DB::connection('mssql_prod')->getPdo();
            $this->info('✅ Connessione MSSQL produzione: OK');
        } catch (\Exception $e) {
            $this->error('❌ Errore connessione MSSQL: ' . $e->getMessage());
            return 1;
        }

        try {
            $this->migraFornitori();
            $this->migraArticoliEGiacenze();
            if ($forceMissing) {
                $this->importMissingArticoliFromDdtDetails();
            }
            $this->migraDdt();
            $this->migraFatture();
            if ($backfillDescrizioni) {
                $this->backfillDdtDettagliDescrizioni();
            }
            if ($backfillLinks) {
                $this->backfillDdtLinksFromArticoli();
            }
            if ($backfillGiacenze) {
                $this->backfillGiacenzeFromMssql();
            }
            if ($normalizeCodici) {
                $this->normalizeCodiciBase($normalizeSince, $normalizeFromId, $normalizeSkipCategorie);
            }
            if ($alignCategoria) {
                $this->alignCategoriaByCodice($alignOnlyPrefix);
            }
            if ($rebuildCodici) {
                $this->rebuildCodiciFromMssql($rebuildOnlyPrefix, $rebuildOnlyBase);
            }
            if (!empty($rebuildById)) {
                $this->rebuildCodiciById($rebuildById);
            }

            if ($this->dryRun) {
                $this->warn('🔄 Dry-run completato (nessuna modifica applicata).');
            } else {
                $this->info('✅ Migrazione delta completata!');
            }
        } catch (\Exception $e) {
            $this->error('❌ Errore durante migrazione delta: ' . $e->getMessage());
            return 1;
        }

        $this->displaySummary();
        return 0;
    }

    private function migraFornitori(): void
    {
        $this->info('🏢 FORNITORI (delta)');

        $maxId = (int) (DB::table('fornitori')->max('id') ?? 0);
        $rows = DB::connection('mssql_prod')
            ->table('mag_fornitori')
            ->where('id', '>', $maxId)
            ->get();

        $this->line("  Max ID attuale: {$maxId} | Nuovi: {$rows->count()}");
        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $forn) {
            try {
                if (!$this->dryRun) {
                    Fornitore::create([
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
                $this->stats['fornitori']++;
            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("  ❌ Fornitore {$forn->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migraArticoliEGiacenze(): void
    {
        $this->info('💎 ARTICOLI + GIACENZE (delta)');

        $maxId = (int) (DB::table('articoli')->max('id') ?? 0);
        $rows = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->where('id', '>', $maxId)
            ->get();

        $this->line("  Max ID attuale: {$maxId} | Nuovi: {$rows->count()}");
        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $art) {
            try {
                $codiceBase = ($art->id_magazzino ?? '0') . '-' . ($art->carico ?? $art->id);
                $codiceUnico = $this->generateUniqueCodice($codiceBase);

                $descrizione = trim((string) ($art->descrizione ?? ''));
                if ($descrizione === '') {
                    $descrizione = 'Articolo ' . $art->id;
                }

                $sedeId = 1;
                if (property_exists($art, 'ubicazione_magazzino')) {
                    $sedeId = $this->ubicazioneToSedeMapping[$art->ubicazione_magazzino ?? 0] ?? 1;
                }

                $fornitoreId = $this->resolveFornitoreIdFromArticolo($art);

                if (!$this->dryRun) {
                    Articolo::create([
                        'id' => $art->id,
                        'codice' => $codiceUnico,
                        'descrizione' => $descrizione,
                        'descrizione_estesa' => $art->note ?? null,
                        'categoria_merceologica_id' => $art->id_magazzino ?? null,
                        'sede_id' => $sedeId,
                        'fornitore_id' => $fornitoreId,
                        'materiale' => $art->materiale ?? null,
                        'colore' => $art->colore ?? null,
                        'peso_lordo' => $art->peso_lordo ?? null,
                        'peso_netto' => $art->peso_netto ?? null,
                        'titolo' => $art->oro ?? null,
                        'caratura' => $art->carati ?? null,
                        'prezzo_acquisto' => $art->costo_unitario ?? 0,
                        'prezzo_fornitore' => $art->prezzo_fornitore ?? null,
                        'stato_articolo' => 'disponibile',
                        'tipo_carico' => isset($art->fatturato) && $art->fatturato == 1 ? 'fattura' : 'ddt',
                        'numero_documento_carico' => $art->numero_documento ?? null,
                        'data_carico' => $art->data_documento ?? null,
                        'in_vetrina' => (bool) ($art->vetrina ?? false),
                        'foto_principale' => $art->foto_url ?? null,
                        'caratteristiche' => json_encode([
                            'marca' => $art->marca ?? null,
                            'referenza' => $art->referenza ?? null,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Giacenza
                    $qta = isset($art->qta) ? (int) $art->qta : 1;
                    $qtaResidua = $art->qta_residua ?? $qta;
                    Giacenza::create([
                        'articolo_id' => $art->id,
                        'categoria_merceologica_id' => $art->id_magazzino ?? null,
                        'sede_id' => $sedeId,
                        'quantita' => $qta,
                        'quantita_residua' => $qtaResidua,
                        'quantita_deposito' => 0,
                        'costo_unitario' => $art->costo_unitario ?? 0,
                        'scaffale' => $art->ubicazione ?? null,
                        'note' => $art->ubicazione ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->stats['articoli']++;
                $this->stats['giacenze']++;
            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("  ❌ Articolo {$art->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migraDdt(): void
    {
        if (!Schema::connection('mssql_prod')->hasTable('mag_ddt_articoli_testate')) {
            $this->warn('⚠️  Tabella mag_ddt_articoli_testate non trovata, skip DDT');
            $this->newLine();
            return;
        }

        $this->info('📄 DDT (delta)');

        $maxId = (int) (DB::table('ddt')->max('id') ?? 0);
        $rows = DB::connection('mssql_prod')
            ->table('mag_ddt_articoli_testate')
            ->where('id', '>', $maxId)
            ->whereNotNull('numero_documento')
            ->where('numero_documento', '!=', '')
            ->get();

        $this->line("  Max ID attuale: {$maxId} | Nuovi: {$rows->count()}");
        $bar = $this->output->createProgressBar($rows->count());

        $fornitoreFallbackId = Fornitore::where('ragione_sociale', 'DE PASCALIS S.P.A.')->value('id')
            ?? Fornitore::min('id');

        foreach ($rows as $d) {
            try {
                if (!$this->dryRun) {
                    $fornitoreId = $this->resolveFornitoreIdFromId($d->fornitore ?? null, $fornitoreFallbackId);
                    Ddt::create([
                        'id' => $d->id,
                        'numero' => $d->numero_documento,
                        'data_documento' => $d->data_documento ?? now(),
                        'anno' => date('Y', strtotime($d->data_documento ?? 'now')),
                        'fornitore_id' => $fornitoreId,
                        'stato' => 'caricato',
                        'note' => $d->note ?? null,
                        'data_carico' => $d->data_carico ?? null,
                        'created_at' => $d->created_at ?? now(),
                        'updated_at' => $d->updated_at ?? now(),
                    ]);
                }
                $this->stats['ddt']++;
            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("  ❌ DDT {$d->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->migraDdtDettagli();
    }

    private function migraDdtDettagli(): void
    {
        if (!Schema::connection('mssql_prod')->hasTable('mag_ddt_articoli_dettagli')) {
            $this->warn('⚠️  Tabella mag_ddt_articoli_dettagli non trovata, skip dettagli DDT');
            $this->newLine();
            return;
        }

        $this->info('📋 DETTAGLI DDT (delta)');

        $maxId = (int) (DB::table('ddt_dettagli')->max('id') ?? 0);
        $rows = DB::connection('mssql_prod')
            ->table('mag_ddt_articoli_dettagli')
            ->where('id', '>', $maxId)
            ->get();

        $this->line("  Max ID attuale: {$maxId} | Nuovi: {$rows->count()}");
        $this->importMissingArticoliForDdtDettagli($rows);

        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $det) {
            try {
                $ddtId = $det->id_testata ?? null;
                $articoloId = $det->id_articolo ?? null;
                if (!$ddtId || !Ddt::where('id', $ddtId)->exists()) {
                    $bar->advance();
                    continue;
                }

                $articoloExists = $articoloId && DB::table('articoli')->where('id', $articoloId)->exists();
                if (!$articoloExists && $articoloId) {
                    $imported = $this->importArticoloById($articoloId);
                    $articoloExists = $imported || DB::table('articoli')->where('id', $articoloId)->exists();
                }
                if ($articoloExists) {
                    $deletedAt = DB::table('articoli')->where('id', $articoloId)->value('deleted_at');
                    if (!empty($deletedAt)) {
                        DB::table('articoli')->where('id', $articoloId)->update(['deleted_at' => null]);
                    }
                    $this->ensureGiacenzaForArticolo($articoloId);
                }
                if (!$articoloExists) {
                    throw new \RuntimeException("Articolo {$articoloId} mancante: dettaglio DDT {$det->id} non importabile");
                }

                if (!$this->dryRun) {
                    $descrizione = $det->descrizione ?? null;
                    if (!$descrizione && $articoloId) {
                        $descrizione = Articolo::where('id', $articoloId)->value('descrizione');
                    }
                    DdtDettaglio::create([
                        'id' => $det->id,
                        'ddt_id' => $ddtId,
                        'articolo_id' => $articoloId,
                        'descrizione' => $descrizione,
                        'quantita' => $det->qta_caricata ?? $det->quantita ?? 1,
                        'prezzo_unitario' => $det->prezzo_unitario ?? null,
                        'caricato' => true,
                        'created_at' => $det->created_at ?? now(),
                    ]);
                }

                $this->stats['ddt_dettagli']++;
            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("  ❌ Dettaglio DDT {$det->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function importMissingArticoliForDdtDettagli($rows): void
    {
        $articoliIds = collect($rows)
            ->pluck('id_articolo')
            ->filter(fn($id) => !empty($id))
            ->unique()
            ->values();

        if ($articoliIds->isEmpty()) {
            return;
        }

        $missing = $articoliIds->reject(function ($id) {
            return DB::table('articoli')->where('id', $id)->exists();
        })->values();

        if ($missing->isEmpty()) {
            return;
        }

        $this->info("  ↳ Articoli mancanti dai dettagli DDT: {$missing->count()} (import in corso)");

        $missing->chunk(500)->each(function ($chunk) {
            $rows = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->whereIn('id', $chunk->all())
                ->get();
            if ($rows->isEmpty()) {
                $this->warn("  ⚠️  Nessun articolo trovato in MSSQL per chunk: " . implode(',', $chunk->all()));
            }

            foreach ($rows as $art) {
                try {
                    $existing = DB::table('articoli')->where('id', $art->id)->first();
                    if ($existing) {
                        if (!empty($existing->deleted_at)) {
                            DB::table('articoli')
                                ->where('id', $art->id)
                                ->update(['deleted_at' => null]);
                        }
                        continue;
                    }

                    $this->insertArticoloFromRow($art);

                    $this->stats['articoli']++;
                    $this->stats['giacenze']++;
                } catch (\Exception $e) {
                    $this->stats['errori']++;
                    $this->error("  ❌ Articolo mancante {$art->id}: {$e->getMessage()}");
                }
            }
        });
    }

    private function importMissingArticoliFromDdtDetails(): void
    {
        if (!Schema::connection('mssql_prod')->hasTable('mag_ddt_articoli_dettagli')) {
            $this->warn('⚠️  Tabella mag_ddt_articoli_dettagli non trovata, skip force-missing');
            $this->newLine();
            return;
        }

        $this->info('🔁 FORCE-MISSING: Import articoli mancanti dai dettagli DDT');

        $ids = DB::connection('mssql_prod')
            ->table('mag_ddt_articoli_dettagli')
            ->select('id_articolo')
            ->whereNotNull('id_articolo')
            ->distinct()
            ->pluck('id_articolo')
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            $this->line('  Nessun articolo trovato nei dettagli DDT.');
            $this->newLine();
            return;
        }

        $missing = $ids->filter(function ($id) {
            return !DB::table('articoli')->where('id', $id)->exists();
        })->values();

        $this->line("  Articoli mancanti da importare: {$missing->count()}");

        if ($missing->isEmpty()) {
            $this->newLine();
            return;
        }

        $bar = $this->output->createProgressBar($missing->count());
        $bar->start();

        $missing->chunk(500)->each(function ($chunk) use ($bar) {
            $rows = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->whereIn('id', $chunk->all())
                ->get();

            foreach ($rows as $art) {
                try {
                    $existing = DB::table('articoli')->where('id', $art->id)->first();
                    if ($existing) {
                        if (!empty($existing->deleted_at)) {
                            DB::table('articoli')->where('id', $art->id)->update(['deleted_at' => null]);
                        }
                        $this->syncExistingArticoloFromRow($art, $existing);
                        $this->ensureGiacenzaForArticolo($art->id, $art);
                    } else {
                        $this->insertArticoloFromRow($art);
                        $this->stats['articoli']++;
                        $this->stats['giacenze']++;
                    }
                } catch (\Exception $e) {
                    $this->stats['errori']++;
                    $this->error("  ❌ Articolo {$art->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function backfillDdtDettagliDescrizioni(): void
    {
        $this->info('📝 BACKFILL DESCRIZIONI DDT DETTAGLI');

        $query = DB::table('ddt_dettagli')
            ->where(function ($q) {
                $q->whereNull('descrizione')
                  ->orWhere('descrizione', '');
            })
            ->whereNotNull('articolo_id');

        $count = (clone $query)->count();
        $this->line("  Righe senza descrizione: {$count}");

        if ($count === 0 || $this->dryRun) {
            $this->newLine();
            return;
        }

        $updated = 0;
        $query->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$updated) {
                foreach ($rows as $row) {
                    $descrizione = DB::table('articoli')
                        ->where('id', $row->articolo_id)
                        ->value('descrizione');
                    if (!$descrizione) {
                        continue;
                    }
                    $affected = DB::table('ddt_dettagli')
                        ->where('id', $row->id)
                        ->update(['descrizione' => $descrizione]);
                    $updated += $affected;
                }
            });

        $this->line("  Aggiornate descrizioni: {$updated}");
        $this->newLine();
    }

    private function backfillDdtLinksFromArticoli(): void
    {
        $this->info('🔗 BACKFILL LINK DDT DA ARTICOLI');

        if (!Schema::connection('mssql_prod')->hasTable('mag_ddt_articoli_testate') ||
            !Schema::connection('mssql_prod')->hasTable('mag_ddt_articoli_dettagli')) {
            $this->warn('⚠️  Tabelle DDT MSSQL non trovate, skip');
            $this->newLine();
            return;
        }

        $query = Articolo::query()
            ->whereNotNull('numero_documento_carico')
            ->where('numero_documento_carico', '!=', '')
            ->whereDoesntHave('ddtDettaglio');

        $count = (clone $query)->count();
        $this->line("  Articoli senza DDT dettagli: {$count}");

        if ($count === 0 || $this->dryRun) {
            $this->newLine();
            return;
        }

        $query->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $articolo) {
                    $mssqlDet = DB::connection('mssql_prod')
                        ->table('mag_ddt_articoli_dettagli')
                        ->where('id_articolo', $articolo->id)
                        ->orderByDesc('id')
                        ->first();

                    if (!$mssqlDet) {
                        continue;
                    }

                    $ddtId = $mssqlDet->id_testata ?? null;
                    if (!$ddtId) {
                        continue;
                    }

                    $ddtExists = Ddt::where('id', $ddtId)->exists();
                    if (!$ddtExists) {
                        $ddtMssql = DB::connection('mssql_prod')
                            ->table('mag_ddt_articoli_testate')
                            ->where('id', $ddtId)
                            ->first();
                        if ($ddtMssql) {
                            $fornitoreId = $this->resolveFornitoreIdFromId($ddtMssql->fornitore ?? null, Fornitore::min('id'));
                            Ddt::create([
                                'id' => $ddtMssql->id,
                                'numero' => $ddtMssql->numero_documento ?? $articolo->numero_documento_carico,
                                'data_documento' => $ddtMssql->data_documento ?? $articolo->data_carico ?? now(),
                                'anno' => date('Y', strtotime($ddtMssql->data_documento ?? ($articolo->data_carico ?? 'now'))),
                                'fornitore_id' => $fornitoreId,
                                'stato' => 'caricato',
                                'note' => $ddtMssql->note ?? null,
                                'data_carico' => $ddtMssql->data_carico ?? null,
                                'created_at' => $ddtMssql->created_at ?? now(),
                                'updated_at' => $ddtMssql->updated_at ?? now(),
                            ]);
                        }
                    }

                    if (!DdtDettaglio::where('ddt_id', $ddtId)->where('articolo_id', $articolo->id)->exists()) {
                        DdtDettaglio::create([
                            'ddt_id' => $ddtId,
                            'articolo_id' => $articolo->id,
                            'descrizione' => $articolo->descrizione,
                            'quantita' => $mssqlDet->qta_caricata ?? $mssqlDet->quantita ?? 1,
                            'prezzo_unitario' => $mssqlDet->prezzo_unitario ?? null,
                            'caricato' => true,
                            'created_at' => $mssqlDet->created_at ?? now(),
                        ]);
                        $this->stats['ddt_dettagli']++;
                    }
                }
            });

        $this->newLine();
    }

    private function backfillGiacenzeFromMssql(): void
    {
        $this->info('📦 BACKFILL GIACENZE DA MSSQL');

        if (!Schema::connection('mssql_prod')->hasTable('elenco_articoli_magazzino')) {
            $this->warn('⚠️  Vista elenco_articoli_magazzino non trovata, skip');
            $this->newLine();
            return;
        }

        $missing = DB::table('articoli')
            ->leftJoin('giacenze', 'giacenze.articolo_id', '=', 'articoli.id')
            ->whereNull('giacenze.id')
            ->select('articoli.id')
            ->pluck('id');

        $count = $missing->count();
        $this->line("  Giacenze mancanti: {$count}");

        if ($count === 0 || $this->dryRun) {
            $this->newLine();
            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $missing->chunk(500)->each(function ($chunk) use ($bar) {
            $rows = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->whereIn('id', $chunk->all())
                ->get();

            foreach ($rows as $art) {
                try {
                    $articolo = DB::table('articoli')->where('id', $art->id)->first();
                    if (!$articolo) {
                        $bar->advance();
                        continue;
                    }

                    $sedeId = $articolo->sede_id ?? 1;
                    $qta = isset($art->qta) ? (int) $art->qta : 1;
                    $qtaResidua = $art->qta_residua ?? $qta;
                    DB::table('giacenze')->insert([
                        'articolo_id' => $art->id,
                        'categoria_merceologica_id' => $articolo->categoria_merceologica_id ?? null,
                        'sede_id' => $sedeId,
                        'quantita' => $qta,
                        'quantita_residua' => $qtaResidua,
                        'quantita_deposito' => 0,
                        'costo_unitario' => $art->costo_unitario ?? 0,
                        'scaffale' => $art->ubicazione ?? null,
                        'note' => $art->ubicazione ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->stats['giacenze']++;
                } catch (\Exception $e) {
                    $this->stats['errori']++;
                    $this->error("  ❌ Giacenza articolo {$art->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function normalizeCodiciBase(?string $since, ?string $fromId, ?string $skipCategorie): void
    {
        $this->info('🔧 NORMALIZZA CODICI BASE');

        $query = DB::table('articoli')
            ->select('id', 'codice', 'created_at', 'categoria_merceologica_id');

        if (!empty($since)) {
            $query->whereDate('created_at', '>=', $since);
        }

        if (!empty($fromId)) {
            $query->where('id', '>=', (int) $fromId);
        }

        if (empty($since) && empty($fromId)) {
            $query->whereDate('created_at', '>=', now()->toDateString());
        }

        $rows = $query->orderBy('id')->get();
        $this->line("  Candidati: {$rows->count()}");

        if ($rows->isEmpty() || $this->dryRun) {
            $this->newLine();
            return;
        }

        $updated = 0;
        $skip = [];
        if (!empty($skipCategorie)) {
            $skip = array_values(array_filter(array_map('intval', explode(',', $skipCategorie))));
        }

        foreach ($rows as $row) {
            if (!empty($skip) && in_array((int) $row->categoria_merceologica_id, $skip, true)) {
                continue;
            }

            $codice = (string) $row->codice;
            if (!preg_match('/^(\d+-\d+)-(\d+)$/', $codice, $matches)) {
                continue;
            }
            $base = $matches[1];
            $existsBase = DB::table('articoli')->where('codice', $base)->exists();
            if ($existsBase) {
                continue;
            }
            DB::table('articoli')->where('id', $row->id)->update(['codice' => $base]);
            $updated++;
        }

        $this->line("  Codici normalizzati: {$updated}");
        $this->newLine();
    }

    private function alignCategoriaByCodice(?string $onlyPrefix): void
    {
        $this->info('🧭 ALLINEA CATEGORIA DAL CODICE');

        $query = DB::table('articoli')
            ->select('id', 'codice', 'categoria_merceologica_id');

        if (!empty($onlyPrefix)) {
            $prefix = (int) $onlyPrefix;
            $query->where('codice', 'like', $prefix . '-%');
        } else {
            $query->whereRaw("codice REGEXP '^[0-9]+-'");
        }

        $rows = $query->orderBy('id')->get();
        $this->line("  Candidati: {$rows->count()}");

        if ($rows->isEmpty() || $this->dryRun) {
            $this->newLine();
            return;
        }

        $updated = 0;
        foreach ($rows as $row) {
            if (!preg_match('/^(\d+)-/', (string) $row->codice, $m)) {
                continue;
            }
            $prefix = (int) $m[1];
            if ($prefix === (int) $row->categoria_merceologica_id) {
                continue;
            }
            $categoriaExists = DB::table('categorie_merceologiche')->where('id', $prefix)->exists();
            if (!$categoriaExists) {
                continue;
            }
            DB::table('articoli')->where('id', $row->id)->update([
                'categoria_merceologica_id' => $prefix,
                'updated_at' => now(),
            ]);
            $updated++;
        }

        $this->line("  Articoli riallineati: {$updated}");
        $this->newLine();
    }

    private function rebuildCodiciFromMssql(?string $onlyPrefix, ?string $onlyBase): void
    {
        if (!Schema::connection('mssql_prod')->hasTable('elenco_articoli_magazzino')) {
            $this->warn('⚠️  Vista elenco_articoli_magazzino non trovata, skip');
            $this->newLine();
            return;
        }

        $this->info('🧩 RICALCOLA CODICI DA MSSQL');

        $baseMagazzino = null;
        $baseCarico = null;
        if (!empty($onlyBase)) {
            if (!preg_match('/^(\d+)-(\d+)$/', $onlyBase, $matches)) {
                $this->error('  ❌ --rebuild-only-base deve essere nel formato X-Y (es. 2-64443)');
                $this->newLine();
                return;
            }
            $baseMagazzino = (int) $matches[1];
            $baseCarico = (int) $matches[2];
        }

        $dupQuery = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->select('id_magazzino', 'carico', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('id_magazzino')
            ->whereNotNull('carico');

        if (!empty($onlyPrefix)) {
            $dupQuery->where('id_magazzino', (int) $onlyPrefix);
        }
        if ($baseMagazzino !== null && $baseCarico !== null) {
            $dupQuery->where('id_magazzino', $baseMagazzino)
                ->where('carico', $baseCarico);
        }

        $groups = $dupQuery
            ->groupBy('id_magazzino', 'carico')
            ->having('cnt', '>', 1)
            ->get();

        $this->line("  Gruppi duplicati: {$groups->count()}");
        if ($groups->isEmpty() || $this->dryRun) {
            $this->newLine();
            return;
        }

        $updated = 0;
        foreach ($groups as $group) {
            $base = (int) $group->id_magazzino . '-' . (int) $group->carico;

            $ids = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->where('id_magazzino', $group->id_magazzino)
                ->where('carico', $group->carico)
                ->orderBy('id')
                ->pluck('id')
                ->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $existing = DB::table('articoli')
                ->whereIn('id', $ids->all())
                ->pluck('codice', 'id');

            $desired = [];
            foreach ($ids as $index => $id) {
                $desired[$id] = $index === 0 ? $base : ($base . '-' . ($index + 1));
            }

            $needsUpdate = false;
            foreach ($desired as $id => $code) {
                if (!isset($existing[$id]) || $existing[$id] !== $code) {
                    $needsUpdate = true;
                    break;
                }
            }
            if (!$needsUpdate) {
                continue;
            }

            DB::transaction(function () use ($desired, $existing, &$updated) {
                foreach ($desired as $id => $code) {
                    if (!isset($existing[$id])) {
                        continue;
                    }
                    DB::table('articoli')->where('id', $id)->update([
                        'codice' => $code . '-TMP-' . $id,
                        'updated_at' => now(),
                    ]);
                }

                foreach ($desired as $id => $code) {
                    if (!isset($existing[$id])) {
                        continue;
                    }
                    DB::table('articoli')->where('id', $id)->update([
                        'codice' => $code,
                        'updated_at' => now(),
                    ]);
                    $updated++;
                }
            });
        }

        $this->line("  Codici ricalcolati: {$updated}");
        $this->newLine();
    }

    private function rebuildCodiciById(string $idsCsv): void
    {
        if (!Schema::connection('mssql_prod')->hasTable('elenco_articoli_magazzino')) {
            $this->warn('⚠️  Vista elenco_articoli_magazzino non trovata, skip');
            $this->newLine();
            return;
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if (empty($ids)) {
            $this->warn('⚠️  Nessun ID valido fornito.');
            $this->newLine();
            return;
        }

        $this->info('🧩 RICALCOLA CODICI PER ID');

        $rows = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->select('id', 'id_magazzino', 'carico')
            ->whereIn('id', $ids)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('⚠️  Nessun articolo trovato in MSSQL per gli ID richiesti.');
            $this->newLine();
            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $magazzino = (int) ($row->id_magazzino ?? 0);
            $carico = $row->carico ?? $row->id;
            $key = $magazzino . '|' . $carico;
            $groups[$key] = [$magazzino, $carico];
        }

        $updated = 0;
        foreach ($groups as [$magazzino, $carico]) {
            $base = $magazzino . '-' . $carico;

            $idsGroup = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->where('id_magazzino', $magazzino)
                ->where('carico', $carico)
                ->orderBy('id')
                ->pluck('id')
                ->values();

            if ($idsGroup->isEmpty()) {
                continue;
            }

            $existing = DB::table('articoli')
                ->whereIn('id', $idsGroup->all())
                ->pluck('codice', 'id');

            $desired = [];
            foreach ($idsGroup as $index => $id) {
                $desired[$id] = $index === 0 ? $base : ($base . '-' . ($index + 1));
            }

            $needsUpdate = false;
            foreach ($desired as $id => $code) {
                if (isset($existing[$id]) && $existing[$id] !== $code) {
                    $needsUpdate = true;
                    break;
                }
            }
            if (!$needsUpdate) {
                continue;
            }

            if ($this->dryRun) {
                continue;
            }

            DB::transaction(function () use ($desired, $existing, &$updated) {
                foreach ($desired as $id => $code) {
                    if (!isset($existing[$id])) {
                        continue;
                    }
                    DB::table('articoli')->where('id', $id)->update([
                        'codice' => $code . '-TMP-' . $id,
                        'updated_at' => now(),
                    ]);
                }

                foreach ($desired as $id => $code) {
                    if (!isset($existing[$id])) {
                        continue;
                    }
                    DB::table('articoli')->where('id', $id)->update([
                        'codice' => $code,
                        'updated_at' => now(),
                    ]);
                    $updated++;
                }
            });
        }

        $this->line("  Codici ricalcolati: {$updated}");
        $this->newLine();
    }

    private function migraFatture(): void
    {
        $testateTable = $this->resolveFattureTestateTable();
        $dettagliTable = $this->resolveFattureDettagliTable();

        if (!$testateTable || !$dettagliTable) {
            $this->warn('⚠️  Tabelle fatture non trovate in MSSQL, skip fatture');
            $this->newLine();
            return;
        }

        $this->info('🧾 FATTURE (delta)');

        $maxId = (int) (DB::table('fatture')->max('id') ?? 0);
        $rows = DB::connection('mssql_prod')
            ->table($testateTable)
            ->where('id', '>', $maxId)
            ->get();

        $this->line("  Max ID attuale: {$maxId} | Nuovi: {$rows->count()}");
        $bar = $this->output->createProgressBar($rows->count());

        $fornitoreFallbackId = Fornitore::where('ragione_sociale', 'DE PASCALIS S.P.A.')->value('id')
            ?? Fornitore::min('id');

        foreach ($rows as $f) {
            try {
                if (!$this->dryRun) {
                    $fornitoreId = $this->resolveFornitoreIdFromId($f->fornitore ?? null, $fornitoreFallbackId);
                    $dataDocumento = $f->data_documento ?? $f->data_fattura ?? now();
                    $numero = $f->numero_documento ?? $f->numero_fattura ?? $f->numero ?? $f->id;
                    Fattura::create([
                        'id' => $f->id,
                        'numero' => $numero,
                        'data_documento' => $dataDocumento,
                        'anno' => date('Y', strtotime($dataDocumento ?? 'now')),
                        'fornitore_id' => $fornitoreId,
                        'imponibile' => $f->imponibile ?? 0,
                        'iva' => $f->iva ?? 0,
                        'totale' => $f->totale ?? 0,
                        'stato' => 'caricato',
                        'note' => $f->note ?? null,
                        'created_at' => $f->created_at ?? now(),
                        'updated_at' => $f->updated_at ?? now(),
                    ]);
                }
                $this->stats['fatture']++;
            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("  ❌ Fattura {$f->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->migraFattureDettagli($dettagliTable);
    }

    private function migraFattureDettagli(string $dettagliTable): void
    {
        $this->info('📋 DETTAGLI FATTURE (delta)');

        $maxId = (int) (DB::table('fatture_dettagli')->max('id') ?? 0);
        $rows = DB::connection('mssql_prod')
            ->table($dettagliTable)
            ->where('id', '>', $maxId)
            ->get();

        $this->line("  Max ID attuale: {$maxId} | Nuovi: {$rows->count()}");
        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $det) {
            try {
                $fatturaId = $det->id_testata ?? $det->fattura_id ?? null;
                $articoloId = $det->id_articolo ?? null;
                if (!$fatturaId || !Fattura::where('id', $fatturaId)->exists()) {
                    $bar->advance();
                    continue;
                }

                $articoloExists = $articoloId && DB::table('articoli')->where('id', $articoloId)->exists();
                if (!$articoloExists && $articoloId) {
                    $imported = $this->importArticoloById($articoloId);
                    $articoloExists = $imported || DB::table('articoli')->where('id', $articoloId)->exists();
                }
                if ($articoloExists) {
                    $deletedAt = DB::table('articoli')->where('id', $articoloId)->value('deleted_at');
                    if (!empty($deletedAt)) {
                        DB::table('articoli')->where('id', $articoloId)->update(['deleted_at' => null]);
                    }
                    $this->ensureGiacenzaForArticolo($articoloId);
                }
                if (!$articoloExists) {
                    throw new \RuntimeException("Articolo {$articoloId} mancante: dettaglio Fattura {$det->id} non importabile");
                }

                if (!$this->dryRun) {
                    FatturaDettaglio::create([
                        'id' => $det->id,
                        'fattura_id' => $fatturaId,
                        'articolo_id' => $articoloId,
                        'codice_articolo' => $det->codice_articolo ?? null,
                        'descrizione' => $det->descrizione ?? ('Articolo ' . $articoloId),
                        'quantita' => $det->quantita ?? 1,
                        'prezzo_unitario' => $det->prezzo_unitario ?? 0,
                        'sconto_percentuale' => $det->sconto_percentuale ?? 0,
                        'iva_percentuale' => $det->iva_percentuale ?? 22.00,
                        'totale_riga' => $det->totale_riga ?? 0,
                        'caricato' => true,
                        'created_at' => $det->created_at ?? now(),
                    ]);
                }

                $this->stats['fatture_dettagli']++;
            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("  ❌ Dettaglio Fattura {$det->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function resolveFattureTestateTable(): ?string
    {
        $candidates = [
            'mag_fatture_testate',
            'mag_fatture_articoli_testate',
            'mag_fatture',
        ];
        foreach ($candidates as $table) {
            if (Schema::connection('mssql_prod')->hasTable($table)) {
                return $table;
            }
        }
        return null;
    }

    private function resolveFattureDettagliTable(): ?string
    {
        $candidates = [
            'mag_fatture_dettagli',
            'mag_fatture_articoli_dettagli',
            'mag_fatture_righe',
        ];
        foreach ($candidates as $table) {
            if (Schema::connection('mssql_prod')->hasTable($table)) {
                return $table;
            }
        }
        return null;
    }

    private function resolveFornitoreIdFromId($value, ?int $fallbackId): ?int
    {
        if (is_numeric($value)) {
            $id = (int) $value;
            if (Fornitore::where('id', $id)->exists()) {
                return $id;
            }
        }
        return $fallbackId;
    }

    private function resolveFornitoreIdFromArticolo(object $art): ?int
    {
        $fallbackId = Fornitore::where('ragione_sociale', 'DE PASCALIS S.P.A.')->value('id')
            ?? Fornitore::min('id');

        if (property_exists($art, 'fornitore') && $art->fornitore) {
            $value = $art->fornitore;
            if (is_numeric($value) && Fornitore::where('id', (int) $value)->exists()) {
                return (int) $value;
            }
            return $this->resolveFornitoreIdFromString((string) $value, $fallbackId);
        }

        if (property_exists($art, 'fornitore_import') && $art->fornitore_import) {
            return $this->resolveFornitoreIdFromString((string) $art->fornitore_import, $fallbackId);
        }

        return $fallbackId;
    }

    private function resolveFornitoreIdFromString(string $ragioneSociale, ?int $fallbackId): ?int
    {
        $ragioneSociale = trim($ragioneSociale);
        if ($ragioneSociale === '') {
            return $fallbackId;
        }

        if (strcasecmp($ragioneSociale, 'NON INSERITO') === 0) {
            $ragioneSociale = 'DE PASCALIS S.P.A.';
        }

        $fornitore = Fornitore::where('ragione_sociale', $ragioneSociale)->first();
        if (!$fornitore) {
            $fornitore = Fornitore::where('ragione_sociale', 'like', '%' . $ragioneSociale . '%')->first();
        }
        if ($fornitore) {
            return $fornitore->id;
        }

        if ($this->dryRun) {
            return $fallbackId;
        }

        $fornitore = Fornitore::create([
            'ragione_sociale' => $ragioneSociale,
            'note' => 'Creato da migrazione delta MSSQL',
        ]);

        return $fornitore->id;
    }

    private function importArticoloById(int $articoloId): bool
    {
        $art = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->where('id', $articoloId)
            ->first();

        if (!$art) {
            return false;
        }

        $existing = DB::table('articoli')->where('id', $articoloId)->first();
        if ($existing) {
            if (!empty($existing->deleted_at)) {
                DB::table('articoli')->where('id', $articoloId)->update(['deleted_at' => null]);
            }
            $this->syncExistingArticoloFromRow($art, $existing);
            $this->ensureGiacenzaForArticolo($articoloId, $art);
            return true;
        }

        $this->insertArticoloFromRow($art);
        $this->stats['articoli']++;
        $this->stats['giacenze']++;
        return true;
    }

    private function ensureGiacenzaForArticolo(int $articoloId, ?object $art = null): void
    {
        $exists = DB::table('giacenze')->where('articolo_id', $articoloId)->exists();
        if ($exists || $this->dryRun) {
            return;
        }

        if (!$art) {
            $art = DB::connection('mssql_prod')
                ->table('elenco_articoli_magazzino')
                ->where('id', $articoloId)
                ->first();
        }

        $articolo = DB::table('articoli')->where('id', $articoloId)->first();
        if (!$articolo) {
            return;
        }

        $qta = $art ? (int) ($art->qta ?? 1) : 1;
        $qtaResidua = $art ? ($art->qta_residua ?? $qta) : $qta;
        $costo = $art ? ($art->costo_unitario ?? 0) : ($articolo->prezzo_acquisto ?? 0);
        $scaffale = $art->ubicazione ?? null;
        $note = $art->ubicazione ?? null;

        DB::table('giacenze')->insert([
            'articolo_id' => $articoloId,
            'categoria_merceologica_id' => $articolo->categoria_merceologica_id ?? null,
            'sede_id' => $articolo->sede_id ?? 1,
            'quantita' => $qta,
            'quantita_residua' => $qtaResidua,
            'quantita_deposito' => 0,
            'costo_unitario' => $costo,
            'scaffale' => $scaffale,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->stats['giacenze']++;
    }

    private function syncExistingArticoloFromRow(object $art, object $existing): void
    {
        if ($this->dryRun) {
            return;
        }

        $sedeId = $existing->sede_id ?? 1;
        if (property_exists($art, 'ubicazione_magazzino')) {
            $sedeId = $this->ubicazioneToSedeMapping[$art->ubicazione_magazzino ?? 0] ?? $sedeId;
        }

        $codiceBase = ($art->id_magazzino ?? '0') . '-' . ($art->carico ?? $art->id);
        $codiceUnico = $existing->codice;
        if (strpos((string) $existing->codice, $codiceBase) !== 0) {
            $codiceUnico = $this->generateUniqueCodice($codiceBase);
        }

        DB::table('articoli')->where('id', $art->id)->update([
            'codice' => $codiceUnico,
            'descrizione' => $art->descrizione ?? $existing->descrizione ?? ('Articolo ' . $art->id),
            'descrizione_estesa' => $art->note ?? $existing->descrizione_estesa,
            'categoria_merceologica_id' => $art->id_magazzino ?? $existing->categoria_merceologica_id,
            'sede_id' => $sedeId,
            'materiale' => $art->materiale ?? $existing->materiale,
            'colore' => $art->colore ?? $existing->colore,
            'peso_lordo' => $art->peso_lordo ?? $existing->peso_lordo,
            'peso_netto' => $art->peso_netto ?? $existing->peso_netto,
            'titolo' => $art->oro ?? $existing->titolo,
            'caratura' => $art->carati ?? $existing->caratura,
            'prezzo_acquisto' => $art->costo_unitario ?? $existing->prezzo_acquisto,
            'prezzo_fornitore' => $art->prezzo_fornitore ?? $existing->prezzo_fornitore,
            'tipo_carico' => isset($art->fatturato) && $art->fatturato == 1 ? 'fattura' : 'ddt',
            'numero_documento_carico' => $art->numero_documento ?? $existing->numero_documento_carico,
            'data_carico' => $art->data_documento ?? $existing->data_carico,
            'in_vetrina' => (bool) ($art->vetrina ?? false),
            'foto_principale' => $art->foto_url ?? $existing->foto_principale,
            'caratteristiche' => json_encode([
                'marca' => $art->marca ?? null,
                'referenza' => $art->referenza ?? null,
            ]),
            'updated_at' => now(),
        ]);
    }

    private function insertArticoloFromRow(object $art): void
    {
        if ($this->dryRun) {
            return;
        }

        $codiceBase = ($art->id_magazzino ?? '0') . '-' . ($art->carico ?? $art->id);
        $codiceUnico = $this->generateUniqueCodice($codiceBase);
        $descrizione = trim((string) ($art->descrizione ?? ''));
        if ($descrizione === '') {
            $descrizione = 'Articolo ' . $art->id;
        }

        $sedeId = 1;
        if (property_exists($art, 'ubicazione_magazzino')) {
            $sedeId = $this->ubicazioneToSedeMapping[$art->ubicazione_magazzino ?? 0] ?? 1;
        }

        $fornitoreId = $this->resolveFornitoreIdFromArticolo($art);

        try {
            DB::table('articoli')->insert([
                'id' => $art->id,
                'codice' => $codiceUnico,
                'descrizione' => $descrizione,
                'descrizione_estesa' => $art->note ?? null,
                'categoria_merceologica_id' => $art->id_magazzino ?? null,
                'sede_id' => $sedeId,
                'fornitore_id' => $fornitoreId,
                'materiale' => $art->materiale ?? null,
                'colore' => $art->colore ?? null,
                'peso_lordo' => $art->peso_lordo ?? null,
                'peso_netto' => $art->peso_netto ?? null,
                'titolo' => $art->oro ?? null,
                'caratura' => $art->carati ?? null,
                'prezzo_acquisto' => $art->costo_unitario ?? 0,
                'prezzo_fornitore' => $art->prezzo_fornitore ?? null,
                'stato_articolo' => 'disponibile',
                'tipo_carico' => isset($art->fatturato) && $art->fatturato == 1 ? 'fattura' : 'ddt',
                'numero_documento_carico' => $art->numero_documento ?? null,
                'data_carico' => $art->data_documento ?? null,
                'in_vetrina' => (bool) ($art->vetrina ?? false),
                'foto_principale' => $art->foto_url ?? null,
                'caratteristiche' => json_encode([
                    'marca' => $art->marca ?? null,
                    'referenza' => $art->referenza ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $exists = DB::table('articoli')->where('id', $art->id)->exists();
            if (!$exists) {
                throw new \RuntimeException("Insert articolo {$art->id} non riuscito");
            }

        // Giacenze create in un passaggio dedicato (backfill)
        } catch (\Throwable $e) {
            throw new \RuntimeException("Insert articolo {$art->id} fallito: " . $e->getMessage(), 0, $e);
        }
    }

    private function generateUniqueCodice(string $base): string
    {
        if (!$this->codiceExists($base)) {
            $this->codiciUsati[$base] = true;
            return $base;
        }

        $code = $base;
        $counter = $this->codiceCounters[$base] ?? 1;

        while ($this->codiceExists($code)) {
            $counter++;
            $code = $base . '-' . $counter;
        }

        $this->codiceCounters[$base] = $counter;
        $this->codiciUsati[$code] = true;

        return $code;
    }

    private function codiceExists(string $code): bool
    {
        if (isset($this->codiciUsati[$code])) {
            return true;
        }
        return DB::table('articoli')->where('codice', $code)->exists();
    }

    private function displaySummary(): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 RIEPILOGO MIGRAZIONE DELTA');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Fornitori: {$this->stats['fornitori']}");
        $this->line("Articoli: {$this->stats['articoli']}");
        $this->line("Giacenze: {$this->stats['giacenze']}");
        $this->line("DDT: {$this->stats['ddt']}");
        $this->line("Dettagli DDT: {$this->stats['ddt_dettagli']}");
        $this->line("Dettagli DDT saltati: {$this->stats['ddt_dettagli_skipped']}");
        $this->line("Fatture: {$this->stats['fatture']}");
        $this->line("Dettagli Fatture: {$this->stats['fatture_dettagli']}");
        $this->line("Dettagli Fatture saltati: {$this->stats['fatture_dettagli_skipped']}");
        if ($this->stats['errori'] > 0) {
            $this->warn("Errori: {$this->stats['errori']}");
        }
        $this->newLine();
        if ($this->dryRun) {
            $this->warn('✅ DRY-RUN completato. Rimuovi --dry-run per applicare.');
        }
    }
}
