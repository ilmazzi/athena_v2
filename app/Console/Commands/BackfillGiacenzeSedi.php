<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Articolo;
use App\Models\GiacenzaSede;

class BackfillGiacenzeSedi extends Command
{
    protected $signature = 'giacenze:backfill {--dry-run : Mostra cosa verrebbe fatto senza modificare}';
    protected $description = 'Popola la tabella giacenze_sedi a partire dalle giacenze attuali degli articoli';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $count = 0; $updated = 0;

        $this->info('Avvio backfill giacenze_sedi...');
        Articolo::with(['giacenza'])
            ->chunk(500, function($articoli) use (&$count, &$updated, $dry){
                foreach ($articoli as $a) {
                    $count++;
                    $q = (int) ($a->giacenza->quantita ?? 0);
                    $qr = (int) ($a->giacenza->quantita_residua ?? 0);
                    if ($a->sede_id) {
                        if (!$dry) {
                            GiacenzaSede::updateOrCreate(
                                ['articolo_id' => $a->id, 'sede_id' => $a->sede_id],
                                ['quantita' => $q, 'quantita_residua' => $qr]
                            );
                        }
                        $updated++;
                    }
                }
            });

        $this->info("Articoli scansionati: {$count}");
        $this->info(($dry ? 'Verrebbero aggiornate' : 'Aggiornate') . " {$updated} righe in giacenze_sedi");

        return 0;
    }
}





