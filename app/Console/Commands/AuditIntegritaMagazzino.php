<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditIntegritaMagazzino extends Command
{
    protected $signature = 'audit:integrita-magazzino
                            {--baseline : Salva l\'output corrente come baseline}
                            {--fail-on-critical : Restituisce exit code 2 se il semaforo è rosso}
                            {--use-env-audit-db : Usa la connessione audit definita in config/services.php (services.audit_db.*)}
                            {--output-dir=audit_integrita_magazzino : Cartella sotto storage/app per i report}';

    protected $description = 'Audit read-only su coerenza articoli/giacenze/movimenti/deposito con export JSON/CSV';

    public function handle(): int
    {
        $this->configureRuntimeConnection();

        $outputDir = trim((string) $this->option('output-dir'));
        $root = storage_path('app/' . ($outputDir !== '' ? $outputDir : 'audit_integrita_magazzino'));

        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            $this->error('Impossibile creare directory report: ' . $root);
            return 1;
        }

        $timestamp = now()->format('Ymd_His');
        $jsonPath = $root . "/snapshot_{$timestamp}.json";
        $metrics = $this->collectMetrics();
        $critical = $this->evaluateCritical($root, $metrics);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'metrics' => $metrics,
            'critical' => $critical,
        ];

        file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->writeCsvReports($root, $timestamp);

        if ($this->option('baseline')) {
            file_put_contents($root . '/baseline.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Baseline salvata.');
        }

        $this->table(
            ['KPI', 'Valore'],
            [
                ['mismatch_articoli_giacenze_attivi', $metrics['mismatch_articoli_giacenze_attivi']],
                ['mismatch_giacenze_sedi_attivi_residua', $metrics['mismatch_giacenze_sedi_attivi_residua']],
                ['soft_deleted_con_stock', $metrics['soft_deleted_con_stock']],
                ['xor_movimenti_deposito_violazioni', $metrics['xor_movimenti_deposito_violazioni']],
                ['deposito_drift_articoli', $metrics['deposito_drift_articoli']],
                ['deposito_drift_pf', $metrics['deposito_drift_pf']],
            ]
        );

        $this->line('Snapshot: ' . $jsonPath);
        $this->line('Semaforo: ' . strtoupper($critical['semaforo']));
        if (!empty($critical['notes'])) {
            foreach ($critical['notes'] as $note) {
                $this->line('- ' . $note);
            }
        }

        if ($this->option('fail-on-critical') && $critical['semaforo'] === 'rosso') {
            return 2;
        }

        return 0;
    }

    private function configureRuntimeConnection(): void
    {
        if (!$this->option('use-env-audit-db')) {
            return;
        }

        $audit = (array) config('services.audit_db', []);
        $host = trim((string) ($audit['host'] ?? ''));
        $port = (int) ($audit['port'] ?? 0);
        $database = trim((string) ($audit['database'] ?? ''));
        $username = trim((string) ($audit['username'] ?? ''));
        $password = (string) ($audit['password'] ?? '');

        if ($host === '' || $port <= 0 || $database === '' || $username === '') {
            $this->warn('Audit DB env incompleto: uso connessione di default.');
            return;
        }

        $default = (string) config('database.default', 'mysql');
        $base = (array) config("database.connections.$default", []);
        if (empty($base)) {
            $this->warn("Connessione base '{$default}' non trovata: uso default applicativo.");
            return;
        }

        $connName = 'audit_runtime';
        $cfg = $base;
        $cfg['host'] = $host;
        $cfg['port'] = $port;
        $cfg['database'] = $database;
        $cfg['username'] = $username;
        $cfg['password'] = $password;

        config(["database.connections.$connName" => $cfg]);
        config(['database.default' => $connName]);
        DB::purge($connName);
        DB::reconnect($connName);

        $this->line("Connessione audit attiva: {$host}:{$port}/{$database} ({$username})");
    }

    private function collectMetrics(): array
    {
        $k1 = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM articoli a
            JOIN giacenze g ON g.articolo_id = a.id
            WHERE a.deleted_at IS NULL
              AND (
                    COALESCE(g.sede_id, 0) <> COALESCE(a.sede_id, 0)
                    OR COALESCE(g.magazzino_logico, 0) <> COALESCE(a.magazzino_logico, 0)
              )
        ")->n;

        $k2 = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM articoli a
            JOIN giacenze_sedi gs ON gs.articolo_id = a.id
            WHERE a.deleted_at IS NULL
              AND gs.quantita_residua > 0
              AND COALESCE(gs.sede_id, 0) <> COALESCE(a.sede_id, 0)
        ")->n;

        $k3 = (int) DB::selectOne("
            SELECT COUNT(DISTINCT a.id) AS n
            FROM articoli a
            LEFT JOIN giacenze g ON g.articolo_id = a.id
            LEFT JOIN giacenze_sedi gs ON gs.articolo_id = a.id
            WHERE a.deleted_at IS NOT NULL
              AND (
                    COALESCE(g.quantita_residua, 0) > 0
                    OR COALESCE(gs.quantita_residua, 0) > 0
              )
        ")->n;

        $k4 = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM movimenti_deposito md
            WHERE (md.articolo_id IS NULL AND md.prodotto_finito_id IS NULL)
               OR (md.articolo_id IS NOT NULL AND md.prodotto_finito_id IS NOT NULL)
        ")->n;

        $k5 = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM (
                SELECT
                    a.id,
                    COALESCE(SUM(CASE
                        WHEN md.tipo_movimento IN ('invio','rimando') THEN md.quantita
                        WHEN md.tipo_movimento IN ('vendita','reso') THEN -md.quantita
                        ELSE 0
                    END), 0) AS net_qta,
                    COALESCE(a.quantita_in_deposito, 0) AS stored_qta,
                    a.conto_deposito_corrente_id
                FROM articoli a
                LEFT JOIN movimenti_deposito md ON md.articolo_id = a.id
                LEFT JOIN conti_deposito cd ON cd.id = md.conto_deposito_id
                WHERE a.deleted_at IS NULL
                  AND (cd.id IS NULL OR cd.stato IN ('attivo','parziale','scaduto'))
                GROUP BY a.id, a.quantita_in_deposito, a.conto_deposito_corrente_id
            ) t
            WHERE GREATEST(0, t.net_qta) <> t.stored_qta
               OR (GREATEST(0, t.net_qta) > 0 AND t.conto_deposito_corrente_id IS NULL)
        ")->n;

        $k6 = (int) DB::selectOne("
            SELECT COUNT(*) AS n
            FROM (
                SELECT
                    pf.id,
                    COALESCE(SUM(CASE
                        WHEN md.tipo_movimento IN ('invio','rimando') THEN md.quantita
                        WHEN md.tipo_movimento IN ('vendita','reso') THEN -md.quantita
                        ELSE 0
                    END), 0) AS net_qta,
                    COALESCE(pf.in_conto_deposito, 0) AS in_dep
                FROM prodotti_finiti pf
                LEFT JOIN movimenti_deposito md ON md.prodotto_finito_id = pf.id
                LEFT JOIN conti_deposito cd ON cd.id = md.conto_deposito_id
                WHERE pf.deleted_at IS NULL
                  AND (cd.id IS NULL OR cd.stato IN ('attivo','parziale','scaduto'))
                GROUP BY pf.id, pf.in_conto_deposito
            ) t
            WHERE (GREATEST(0, t.net_qta) > 0 AND t.in_dep = 0)
               OR (GREATEST(0, t.net_qta) = 0 AND t.in_dep = 1)
        ")->n;

        return [
            'mismatch_articoli_giacenze_attivi' => $k1,
            'mismatch_giacenze_sedi_attivi_residua' => $k2,
            'soft_deleted_con_stock' => $k3,
            'xor_movimenti_deposito_violazioni' => $k4,
            'deposito_drift_articoli' => $k5,
            'deposito_drift_pf' => $k6,
        ];
    }

    private function evaluateCritical(string $root, array $metrics): array
    {
        $notes = [];
        $semaforo = 'verde';

        if ($metrics['xor_movimenti_deposito_violazioni'] > 0) {
            $semaforo = 'rosso';
            $notes[] = 'Violazioni XOR su movimenti_deposito > 0';
        }

        $baselinePath = $root . '/baseline.json';
        if (is_file($baselinePath)) {
            $baseline = json_decode((string) file_get_contents($baselinePath), true);
            $baseMetrics = $baseline['metrics'] ?? [];

            $trendKeys = [
                'mismatch_articoli_giacenze_attivi',
                'mismatch_giacenze_sedi_attivi_residua',
                'deposito_drift_articoli',
                'deposito_drift_pf',
            ];
            foreach ($trendKeys as $key) {
                $base = (int) ($baseMetrics[$key] ?? 0);
                $curr = (int) ($metrics[$key] ?? 0);
                if ($curr > $base) {
                    $semaforo = 'rosso';
                    $notes[] = "Trend peggiorativo su {$key}: baseline={$base}, attuale={$curr}";
                } elseif ($curr === $base && $curr > 0 && $semaforo === 'verde') {
                    $semaforo = 'giallo';
                }
            }
        } else {
            if (
                $metrics['mismatch_articoli_giacenze_attivi'] > 0
                || $metrics['mismatch_giacenze_sedi_attivi_residua'] > 0
                || $metrics['deposito_drift_articoli'] > 0
                || $metrics['deposito_drift_pf'] > 0
            ) {
                $semaforo = 'giallo';
                $notes[] = 'Baseline assente: impossibile valutare trend';
            }
        }

        return [
            'semaforo' => $semaforo,
            'notes' => $notes,
        ];
    }

    private function writeCsvReports(string $root, string $timestamp): void
    {
        $this->writeCsv(
            $root . "/kpi1_articoli_vs_giacenze_{$timestamp}.csv",
            DB::select("
                SELECT a.id, a.codice, a.sede_id AS articolo_sede_id, a.magazzino_logico AS articolo_magazzino_logico,
                       g.id AS giacenza_id, g.sede_id AS giacenza_sede_id, g.magazzino_logico AS giacenza_magazzino_logico,
                       g.quantita, g.quantita_residua
                FROM articoli a
                JOIN giacenze g ON g.articolo_id = a.id
                WHERE a.deleted_at IS NULL
                  AND (
                        COALESCE(g.sede_id, 0) <> COALESCE(a.sede_id, 0)
                        OR COALESCE(g.magazzino_logico, 0) <> COALESCE(a.magazzino_logico, 0)
                  )
                ORDER BY a.id
                LIMIT 1000
            ")
        );

        $this->writeCsv(
            $root . "/kpi2_giacenze_sedi_residua_{$timestamp}.csv",
            DB::select("
                SELECT a.id, a.codice, a.sede_id AS articolo_sede_id, gs.sede_id AS giacenze_sedi_sede_id,
                       gs.quantita, gs.quantita_residua
                FROM articoli a
                JOIN giacenze_sedi gs ON gs.articolo_id = a.id
                WHERE a.deleted_at IS NULL
                  AND gs.quantita_residua > 0
                  AND COALESCE(gs.sede_id, 0) <> COALESCE(a.sede_id, 0)
                ORDER BY a.id
                LIMIT 1000
            ")
        );

        $this->writeCsv(
            $root . "/kpi4_xor_movimenti_deposito_{$timestamp}.csv",
            DB::select("
                SELECT id, conto_deposito_id, articolo_id, prodotto_finito_id, tipo_movimento, quantita, data_movimento
                FROM movimenti_deposito
                WHERE (articolo_id IS NULL AND prodotto_finito_id IS NULL)
                   OR (articolo_id IS NOT NULL AND prodotto_finito_id IS NOT NULL)
                ORDER BY id DESC
                LIMIT 1000
            ")
        );
    }

    /**
     * @param array<int,object> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fp = fopen($path, 'wb');
        if ($fp === false) {
            return;
        }

        if (empty($rows)) {
            fputcsv($fp, ['empty']);
            fclose($fp);
            return;
        }

        $first = (array) $rows[0];
        fputcsv($fp, array_keys($first));
        foreach ($rows as $row) {
            fputcsv($fp, array_values((array) $row));
        }
        fclose($fp);
    }
}

