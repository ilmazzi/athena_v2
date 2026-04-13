<?php

namespace App\Console\Commands;

use App\Models\Articolo;
use Illuminate\Console\Command;

class ExportArticoliEffectiveAudit extends Command
{
    protected $signature = 'audit:export-articoli-effective
                            {--output= : File CSV di output}
                            {--include-deleted : Include anche articoli soft-deleted}';

    protected $description = 'Esporta gli articoli MySQL con numero/data/fornitore effettivi per audit esterni';

    public function handle(): int
    {
        $output = trim((string) $this->option('output'));
        if ($output === '') {
            $output = storage_path('app/audit_articoli_live_vs_mssql/mysql_effective_' . now()->format('Ymd_His') . '.csv');
        }

        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            $this->error('Impossibile creare directory output: ' . $directory);
            return self::FAILURE;
        }

        $handle = fopen($output, 'wb');
        if ($handle === false) {
            $this->error('Impossibile aprire il file di output: ' . $output);
            return self::FAILURE;
        }

        fputcsv($handle, [
            'articolo_id',
            'codice',
            'codice_base',
            'base_code_match',
            'prefix',
            'descrizione_mysql',
            'mysql_fornitore_id',
            'mysql_fornitore',
            'mysql_numero_documento',
            'mysql_data_carico',
            'tipo_carico_effettivo',
            'prodotto_finito_id',
            'deleted_at',
        ]);

        $includeDeleted = (bool) $this->option('include-deleted');

        $query = Articolo::query()
            ->with([
                'fatturaDettaglio.fattura.fornitore',
                'ddtDettaglio.ddt.fornitore',
            ])
            ->orderBy('id');

        if (!$includeDeleted) {
            $query->whereNull('deleted_at');
        }

        $count = 0;

        $query->chunkById(500, function ($rows) use ($handle, &$count) {
            foreach ($rows as $articolo) {
                $codice = trim((string) $articolo->codice);

                fputcsv($handle, [
                    $articolo->id,
                    $codice,
                    (string) ($articolo->codice_base ?? ''),
                    $this->resolveMysqlBaseCode($codice, $articolo->codice_base),
                    $this->extractPrefix($codice),
                    trim((string) ($articolo->descrizione ?? '')),
                    $articolo->fornitore_effettivo_id,
                    $articolo->fornitore?->ragione_sociale,
                    $this->normalizeDocumentNumber($articolo->numero_documento_carico_effettivo),
                    $this->normalizeDate($articolo->data_carico_effettiva),
                    $articolo->tipo_carico_effettivo,
                    $articolo->prodotto_finito_id,
                    $articolo->deleted_at,
                ]);

                $count++;
            }
        }, 'id');

        fclose($handle);

        $this->info("Esportati {$count} articoli in: {$output}");

        return self::SUCCESS;
    }

    private function extractPrefix(string $codice): ?int
    {
        if (!preg_match('/^(\d+)-/', $codice, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function resolveMysqlBaseCode(string $codice, ?string $codiceBase): string
    {
        $explicitBase = trim((string) ($codiceBase ?? ''));
        $prefix = $this->extractPrefix($codice);

        if ($prefix === 5) {
            if ($explicitBase !== '') {
                return $explicitBase;
            }

            if (preg_match('/^5-\d+-\d+$/', $codice)) {
                $parts = explode('-', $codice);
                return $parts[0] . '-' . $parts[1];
            }
        }

        return $explicitBase !== '' ? $explicitBase : $codice;
    }

    private function normalizeDocumentNumber($value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return mb_strtoupper($normalized);
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
