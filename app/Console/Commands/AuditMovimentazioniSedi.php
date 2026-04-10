<?php

namespace App\Console\Commands;

use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\Movimentazione;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditMovimentazioniSedi extends Command
{
    protected $signature = 'audit:movimentazioni-sedi';

    protected $description = 'Audit coerenza movimenti sede/articolo/giacenza dopo il refactor movimentazioni interne';

    public function handle(): int
    {
        $timestamp = now()->format('Ymd_His');
        $directory = 'audit_movimentazioni_sedi';

        $articoli = Articolo::withoutGlobalScopes()
            ->with(['giacenze' => function ($query) {
                $query->orderByDesc('quantita_residua')->orderByDesc('quantita');
            }])
            ->get();

        $sedeMismatch = [];
        $magazzinoMismatch = [];
        $categoriaMismatch = [];
        $duplicateGiacenze = [];

        foreach ($articoli as $articolo) {
            $giacenze = $articolo->giacenze;
            $giacenzaAttiva = $giacenze->first();

            if ($giacenze->count() > 1) {
                $duplicateGiacenze[] = [
                    'articolo_id' => $articolo->id,
                    'codice' => $articolo->codice,
                    'giacenze_count' => $giacenze->count(),
                    'sede_articolo' => $articolo->sede_id,
                ];
            }

            if (!$giacenzaAttiva) {
                continue;
            }

            if ((int) $articolo->sede_id !== (int) $giacenzaAttiva->sede_id) {
                $sedeMismatch[] = [
                    'articolo_id' => $articolo->id,
                    'codice' => $articolo->codice,
                    'articolo_sede_id' => $articolo->sede_id,
                    'giacenza_sede_id' => $giacenzaAttiva->sede_id,
                    'giacenza_id' => $giacenzaAttiva->id,
                    'quantita_residua' => $giacenzaAttiva->quantita_residua,
                ];
            }

            if (!is_null($articolo->magazzino_logico) && !is_null($giacenzaAttiva->magazzino_logico)
                && (int) $articolo->magazzino_logico !== (int) $giacenzaAttiva->magazzino_logico) {
                $magazzinoMismatch[] = [
                    'articolo_id' => $articolo->id,
                    'codice' => $articolo->codice,
                    'articolo_magazzino_logico' => $articolo->magazzino_logico,
                    'giacenza_magazzino_logico' => $giacenzaAttiva->magazzino_logico,
                    'giacenza_id' => $giacenzaAttiva->id,
                ];
            }

            if (!is_null($articolo->categoria_merceologica_id) && !is_null($giacenzaAttiva->categoria_merceologica_id)
                && (int) $articolo->categoria_merceologica_id !== (int) $giacenzaAttiva->categoria_merceologica_id) {
                $categoriaMismatch[] = [
                    'articolo_id' => $articolo->id,
                    'codice' => $articolo->codice,
                    'articolo_categoria_id' => $articolo->categoria_merceologica_id,
                    'giacenza_categoria_id' => $giacenzaAttiva->categoria_merceologica_id,
                    'giacenza_id' => $giacenzaAttiva->id,
                ];
            }
        }

        $movimentiCategoriaChanged = Movimentazione::query()
            ->whereColumn('magazzino_partenza_id', '!=', 'magazzino_destinazione_id')
            ->get([
                'id',
                'numero_documento',
                'magazzino_partenza_id',
                'magazzino_destinazione_id',
                'magazzino_logico_partenza',
                'magazzino_logico_destinazione',
                'data_movimentazione',
            ])
            ->map(fn ($movimento) => $movimento->toArray())
            ->all();

        $summary = [
            'articolo_sede_vs_giacenza_sede_mismatch' => count($sedeMismatch),
            'articolo_magazzino_vs_giacenza_magazzino_mismatch' => count($magazzinoMismatch),
            'articolo_categoria_vs_giacenza_categoria_mismatch' => count($categoriaMismatch),
            'articoli_con_giacenze_duplicate' => count($duplicateGiacenze),
            'movimentazioni_con_categoria_partenza_destinazione_diversa' => count($movimentiCategoriaChanged),
        ];

        Storage::makeDirectory($directory);
        Storage::put("{$directory}/summary_{$timestamp}.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->writeCsv("{$directory}/articolo_sede_mismatch_{$timestamp}.csv", $sedeMismatch);
        $this->writeCsv("{$directory}/articolo_magazzino_mismatch_{$timestamp}.csv", $magazzinoMismatch);
        $this->writeCsv("{$directory}/articolo_categoria_mismatch_{$timestamp}.csv", $categoriaMismatch);
        $this->writeCsv("{$directory}/giacenze_duplicate_{$timestamp}.csv", $duplicateGiacenze);
        $this->writeCsv("{$directory}/movimentazioni_categoria_changed_{$timestamp}.csv", $movimentiCategoriaChanged);

        $this->table(
            ['Check', 'Count'],
            [
                ['Articolo vs giacenza sede', $summary['articolo_sede_vs_giacenza_sede_mismatch']],
                ['Articolo vs giacenza magazzino', $summary['articolo_magazzino_vs_giacenza_magazzino_mismatch']],
                ['Articolo vs giacenza categoria', $summary['articolo_categoria_vs_giacenza_categoria_mismatch']],
                ['Articoli con giacenze duplicate', $summary['articoli_con_giacenze_duplicate']],
                ['Movimenti con categoria cambiata', $summary['movimentazioni_con_categoria_partenza_destinazione_diversa']],
            ]
        );

        $this->line('Summary: ' . storage_path("app/{$directory}/summary_{$timestamp}.json"));

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $rows): void
    {
        if (empty($rows)) {
            Storage::put($path, '');
            return;
        }

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        Storage::put($path, stream_get_contents($handle));
        fclose($handle);
    }
}
