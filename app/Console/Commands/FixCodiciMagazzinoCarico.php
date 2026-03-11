<?php

namespace App\Console\Commands;

use App\Models\CaricoDettaglio;
use App\Models\Articolo;
use App\Models\ValueObjects\CodiceArticolo;
use App\Services\CodiceService;
use Illuminate\Console\Command;

class FixCodiciMagazzinoCarico extends Command
{
    protected $signature = 'articoli:fix-codici-magazzino {--ddt_id=} {--fattura_id=} {--dry-run : Mostra cosa verrebbe fatto senza salvare}';
    protected $description = 'Corregge codici articolo errati rispetto al magazzino per un carico specifico';

    public function handle(): int
    {
        $ddtId = $this->option('ddt_id');
        $fatturaId = $this->option('fattura_id');
        $dryRun = (bool) $this->option('dry-run');

        if (!$ddtId && !$fatturaId) {
            $this->error('Specifica --ddt_id o --fattura_id');
            return 1;
        }

        $righe = CaricoDettaglio::with('articolo')
            ->when($ddtId, fn ($q) => $q->where('ddt_id', $ddtId))
            ->when($fatturaId, fn ($q) => $q->where('fattura_id', $fatturaId))
            ->get();

        if ($righe->isEmpty()) {
            $this->warn('Nessuna riga trovata per il carico indicato.');
            return 0;
        }

        $codiceService = app(CodiceService::class);
        $nextByMagazzino = [];
        $fixed = 0;

        foreach ($righe as $riga) {
            $articolo = $riga->articolo;
            if (!$articolo || !$articolo->categoria_merceologica_id) {
                continue;
            }

            try {
                $codiceVO = CodiceArticolo::fromString($articolo->codice);
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $prefisso = $codiceVO->getMagazzinoId();
            $magazzino = (int) $articolo->categoria_merceologica_id;

            if ($prefisso === $magazzino) {
                continue;
            }

            if (!isset($nextByMagazzino[$magazzino])) {
                $nextByMagazzino[$magazzino] = $codiceService->prossimoCodiceDisponibile($magazzino)->getCarico();
            }
            $nuovoCodice = $magazzino . '-' . $nextByMagazzino[$magazzino];
            $nextByMagazzino[$magazzino]++;

            $this->line("{$articolo->id}: {$articolo->codice} -> {$nuovoCodice} (magazzino {$magazzino})");
            $fixed++;

            if (!$dryRun) {
                $articolo->update(['codice' => $nuovoCodice]);
            }
        }

        $this->info("Articoli corretti: {$fixed}");

        return 0;
    }
}
