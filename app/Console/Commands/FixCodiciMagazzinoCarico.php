<?php

namespace App\Console\Commands;

use App\Models\CaricoDettaglio;
use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
use App\Models\ValueObjects\CodiceArticolo;
use App\Services\CodiceService;
use Illuminate\Console\Command;

class FixCodiciMagazzinoCarico extends Command
{
    protected $signature = 'articoli:fix-codici-magazzino {--ddt_id=} {--fattura_id=} {--start-from= : Forza numerazione da questo carico} {--dry-run : Mostra cosa verrebbe fatto senza salvare}';
    protected $description = 'Corregge codici articolo errati rispetto al magazzino per un carico specifico';

    public function handle(): int
    {
        $ddtId = $this->option('ddt_id');
        $fatturaId = $this->option('fattura_id');
        $dryRun = (bool) $this->option('dry-run');
        $startFrom = $this->option('start-from');
        $startFrom = is_null($startFrom) ? null : (int) $startFrom;

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
        $batchIds = $righe->pluck('articolo_id')->filter()->unique()->values()->all();
        $fixed = 0;

        foreach ($righe as $riga) {
            $articolo = $riga->articolo;
            if (!$articolo || !$articolo->categoria_merceologica_id) {
                continue;
            }

            try {
                $codiceBase = $articolo->codice_base ?: $articolo->codice;
                $codiceVO = CodiceArticolo::fromString($codiceBase);
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $prefisso = $codiceVO->getMagazzinoId();
            $magazzino = $this->resolveMagazzinoCode((int) $articolo->categoria_merceologica_id);

            if ($prefisso === $magazzino) {
                continue;
            }

            if (!isset($nextByMagazzino[$magazzino])) {
                $nextByMagazzino[$magazzino] = $startFrom
                    ? $startFrom
                    : $codiceService->prossimoCodiceDisponibile($magazzino)->getCarico();
            }

            $nuovoCodice = $this->getNextAvailableCodice($magazzino, $nextByMagazzino[$magazzino], $batchIds);
            $nextByMagazzino[$magazzino] = (int) substr($nuovoCodice, strpos($nuovoCodice, '-') + 1) + 1;

            $this->line("{$articolo->id}: {$articolo->codice} -> {$nuovoCodice} (magazzino {$magazzino})");
            $fixed++;

            if (!$dryRun) {
                $articolo->update(['codice' => $nuovoCodice]);
            }
        }

        $this->info("Articoli corretti: {$fixed}");

        return 0;
    }

    private function getNextAvailableCodice(int $magazzino, int $carico, array $batchIds): string
    {
        while (true) {
            $codice = $magazzino . '-' . $carico;
            $exists = Articolo::where('codice', $codice)
                ->whereNotIn('id', $batchIds)
                ->exists();
            if (!$exists) {
                return $codice;
            }
            $carico++;
        }
    }

    private function resolveMagazzinoCode(int $categoriaId): int
    {
        $categoria = CategoriaMerceologica::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->find($categoriaId);

        if (!$categoria) {
            return $categoriaId;
        }

        $codice = trim((string) $categoria->codice);
        $nome = trim((string) $categoria->nome);

        if ($codice !== '' && ctype_digit($codice)) {
            return (int) $codice;
        }

        if ($codice !== '' && preg_match('/(?:MAG|MAGAZZINO)\s*([0-9]+)/i', $codice, $matches)) {
            return (int) $matches[1];
        }

        if ($nome !== '' && preg_match('/MAGAZZINO\s*([0-9]+)/i', $nome, $matches)) {
            return (int) $matches[1];
        }

        return $categoriaId;
    }
}
