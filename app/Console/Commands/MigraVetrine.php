<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Vetrina;
use App\Models\ArticoloVetrina;
use App\Models\Articolo;
use App\Models\CategoriaMerceologica;

class MigraVetrine extends Command
{
    protected $signature = 'migra:vetrine {--test : Modalità test senza salvare}';
    protected $description = 'Migra dati storici vetrine dal database di produzione';

    private $mssqlConnection;
    private $testMode = false;
    private $stats = [
        'vetrine_migrate' => 0,
        'articoli_vetrine_migrate' => 0,
        'errori' => 0,
    ];

    public function handle()
    {
        $this->testMode = $this->option('test');
        
        if ($this->testMode) {
            $this->info('🧪 MODALITÀ TEST - Nessun dato verrà salvato');
        }

        try {
            // Connessione al database MSSQL di produzione
            $this->mssqlConnection = DB::connection('mssql_prod');
            $this->info('✅ Connesso al database MSSQL di produzione');

            // Migra vetrine
            $this->migraVetrine();
            
            // Migra articoli in vetrina
            $this->migraArticoliVetrine();

            // Statistiche finali
            $this->info('');
            $this->info('📊 STATISTICHE MIGRAZIONE:');
            $this->info("   Vetrine migrate: {$this->stats['vetrine_migrate']}");
            $this->info("   Articoli vetrine migrate: {$this->stats['articoli_vetrine_migrate']}");
            $this->info("   Errori: {$this->stats['errori']}");

        } catch (\Exception $e) {
            $this->error('❌ Errore durante la migrazione: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function migraVetrine()
    {
        $this->info('🏪 Migrazione vetrine...');

        // Query per ottenere le vetrine dal database MSSQL
        $vetrineMssql = $this->mssqlConnection
            ->table('mag_vetrine')
            ->get();

        if ($vetrineMssql->isEmpty()) {
            $this->warn('⚠️  Nessuna vetrina trovata nel database MSSQL');
            return;
        }

        $this->info("   Trovate {$vetrineMssql->count()} vetrine da migrare");

        foreach ($vetrineMssql as $vetrinaMssql) {
            try {
                // Mappa i campi dal database MSSQL
                $datiVetrina = [
                    'id' => $vetrinaMssql->id,
                    'codice' => 'VET' . str_pad($vetrinaMssql->id, 3, '0', STR_PAD_LEFT),
                    'nome' => $vetrinaMssql->nome ?? 'Vetrina ' . $vetrinaMssql->id,
                    'tipologia' => strtolower($vetrinaMssql->tipologia ?? 'gioielleria'), // Usa tipologia dal DB
                    'sede_id' => null,
                    'attiva' => true, // Assumiamo tutte attive
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (!$this->testMode) {
                    // Usa insertOrIgnore per evitare duplicati
                    DB::table('vetrine')->insertOrIgnore($datiVetrina);
                }

                $this->stats['vetrine_migrate']++;
                $this->line("   ✅ Vetrina {$datiVetrina['codice']} migrata");

            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("   ❌ Errore vetrina ID {$vetrinaMssql->id}: " . $e->getMessage());
            }
        }
    }

    private function migraArticoliVetrine()
    {
        $this->info('📦 Migrazione articoli in vetrina...');

        // Query per ottenere gli articoli in vetrina dal database MSSQL
        $articoliVetrineMssql = $this->mssqlConnection
            ->table('mag_articoli_vetrine')
            ->get();

        if ($articoliVetrineMssql->isEmpty()) {
            $this->warn('⚠️  Nessun articolo in vetrina trovato nel database MSSQL');
            return;
        }

        $this->info("   Trovati {$articoliVetrineMssql->count()} articoli in vetrina da migrare");

        foreach ($articoliVetrineMssql as $articoloVetrinaMssql) {
            try {
                // Verifica che l'articolo esista nel nuovo sistema
                $articoloEsiste = Articolo::where('id', $articoloVetrinaMssql->id_articolo)->exists();
                if (!$articoloEsiste) {
                    $this->warn("   ⚠️  Articolo ID {$articoloVetrinaMssql->id_articolo} non trovato, saltato");
                    continue;
                }

                // Verifica che la vetrina esista nel nuovo sistema
                $vetrinaEsiste = Vetrina::where('id', $articoloVetrinaMssql->id_vetrina)->exists();
                if (!$vetrinaEsiste) {
                    $this->warn("   ⚠️  Vetrina ID {$articoloVetrinaMssql->id_vetrina} non trovata, saltato");
                    continue;
                }

                // Mappa i campi dal database MSSQL
                $datiArticoloVetrina = [
                    'id' => $articoloVetrinaMssql->id,
                    'vetrina_id' => $articoloVetrinaMssql->id_vetrina,
                    'articolo_id' => $articoloVetrinaMssql->id_articolo,
                    'prezzo_vetrina' => $this->parsePrezzo($articoloVetrinaMssql->prezzo_vetrina),
                    'testo_vetrina' => $articoloVetrinaMssql->testo_vetrina ?? '',
                    'posizione' => $articoloVetrinaMssql->ordine_vetrina ?? 0,
                    'ripiano' => null, // Non presente nel DB MSSQL
                    'data_inserimento' => now()->toDateString(), // Non presente nel DB MSSQL
                    'data_rimozione' => null, // Assumiamo tutti attivi
                    'giorni_esposizione' => null,
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (!$this->testMode) {
                    // Usa insertOrIgnore per evitare duplicati
                    DB::table('articoli_vetrine')->insertOrIgnore($datiArticoloVetrina);
                    
                    // Aggiorna l'ultimo testo vetrina nell'articolo se presente
                    if ($datiArticoloVetrina['testo_vetrina']) {
                        Articolo::where('id', $articoloVetrinaMssql->id_articolo)
                            ->update(['ultimo_testo_vetrina' => $datiArticoloVetrina['testo_vetrina']]);
                    }
                }

                $this->stats['articoli_vetrine_migrate']++;
                $this->line("   ✅ Articolo {$articoloVetrinaMssql->id_articolo} in vetrina {$articoloVetrinaMssql->id_vetrina} migrato");

            } catch (\Exception $e) {
                $this->stats['errori']++;
                $this->error("   ❌ Errore articolo vetrina ID {$articoloVetrinaMssql->id}: " . $e->getMessage());
            }
        }
    }

    private function parsePrezzo($prezzoString)
    {
        if (empty($prezzoString)) {
            return 0;
        }

        // Rimuovi caratteri non numerici eccetto punto e virgola
        $prezzo = preg_replace('/[^0-9.,]/', '', $prezzoString);
        
        // Sostituisci virgola con punto per decimali
        $prezzo = str_replace(',', '.', $prezzo);
        
        return floatval($prezzo);
    }
}