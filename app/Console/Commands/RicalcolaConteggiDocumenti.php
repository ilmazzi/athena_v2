<?php

namespace App\Console\Commands;

use App\Models\Ddt;
use App\Models\Fattura;
use App\Models\DdtDettaglio;
use App\Models\FatturaDettaglio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RicalcolaConteggiDocumenti extends Command
{
    protected $signature = 'documenti:ricalcola-conteggi 
                            {--tipo=all : Tipo di documento (ddt|fatture|all)}
                            {--aggiorna-meta : Aggiorna sede e categoria dai dettagli}';

    protected $description = 'Ricalcola numero_articoli e quantita_totale per tutti i documenti';

    public function handle()
    {
        $tipo = $this->option('tipo');
        $aggiornaMeta = (bool) $this->option('aggiorna-meta');

        if ($tipo === 'all' || $tipo === 'ddt') {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📦 RICALCOLO CONTEGGI DDT');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->ricalcolaDdt($aggiornaMeta);
        }

        if ($tipo === 'all' || $tipo === 'fatture') {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🧾 RICALCOLO CONTEGGI FATTURE');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->ricalcolaFatture($aggiornaMeta);
        }

        $this->newLine();
        $this->info('✅ Ricalcolo completato!');
    }

    private function ricalcolaDdt(bool $aggiornaMeta)
    {
        $ddt = Ddt::all();
        $this->info("Trovati {$ddt->count()} DDT da processare...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($ddt->count());
        $progressBar->start();

        $aggiornati = 0;

        foreach ($ddt as $documento) {
            // Conta articoli unici dai dettagli
            $numeroArticoli = DdtDettaglio::where('ddt_id', $documento->id)
                ->distinct('articolo_id')
                ->count('articolo_id');
            
            // Somma quantità totale
            $quantitaTotale = DdtDettaglio::where('ddt_id', $documento->id)
                ->sum('quantita');

            $updateData = [
                'numero_articoli' => $numeroArticoli,
                'quantita_totale' => $quantitaTotale,
            ];

            if ($aggiornaMeta) {
                $meta = DdtDettaglio::where('ddt_id', $documento->id)
                    ->whereNotNull('ddt_dettagli.articolo_id')
                    ->join('articoli', 'ddt_dettagli.articolo_id', '=', 'articoli.id')
                    ->selectRaw('articoli.sede_id as sede_id, articoli.categoria_merceologica_id as categoria_id, COUNT(*) as c')
                    ->groupBy('articoli.sede_id', 'articoli.categoria_merceologica_id')
                    ->orderByDesc('c')
                    ->first();

                if ($meta) {
                    $updateData['sede_id'] = $meta->sede_id;
                    $updateData['categoria_id'] = $meta->categoria_id;
                }
            }

            $hasChanges = $documento->numero_articoli != $numeroArticoli
                || $documento->quantita_totale != $quantitaTotale;

            if ($aggiornaMeta && array_key_exists('sede_id', $updateData)) {
                $hasChanges = $hasChanges
                    || (int) $documento->sede_id !== (int) $updateData['sede_id']
                    || (int) $documento->categoria_id !== (int) $updateData['categoria_id'];
            }

            if ($hasChanges) {
                DB::table('ddt')
                    ->where('id', $documento->id)
                    ->update($updateData);
                $aggiornati++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("✅ Aggiornati {$aggiornati} DDT su {$ddt->count()}");
    }

    private function ricalcolaFatture(bool $aggiornaMeta)
    {
        $fatture = Fattura::all();
        $this->info("Trovate {$fatture->count()} Fatture da processare...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($fatture->count());
        $progressBar->start();

        $aggiornati = 0;

        foreach ($fatture as $documento) {
            // Conta articoli unici dai dettagli
            $numeroArticoli = FatturaDettaglio::where('fattura_id', $documento->id)
                ->distinct('articolo_id')
                ->count('articolo_id');
            
            // Somma quantità totale
            $quantitaTotale = FatturaDettaglio::where('fattura_id', $documento->id)
                ->sum('quantita');

            $updateData = [
                'numero_articoli' => $numeroArticoli,
                'quantita_totale' => $quantitaTotale,
            ];

            if ($aggiornaMeta) {
                $meta = FatturaDettaglio::where('fattura_id', $documento->id)
                    ->whereNotNull('fatture_dettagli.articolo_id')
                    ->join('articoli', 'fatture_dettagli.articolo_id', '=', 'articoli.id')
                    ->selectRaw('articoli.sede_id as sede_id, articoli.categoria_merceologica_id as categoria_id, COUNT(*) as c')
                    ->groupBy('articoli.sede_id', 'articoli.categoria_merceologica_id')
                    ->orderByDesc('c')
                    ->first();

                if ($meta) {
                    $updateData['sede_id'] = $meta->sede_id;
                    $updateData['categoria_id'] = $meta->categoria_id;
                }
            }

            $hasChanges = $documento->numero_articoli != $numeroArticoli
                || $documento->quantita_totale != $quantitaTotale;

            if ($aggiornaMeta && array_key_exists('sede_id', $updateData)) {
                $hasChanges = $hasChanges
                    || (int) $documento->sede_id !== (int) $updateData['sede_id']
                    || (int) $documento->categoria_id !== (int) $updateData['categoria_id'];
            }

            if ($hasChanges) {
                DB::table('fatture')
                    ->where('id', $documento->id)
                    ->update($updateData);
                $aggiornati++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("✅ Aggiornate {$aggiornati} Fatture su {$fatture->count()}");
    }
}
