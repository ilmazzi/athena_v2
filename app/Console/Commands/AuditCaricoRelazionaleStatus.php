<?php

namespace App\Console\Commands;

use App\Models\Articolo;
use Illuminate\Console\Command;

class AuditCaricoRelazionaleStatus extends Command
{
    protected $signature = 'audit:carico-relazionale-status
                            {--output-dir=audit_carico_relazionale_status : Cartella sotto storage/app per i report}
                            {--include-deleted : Include anche articoli soft-deleted}
                            {--only-status= : Filtra un singolo status nel CSV}';

    protected $description = 'Classifica gli articoli in base allo stato del carico relazionale vs legacy';

    public function handle(): int
    {
        $outputDir = trim((string) $this->option('output-dir'));
        $root = storage_path('app/' . ($outputDir !== '' ? $outputDir : 'audit_carico_relazionale_status'));

        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            $this->error('Impossibile creare directory report: ' . $root);
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $includeDeleted = (bool) $this->option('include-deleted');
        $onlyStatus = trim((string) $this->option('only-status'));

        $query = Articolo::query()
            ->withCount([
                'ddtDettaglio as ddt_dettagli_count',
                'fatturaDettaglio as fattura_dettagli_count',
                'caricoDettagli as carico_dettagli_count',
            ])
            ->orderBy('id');

        if (!$includeDeleted) {
            $query->whereNull('deleted_at');
        }

        $rows = [];
        $counts = [
            'pf_produzione_interna' => 0,
            'ok_relazionale' => 0,
            'relazionale_senza_carico_dettaglio' => 0,
            'legacy_con_relazione_parziale' => 0,
            'solo_legacy' => 0,
            'orphan' => 0,
        ];

        $query->chunkById(500, function ($chunk) use (&$rows, &$counts, $onlyStatus) {
            foreach ($chunk as $articolo) {
                $status = $this->classifyArticolo($articolo);
                $counts[$status] = ($counts[$status] ?? 0) + 1;

                if ($onlyStatus !== '' && $onlyStatus !== $status) {
                    continue;
                }

                $rows[] = [
                    'articolo_id' => $articolo->id,
                    'codice' => $articolo->codice,
                    'codice_base' => $articolo->codice_base,
                    'descrizione' => $articolo->descrizione,
                    'status' => $status,
                    'prodotto_finito_id' => $articolo->prodotto_finito_id,
                    'tipo_carico_legacy' => $articolo->tipo_carico,
                    'numero_documento_carico_legacy' => $articolo->numero_documento_carico,
                    'data_carico_legacy' => optional($articolo->data_carico)?->format('Y-m-d'),
                    'ddt_dettagli_count' => $articolo->ddt_dettagli_count,
                    'fattura_dettagli_count' => $articolo->fattura_dettagli_count,
                    'carico_dettagli_count' => $articolo->carico_dettagli_count,
                    'tipo_carico_effettivo' => $articolo->tipo_carico_effettivo,
                    'numero_documento_effettivo' => $articolo->numero_documento_carico_effettivo,
                    'data_carico_effettiva' => optional($articolo->data_carico_effettiva)?->format('Y-m-d'),
                    'fornitore_effettivo_id' => $articolo->fornitore_effettivo_id,
                    'deleted_at' => optional($articolo->deleted_at)?->format('Y-m-d H:i:s'),
                ];
            }
        }, 'id');

        $csvPath = $root . DIRECTORY_SEPARATOR . "audit_carico_relazionale_status_{$timestamp}.csv";
        $this->writeCsv($csvPath, $rows);

        $summary = [
            'generated_at' => now()->toIso8601String(),
            'counts' => $counts,
            'csv' => $csvPath,
            'filtered_status' => $onlyStatus !== '' ? $onlyStatus : null,
        ];

        $summaryPath = $root . DIRECTORY_SEPARATOR . "summary_{$timestamp}.json";
        file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->table(
            ['Status', 'Count'],
            collect($counts)
                ->map(fn ($count, $status) => ['status' => $status, 'count' => $count])
                ->values()
                ->all()
        );

        $this->line('CSV: ' . $csvPath);
        $this->line('Summary: ' . $summaryPath);

        return self::SUCCESS;
    }

    private function classifyArticolo(Articolo $articolo): string
    {
        $isPf = !empty($articolo->prodotto_finito_id)
            || $articolo->tipo_carico === 'produzione_interna';

        if ($isPf) {
            return 'pf_produzione_interna';
        }

        $hasLegacy = !empty($articolo->tipo_carico)
            || !empty($articolo->numero_documento_carico)
            || !empty($articolo->data_carico);

        $hasDocRel = ($articolo->ddt_dettagli_count ?? 0) > 0
            || ($articolo->fattura_dettagli_count ?? 0) > 0;

        $hasCaricoDet = ($articolo->carico_dettagli_count ?? 0) > 0;

        if ($hasDocRel && $hasCaricoDet) {
            return 'ok_relazionale';
        }

        if ($hasDocRel && !$hasCaricoDet) {
            return $hasLegacy
                ? 'legacy_con_relazione_parziale'
                : 'relazionale_senza_carico_dettaglio';
        }

        if (!$hasDocRel && $hasCaricoDet) {
            return $hasLegacy
                ? 'legacy_con_relazione_parziale'
                : 'relazionale_senza_carico_dettaglio';
        }

        if ($hasLegacy) {
            return 'solo_legacy';
        }

        return 'orphan';
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            return;
        }

        if ($rows === []) {
            fputcsv($handle, ['no_rows']);
            fclose($handle);
            return;
        }

        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);
    }
}
