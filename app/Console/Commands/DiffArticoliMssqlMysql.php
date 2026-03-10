<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiffArticoliMssqlMysql extends Command
{
    protected $signature = 'articoli:diff-mssql
                            {--from-id= : ID minimo da confrontare}
                            {--to-id= : ID massimo da confrontare}
                            {--only-prefix= : Limita al prefisso/categoria (es. 2)}
                            {--chunk=1000 : Dimensione chunk di confronto}
                            {--limit=200 : Numero massimo di esempi per lato}
                            {--export-missing-mysql= : Path per esportare ID mancanti in MySQL}
                            {--export-missing-mssql= : Path per esportare ID mancanti in MSSQL}
                            {--purge-mysql-missing : Elimina da MySQL gli ID non presenti in MSSQL}
                            {--force-delete : Esegue delete fisico invece di soft-delete}
                            {--dry-run : Simula senza eliminare}';

    protected $description = 'Confronta articoli tra MSSQL e MySQL e segnala discrepanze';

    public function handle(): int
    {
        $table = $this->resolveMssqlArticoliTable();
        if (!$table) {
            $this->error('❌ Nessuna tabella/vista MSSQL trovata (elenco_articoli_magazzino/mag_articoli).');
            return self::FAILURE;
        }

        $fromId = (int) ($this->option('from-id') ?? 1);
        $toId = $this->option('to-id');
        $onlyPrefix = $this->option('only-prefix');
        $chunk = max(100, (int) $this->option('chunk'));
        $limit = max(1, (int) $this->option('limit'));
        $exportMissingMysql = $this->option('export-missing-mysql');
        $exportMissingMssql = $this->option('export-missing-mssql');
        $purgeMysqlMissing = (bool) $this->option('purge-mysql-missing');
        $forceDelete = (bool) $this->option('force-delete');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($toId)) {
            $maxMssql = (int) (DB::connection('mssql_prod')->table($table)->max('id') ?? 0);
            $maxMysql = (int) (DB::table('articoli')->max('id') ?? 0);
            $toId = max($maxMssql, $maxMysql);
        } else {
            $toId = (int) $toId;
        }

        if ($toId < $fromId) {
            $this->error('❌ Intervallo ID non valido.');
            return self::FAILURE;
        }

        $this->info('🔎 CONFRONTO ARTICOLI MSSQL vs MYSQL');
        $this->line("  Tabella MSSQL: {$table}");
        $this->line("  Range ID: {$fromId} → {$toId}");
        if (!empty($onlyPrefix)) {
            $this->line("  Filtro prefisso/categoria: {$onlyPrefix}");
        }

        $missingInMysqlCount = 0;
        $missingInMssqlCount = 0;
        $missingInMysqlSamples = [];
        $missingInMssqlSamples = [];
        $missingInMysqlIds = [];
        $missingInMssqlIds = [];

        for ($start = $fromId; $start <= $toId; $start += $chunk) {
            $end = min($toId, $start + $chunk - 1);

            $mssqlIds = DB::connection('mssql_prod')
                ->table($table)
                ->select('id')
                ->whereBetween('id', [$start, $end])
                ->when(!empty($onlyPrefix), function ($q) use ($onlyPrefix) {
                    $q->where('id_magazzino', (int) $onlyPrefix);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $mysqlIds = DB::table('articoli')
                ->select('id')
                ->whereBetween('id', [$start, $end])
                ->when(!empty($onlyPrefix), function ($q) use ($onlyPrefix) {
                    $q->where('categoria_merceologica_id', (int) $onlyPrefix);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $missingInMysql = array_values(array_diff($mssqlIds, $mysqlIds));
            $missingInMssql = array_values(array_diff($mysqlIds, $mssqlIds));

            $missingInMysqlCount += count($missingInMysql);
            $missingInMssqlCount += count($missingInMssql);
            if (!empty($missingInMysql)) {
                $missingInMysqlIds = array_merge($missingInMysqlIds, $missingInMysql);
            }
            if (!empty($missingInMssql)) {
                $missingInMssqlIds = array_merge($missingInMssqlIds, $missingInMssql);
            }

            if (count($missingInMysqlSamples) < $limit && !empty($missingInMysql)) {
                $needed = array_slice($missingInMysql, 0, $limit - count($missingInMysqlSamples));
                $missingInMysqlSamples = array_merge(
                    $missingInMysqlSamples,
                    $this->fetchMssqlSampleRows($table, $needed)
                );
            }

            if (count($missingInMssqlSamples) < $limit && !empty($missingInMssql)) {
                $needed = array_slice($missingInMssql, 0, $limit - count($missingInMssqlSamples));
                $missingInMssqlSamples = array_merge(
                    $missingInMssqlSamples,
                    $this->fetchMysqlSampleRows($needed)
                );
            }
        }

        $this->newLine();
        $this->line("  Mancanti in MySQL: {$missingInMysqlCount}");
        $this->line("  Mancanti in MSSQL: {$missingInMssqlCount}");

        if (!empty($missingInMysqlSamples)) {
            $this->newLine();
            $this->info('  Esempi mancanti in MySQL:');
            foreach ($missingInMysqlSamples as $row) {
                $this->line("   - ID {$row['id']} | {$row['codice']} | {$row['descrizione']}");
            }
        }

        if (!empty($missingInMssqlSamples)) {
            $this->newLine();
            $this->info('  Esempi mancanti in MSSQL:');
            foreach ($missingInMssqlSamples as $row) {
                $this->line("   - ID {$row['id']} | {$row['codice']} | {$row['descrizione']}");
            }
        }

        if (!empty($exportMissingMysql)) {
            $this->exportIds($exportMissingMysql, $missingInMysqlIds);
            $this->line("  Export mancanti in MySQL: {$exportMissingMysql}");
        }

        if (!empty($exportMissingMssql)) {
            $this->exportIds($exportMissingMssql, $missingInMssqlIds);
            $this->line("  Export mancanti in MSSQL: {$exportMissingMssql}");
        }

        if ($purgeMysqlMissing && !empty($missingInMssqlIds)) {
            $this->newLine();
            $this->warn("  Eliminazione MySQL mancanti in MSSQL: " . count($missingInMssqlIds));
            if ($dryRun) {
                $this->line('  Dry-run: nessuna eliminazione eseguita.');
            } else {
                $deleted = 0;
                foreach (array_chunk($missingInMssqlIds, 1000) as $chunkIds) {
                    if ($forceDelete) {
                        $deleted += DB::table('articoli')->whereIn('id', $chunkIds)->delete();
                    } else {
                        $deleted += DB::table('articoli')
                            ->whereIn('id', $chunkIds)
                            ->update(['deleted_at' => now()]);
                    }
                }
                $this->line($forceDelete ? "  Eliminati: {$deleted}" : "  Soft-deleted: {$deleted}");
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function fetchMssqlSampleRows(string $table, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = DB::connection('mssql_prod')
            ->table($table)
            ->select('id', 'id_magazzino', 'carico', 'descrizione')
            ->whereIn('id', $ids)
            ->get();

        return $rows->map(function ($row) {
            $base = ($row->id_magazzino ?? 0) . '-' . ($row->carico ?? $row->id);
            return [
                'id' => (int) $row->id,
                'codice' => $base,
                'descrizione' => $row->descrizione ?? '',
            ];
        })->all();
    }

    private function fetchMysqlSampleRows(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('articoli')
            ->select('id', 'codice', 'descrizione')
            ->whereIn('id', $ids)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'codice' => $row->codice ?? '',
                'descrizione' => $row->descrizione ?? '',
            ];
        })->all();
    }

    private function exportIds(string $path, array $ids): void
    {
        $content = implode(PHP_EOL, array_unique($ids)) . PHP_EOL;
        @file_put_contents($path, $content);
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

    private function mssqlViewExists(string $viewName): bool
    {
        try {
            $rows = DB::connection('mssql_prod')
                ->select(
                    "SELECT 1 FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_NAME = ?",
                    [$viewName]
                );
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
