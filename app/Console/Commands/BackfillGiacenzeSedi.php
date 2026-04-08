<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Articolo;

class BackfillGiacenzeSedi extends Command
{
    protected $signature = 'giacenze:backfill {--dry-run : Mostra cosa verrebbe fatto senza modificare}';
    protected $description = 'DEPRECATO: giacenze_sedi non e piu una fonte di verita';

    public function handle(): int
    {
        $count = 0;
        $candidati = 0;

        $this->warn('Comando deprecato: giacenze_sedi e dismessa come fonte dati operativa.');
        $this->warn('Usare giacenze come unica fonte di verita.');

        // Manteniamo solo una scansione informativa in read-only.
        Articolo::with(['giacenza'])
            ->chunk(500, function($articoli) use (&$count, &$candidati){
                foreach ($articoli as $a) {
                    $count++;
                    if ($a->sede_id) {
                        $candidati++;
                    }
                }
            });

        $this->info("Articoli scansionati: {$count}");
        $this->info("Righe potenziali ex-giacenze_sedi: {$candidati}");

        return self::SUCCESS;
    }
}





