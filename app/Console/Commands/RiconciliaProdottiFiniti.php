<?php

namespace App\Console\Commands;

use App\Models\Articolo;
use App\Models\ProdottoFinito;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RiconciliaProdottiFiniti extends Command
{
    protected $signature = 'produzione:riconcilia-pf {--dry-run : Mostra cosa verrebbe fatto senza salvare}';
    protected $description = 'Riconcilia prodotti finiti con articoli (codice 9-x/22-x) usando MSSQL';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY-RUN: nessuna modifica verrà salvata');
        }

        $prodottiMssql = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->whereIn('id_magazzino', [9, 22])
            ->get();

        $this->info("Trovati {$prodottiMssql->count()} prodotti finiti in MSSQL");

        $bar = $this->output->createProgressBar($prodottiMssql->count());
        $bar->start();

        foreach ($prodottiMssql as $pf) {
            $codice = $pf->id_magazzino . '-' . $pf->carico;

            $articolo = Articolo::withTrashed()->find($pf->id);
            if ($articolo && $articolo->trashed() && !$dryRun) {
                $articolo->restore();
            }

            $prodottoFinito = ProdottoFinito::withTrashed()->find($pf->id_pf);
            if ($prodottoFinito && $prodottoFinito->trashed() && !$dryRun) {
                $prodottoFinito->restore();
            }

            if (!$prodottoFinito) {
                if (!$dryRun) {
                    DB::table('prodotti_finiti')->insert([
                        'id' => $pf->id_pf,
                        'codice' => $codice,
                        'descrizione' => $pf->descrizione,
                        'tipologia' => $pf->id_magazzino == 9 ? 'prodotto_finito' : 'semilavorato',
                        'magazzino_id' => $pf->id_magazzino,
                        'costo_totale' => $pf->valore_magazzino ?? 0,
                        'stato' => 'completato',
                        'data_completamento' => $pf->data_documento ?? now(),
                        'note' => "Riconciliato da MSSQL - ID articolo: {$pf->id}, ID PF: {$pf->id_pf}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $prodottoFinito = ProdottoFinito::find($pf->id_pf);
                }
            }

            if ($prodottoFinito && !$dryRun) {
                $prodottoFinito->update([
                    'codice' => $codice,
                    'magazzino_id' => $pf->id_magazzino,
                    'descrizione' => $pf->descrizione,
                    'tipologia' => $pf->id_magazzino == 9 ? 'prodotto_finito' : 'semilavorato',
                ]);
            }

            if ($articolo && !$dryRun) {
                $articolo->update([
                    'prodotto_finito_id' => $pf->id_pf,
                ]);
            }

            if ($prodottoFinito && $articolo && !$dryRun) {
                $prodottoFinito->update([
                    'articolo_risultante_id' => $articolo->id,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Riconciliazione completata');

        return 0;
    }
}
