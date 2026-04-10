<?php

namespace App\Console\Commands;

use App\Models\Fornitore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditCarichiMssql extends Command
{
    protected $signature = 'audit:carichi-mssql
                            {--output-dir=audit_carichi_mssql : Cartella sotto storage/app per i report}
                            {--only-problematic : Esporta solo mismatch e record senza match MSSQL}
                            {--include-deleted : Include anche articoli soft-deleted}';

    protected $description = 'Confronta numero/data carico degli articoli MySQL con la fonte di verità MSSQL';

    public function handle(): int
    {
        $table = $this->resolveMssqlArticoliTable();
        if (!$table) {
            $this->error('Nessuna tabella/vista MSSQL trovata (elenco_articoli_magazzino/mag_articoli).');
            return self::FAILURE;
        }

        $outputDir = trim((string) $this->option('output-dir'));
        $root = storage_path('app/' . ($outputDir !== '' ? $outputDir : 'audit_carichi_mssql'));
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            $this->error('Impossibile creare directory report: ' . $root);
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $onlyProblematic = (bool) $this->option('only-problematic');
        $includeDeleted = (bool) $this->option('include-deleted');

        $mssqlQuery = DB::connection('mssql_prod')
            ->table($table)
            ->select('id', 'id_magazzino', 'carico', 'numero_documento', 'data_documento', 'descrizione');

        if ($this->mssqlColumnExists($table, 'fornitore')) {
            $mssqlQuery->addSelect('fornitore');
        }

        $mssqlRows = $mssqlQuery->get();

        $mssqlByBase = [];
        $mssqlDuplicates = [];

        foreach ($mssqlRows as $row) {
            $baseCode = $this->buildBaseCode($row->id_magazzino ?? null, $row->carico ?? null, $row->id ?? null);
            if ($baseCode === '') {
                continue;
            }

            if (isset($mssqlByBase[$baseCode])) {
                $mssqlDuplicates[] = [
                    'base_code' => $baseCode,
                    'existing_id' => $mssqlByBase[$baseCode]['mssql_id'],
                    'duplicate_id' => (int) ($row->id ?? 0),
                ];
                continue;
            }

            $mssqlByBase[$baseCode] = [
                'mssql_id' => (int) ($row->id ?? 0),
                'base_code' => $baseCode,
                'numero_documento' => $this->normalizeDocumentNumber($row->numero_documento ?? null),
                'data_documento' => $this->normalizeDate($row->data_documento ?? null),
                'fornitore_raw' => trim((string) ($row->fornitore ?? '')),
                'fornitore_id' => $this->resolveMysqlFornitoreId($row->fornitore ?? null),
                'descrizione_mssql' => trim((string) ($row->descrizione ?? '')),
            ];
        }

        $exact = [];
        $mismatch = [];
        $missing = [];
        $illegalSplits = [];

        $query = DB::table('articoli')
            ->select(
                'id',
                'codice',
                'codice_base',
                'descrizione',
                'fornitore_id',
                'numero_documento_carico',
                'data_carico',
                'deleted_at'
            )
            ->orderBy('id');

        if (!$includeDeleted) {
            $query->whereNull('deleted_at');
        }

        $query->chunk(1000, function ($rows) use (&$exact, &$mismatch, &$missing, &$illegalSplits, $mssqlByBase, $onlyProblematic) {
            foreach ($rows as $row) {
                $codice = trim((string) $row->codice);
                $baseCode = $this->resolveMysqlBaseCode($codice, $row->codice_base ?? null);
                $prefix = $this->extractPrefix($codice);
                $mysqlNumero = $this->normalizeDocumentNumber($row->numero_documento_carico ?? null);
                $mysqlData = $this->normalizeDate($row->data_carico ?? null);
                $mysqlFornitoreId = $row->fornitore_id ? (int) $row->fornitore_id : null;
                $mysqlFornitoreName = $this->resolveMysqlFornitoreName($mysqlFornitoreId);

                $payload = [
                    'articolo_id' => (int) $row->id,
                    'codice' => $codice,
                    'codice_base' => (string) ($row->codice_base ?? ''),
                    'base_code_match' => $baseCode,
                    'prefix' => $prefix,
                    'descrizione_mysql' => trim((string) ($row->descrizione ?? '')),
                    'mysql_fornitore_id' => $mysqlFornitoreId,
                    'mysql_fornitore' => $mysqlFornitoreName,
                    'mysql_numero_documento' => $mysqlNumero,
                    'mysql_data_carico' => $mysqlData,
                    'deleted_at' => $row->deleted_at,
                ];

                if ($this->isIllegalSplit($codice, $prefix, $row->codice_base ?? null)) {
                    $illegalSplits[] = $payload + [
                        'status' => 'illegal_split_for_prefix',
                    ];
                    continue;
                }

                $mssql = $mssqlByBase[$baseCode] ?? null;
                if ($mssql === null) {
                    $missing[] = $payload + [
                        'status' => 'missing_in_mssql',
                    ];
                    continue;
                }

                $rowData = $payload + [
                    'mssql_id' => $mssql['mssql_id'],
                    'mssql_fornitore_id' => $mssql['fornitore_id'],
                    'mssql_fornitore' => $mssql['fornitore_raw'],
                    'mssql_numero_documento' => $mssql['numero_documento'],
                    'mssql_data_documento' => $mssql['data_documento'],
                    'descrizione_mssql' => $mssql['descrizione_mssql'],
                ];

                $numeroMismatch = $mysqlNumero !== $mssql['numero_documento'];
                $dataMismatch = $mysqlData !== $mssql['data_documento'];
                $fornitoreMismatch = $this->fornitoreMismatch($mysqlFornitoreId, $mssql['fornitore_id'], $mssql['fornitore_raw']);

                if ($numeroMismatch || $dataMismatch || $fornitoreMismatch) {
                    $mismatch[] = $rowData + [
                        'status' => $this->buildMismatchStatus($numeroMismatch, $dataMismatch, $fornitoreMismatch),
                    ];
                    continue;
                }

                if (!$onlyProblematic) {
                    $exact[] = $rowData + [
                        'status' => 'match',
                    ];
                }
            }
        });

        $files = [];
        $files['missing_in_mssql'] = $this->writeCsv($root, "missing_in_mssql_{$timestamp}.csv", $missing);
        $files['mismatch'] = $this->writeCsv($root, "mismatch_carichi_{$timestamp}.csv", $mismatch);
        $files['illegal_splits'] = $this->writeCsv($root, "illegal_splits_{$timestamp}.csv", $illegalSplits);
        $files['mssql_duplicates'] = $this->writeCsv($root, "mssql_basecode_duplicates_{$timestamp}.csv", $mssqlDuplicates);
        if (!$onlyProblematic) {
            $files['match'] = $this->writeCsv($root, "match_carichi_{$timestamp}.csv", $exact);
        }

        $summary = [
            'generated_at' => now()->toIso8601String(),
            'mssql_table' => $table,
            'counts' => [
                'match' => count($exact),
                'mismatch' => count($mismatch),
                'missing_in_mssql' => count($missing),
                'illegal_splits' => count($illegalSplits),
                'mssql_basecode_duplicates' => count($mssqlDuplicates),
            ],
            'files' => $files,
        ];

        $summaryPath = $root . DIRECTORY_SEPARATOR . "summary_{$timestamp}.json";
        file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->table(
            ['Check', 'Count'],
            [
                ['Match', count($exact)],
                ['Mismatch', count($mismatch)],
                ['Missing in MSSQL', count($missing)],
                ['Illegal splits', count($illegalSplits)],
                ['MSSQL duplicate base_code', count($mssqlDuplicates)],
            ]
        );

        $this->line('Summary: ' . $summaryPath);

        return self::SUCCESS;
    }

    private function resolveMssqlArticoliTable(): ?string
    {
        if ($this->mssqlViewExists('elenco_articoli_magazzino') || Schema::connection('mssql_prod')->hasTable('elenco_articoli_magazzino')) {
            return 'elenco_articoli_magazzino';
        }

        if (Schema::connection('mssql_prod')->hasTable('mag_articoli')) {
            return 'mag_articoli';
        }

        return null;
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

    private function isIllegalSplit(string $codice, ?int $prefix, ?string $codiceBase): bool
    {
        if (!in_array($prefix, [2, 3, 20], true)) {
            return false;
        }

        if (preg_match('/^\d+-\d+-\d+$/', $codice) === 1) {
            return true;
        }

        $explicitBase = trim((string) ($codiceBase ?? ''));
        if ($explicitBase === '') {
            return false;
        }

        return $explicitBase !== $codice;
    }

    private function mssqlViewExists(string $viewName): bool
    {
        try {
            $rows = DB::connection('mssql_prod')->select(
                'SELECT 1 FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_NAME = ?',
                [$viewName]
            );

            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function mssqlColumnExists(string $table, string $column): bool
    {
        try {
            return Schema::connection('mssql_prod')->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param mixed $magazzino
     * @param mixed $carico
     * @param mixed $fallbackId
     */
    private function buildBaseCode($magazzino, $carico, $fallbackId): string
    {
        $mag = trim((string) ($magazzino ?? ''));
        $car = trim((string) ($carico ?? ''));
        $fallback = trim((string) ($fallbackId ?? ''));

        if ($mag === '') {
            return '';
        }

        return $mag . '-' . ($car !== '' ? $car : $fallback);
    }

    /**
     * @param mixed $value
     */
    private function normalizeDocumentNumber($value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return mb_strtoupper($normalized);
    }

    /**
     * @param mixed $value
     */
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

    private function resolveMysqlFornitoreId($value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || strcasecmp($raw, 'NON INSERITO') === 0) {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        $fornitore = Fornitore::query()
            ->where('ragione_sociale', $raw)
            ->orWhere('ragione_sociale', 'like', '%' . $raw . '%')
            ->first(['id']);

        return $fornitore?->id;
    }

    private function resolveMysqlFornitoreName(?int $fornitoreId): ?string
    {
        if (!$fornitoreId) {
            return null;
        }

        return Fornitore::query()->whereKey($fornitoreId)->value('ragione_sociale');
    }

    private function fornitoreMismatch(?int $mysqlFornitoreId, ?int $mssqlFornitoreId, string $mssqlFornitoreRaw): bool
    {
        if ($mssqlFornitoreId === null && $mssqlFornitoreRaw === '') {
            return false;
        }

        return $mysqlFornitoreId !== $mssqlFornitoreId;
    }

    private function buildMismatchStatus(bool $numeroMismatch, bool $dataMismatch, bool $fornitoreMismatch): string
    {
        $parts = [];

        if ($numeroMismatch) {
            $parts[] = 'numero';
        }

        if ($dataMismatch) {
            $parts[] = 'data';
        }

        if ($fornitoreMismatch) {
            $parts[] = 'fornitore';
        }

        return 'mismatch_' . implode('_', $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $root, string $filename, array $rows): string
    {
        $path = $root . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            return $filename;
        }

        if ($rows === []) {
            fputcsv($handle, ['no_rows']);
            fclose($handle);
            return $filename;
        }

        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);

        return $filename;
    }
}
