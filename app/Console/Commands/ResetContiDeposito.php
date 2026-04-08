<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Articolo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetContiDeposito extends Command
{
    protected $signature = 'deposito:reset {--yes : Esegui senza conferma} {--hard : Resetta anche auto-increment}';
    protected $description = 'Pulisce completamente conti deposito, movimenti, DDT deposito e notifiche correlate. Azzera i campi deposito su articoli e prodotti finiti.';

    public function handle(): int
    {
        if (!$this->option('yes')) {
            if (!$this->confirm('Sei sicuro di voler ELIMINARE TUTTI i dati dei conti deposito? Operazione irreversibile.')) {
                $this->warn('Operazione annullata.');
                return 0;
            }
        }

        $hard = $this->option('hard');

        try {
            // Disabilita constraints per velocità/sicurezza
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Prepara mapping magazzini e sedi
            $magazziniRecords = DB::table('categorie_merceologiche')
                ->select('id', 'codice', 'sede_id')
                ->get();

            $magazziniSedi = [];
            $magazziniPerCodice = [];
            $magazziniCdIds = [];

            foreach ($magazziniRecords as $mag) {
                $magazziniSedi[$mag->id] = $mag->sede_id;
                $codeKey = Str::upper((string) $mag->codice);
                if (!isset($magazziniPerCodice[$codeKey])) {
                    $magazziniPerCodice[$codeKey] = [
                        'id' => $mag->id,
                        'sede_id' => $mag->sede_id,
                    ];
                }
                if (Str::startsWith($codeKey, 'CD-')) {
                    $magazziniCdIds[] = $mag->id;
                }
            }

            $magazziniCdIds = array_unique($magazziniCdIds);

            // Mappa degli articoli -> magazzino originale recuperato dai movimenti di invio
            $magazziniOriginali = [];
            if (DB::getSchemaBuilder()->hasTable('movimenti_deposito')) {
                $movimentiInvio = DB::table('movimenti_deposito')
                    ->select('articolo_id', 'dettagli')
                    ->whereNotNull('articolo_id')
                    ->where('tipo_movimento', 'invio')
                    ->orderBy('id')
                    ->cursor();

                foreach ($movimentiInvio as $movimento) {
                    if (!$movimento->dettagli) {
                        continue;
                    }

                    $dettagli = is_array($movimento->dettagli)
                        ? $movimento->dettagli
                        : json_decode($movimento->dettagli, true);

                    if (!empty($dettagli['magazzino_originale_id'])) {
                        $magazziniOriginali[$movimento->articolo_id] = (int) $dettagli['magazzino_originale_id'];
                    }
                }
            }

            // Rimuovi notifiche correlate
            try {
                DB::table('notifiche')
                    ->whereIn('tipo', ['reso', 'vendita', 'scadenza', 'deposito_scaduto'])
                    ->delete();
                $this->info('Notifiche rimosse.');
            } catch (\Throwable $e) {
                $this->warn('Tabella notifiche non presente o schema differente. Proseguo.');
            }

            // Sgancia movimenti collegati ai DDT standard
            if (DB::getSchemaBuilder()->hasColumn('movimenti_deposito', 'ddt_id')) {
                $updates = ['ddt_id' => null];
                if (DB::getSchemaBuilder()->hasColumn('movimenti_deposito', 'fattura_id')) {
                    $updates['fattura_id'] = null;
                }
                if (DB::getSchemaBuilder()->hasColumn('movimenti_deposito', 'proforma_id')) {
                    $updates['proforma_id'] = null;
                }
                DB::table('movimenti_deposito')->update($updates);
            }

            // Svuota dettagli DDT deposito
            if (DB::getSchemaBuilder()->hasTable('ddt_deposito_dettagli')) {
                DB::table('ddt_deposito_dettagli')->delete();
                if ($hard) DB::statement('ALTER TABLE ddt_deposito_dettagli AUTO_INCREMENT = 1');
            }

            // Svuota DDT deposito
            if (DB::getSchemaBuilder()->hasTable('ddt_depositi')) {
                DB::table('ddt_depositi')->delete();
                if ($hard) DB::statement('ALTER TABLE ddt_depositi AUTO_INCREMENT = 1');
            }

            // Svuota movimenti deposito
            if (DB::getSchemaBuilder()->hasTable('movimenti_deposito')) {
                DB::table('movimenti_deposito')->delete();
                if ($hard) DB::statement('ALTER TABLE movimenti_deposito AUTO_INCREMENT = 1');
            }

            // Svuota proforme deposito correlate
            if (DB::getSchemaBuilder()->hasTable('proforme_deposito')) {
                DB::table('proforme_deposito')->delete();
                if ($hard) DB::statement('ALTER TABLE proforme_deposito AUTO_INCREMENT = 1');
                $this->info('Proforme deposito eliminate.');
            }

            // Svuota conti deposito
            if (DB::getSchemaBuilder()->hasTable('conti_deposito')) {
                DB::table('conti_deposito')->delete();
                if ($hard) DB::statement('ALTER TABLE conti_deposito AUTO_INCREMENT = 1');
            }

            // Azzera riferimenti su articoli
            if (DB::getSchemaBuilder()->hasTable('articoli')) {
                $hasFlagInDeposito = DB::getSchemaBuilder()->hasColumn('articoli', 'in_conto_deposito');

                $articoliDaRipristinare = DB::table('articoli')
                    ->select('id', 'codice', 'categoria_merceologica_id')
                    ->where(function ($query) use ($magazziniCdIds) {
                        $query->whereNotNull('conto_deposito_corrente_id')
                              ->orWhere('quantita_in_deposito', '>', 0);

                        if (!empty($magazziniCdIds)) {
                            $query->orWhereIn('categoria_merceologica_id', $magazziniCdIds);
                        }
                    })
                    ->get();

                $ripristinati = 0;

                foreach ($articoliDaRipristinare as $articolo) {
                    $updates = [
                        'conto_deposito_corrente_id' => null,
                        'quantita_in_deposito' => 0,
                    ];

                    if ($hasFlagInDeposito) {
                        $updates['in_conto_deposito'] = 0;
                    }

                    $categoriaTargetId = $magazziniOriginali[$articolo->id] ?? null;
                    $sedeTargetId = $categoriaTargetId ? ($magazziniSedi[$categoriaTargetId] ?? null) : null;

                    if (!$categoriaTargetId) {
                        if (preg_match('/^([0-9A-Za-z]+)/', (string) $articolo->codice, $match)) {
                            $prefix = Str::upper($match[1]);
                            if (isset($magazziniPerCodice[$prefix])) {
                                $categoriaTargetId = $magazziniPerCodice[$prefix]['id'];
                                $sedeTargetId = $magazziniPerCodice[$prefix]['sede_id'];
                            }
                        }
                    }

                    if ($categoriaTargetId) {
                        $updates['categoria_merceologica_id'] = $categoriaTargetId;
                        if ($sedeTargetId) {
                            $updates['sede_id'] = $sedeTargetId;
                        }
                    }

                    DB::table('articoli')
                        ->where('id', $articolo->id)
                        ->update($updates);

                    $ripristinati++;
                }

                $this->info("Articoli ripuliti dai riferimenti al deposito ({$ripristinati} ripristinati).");
            }

            // Azzera riferimenti su prodotti finiti
            if (DB::getSchemaBuilder()->hasTable('prodotti_finiti')) {
                $updatesPf = [
                    'conto_deposito_corrente_id' => null,
                ];
                if (DB::getSchemaBuilder()->hasColumn('prodotti_finiti', 'in_conto_deposito')) {
                    $updatesPf['in_conto_deposito'] = 0;
                }
                DB::table('prodotti_finiti')->update($updatesPf);
                $this->info('Prodotti finiti ripuliti dai riferimenti al deposito.');
            }

            // giacenze_sedi deprecata: nessuna scrittura su questa tabella.
            if (DB::getSchemaBuilder()->hasTable('giacenze_sedi')) {
                $this->warn('Skip reset/rigenerazione giacenze_sedi: tabella deprecata (fonte unica = giacenze).');
            }

            // Riabilita constraints
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->info('✅ Pulizia completata. Sistema conti deposito azzerato.');
            if ($hard) {
                $this->info('ℹ️ Eseguito anche reset AUTO_INCREMENT.');
            }
            return 0;
        } catch (\Throwable $e) {
            // Assicura riabilitazione FK anche in errore
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $ignore) {}
            $this->error('❌ Errore durante la pulizia: ' . $e->getMessage());
            return 1;
        }
    }
}


