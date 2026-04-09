<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditFonteVeritaMagazzino extends Command
{
    protected $signature = 'audit:fonte-verita-magazzino
                            {--output-dir=audit_fonte_verita_magazzino : Cartella sotto storage/app per i report}';

    protected $description = 'Audit read-only del modello canonico magazzino/articoli/deposito con export JSON e CSV';

    public function handle(): int
    {
        $outputDir = trim((string) $this->option('output-dir'));
        $root = storage_path('app/' . ($outputDir !== '' ? $outputDir : 'audit_fonte_verita_magazzino'));

        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            $this->error('Impossibile creare directory report: ' . $root);
            return 1;
        }

        $timestamp = now()->format('Ymd_His');
        $checks = $this->buildChecks();
        $summary = [];

        foreach ($checks as $key => $check) {
            $rows = DB::select($check['sql']);
            $count = count($rows);

            $summary[$key] = [
                'label' => $check['label'],
                'severity' => $check['severity'],
                'count' => $count,
                'csv' => $this->writeCsv($root, $timestamp, $key, $rows),
            ];
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
        ];

        $jsonPath = $root . "/summary_{$timestamp}.json";
        file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->table(
            ['Check', 'Severità', 'Count'],
            collect($summary)->map(fn (array $item) => [
                $item['label'],
                strtoupper($item['severity']),
                $item['count'],
            ])->values()->all()
        );

        $this->line('Summary: ' . $jsonPath);

        return 0;
    }

    /**
     * @return array<string, array{label: string, severity: string, sql: string}>
     */
    private function buildChecks(): array
    {
        return [
            'articoli_senza_giacenza' => [
                'label' => 'Articoli attivi senza giacenza',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        a.id,
                        a.codice,
                        a.codice_base,
                        a.descrizione,
                        a.sede_id AS articolo_sede_id,
                        a.categoria_merceologica_id AS articolo_categoria_id,
                        a.magazzino_logico AS articolo_magazzino_logico
                    FROM articoli a
                    LEFT JOIN giacenze g ON g.articolo_id = a.id
                    WHERE a.deleted_at IS NULL
                    GROUP BY a.id, a.codice, a.codice_base, a.descrizione, a.sede_id, a.categoria_merceologica_id, a.magazzino_logico
                    HAVING COUNT(g.id) = 0
                ",
            ],
            'giacenze_duplicate_articolo_sede' => [
                'label' => 'Giacenze duplicate per articolo+sede',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        g.articolo_id,
                        a.codice,
                        g.sede_id,
                        COUNT(*) AS righe,
                        SUM(COALESCE(g.quantita, 0)) AS quantita_totale,
                        SUM(COALESCE(g.quantita_residua, 0)) AS quantita_residua_totale
                    FROM giacenze g
                    JOIN articoli a ON a.id = g.articolo_id
                    GROUP BY g.articolo_id, a.codice, g.sede_id
                    HAVING COUNT(*) > 1
                ",
            ],
            'mismatch_articolo_vs_giacenza' => [
                'label' => 'Mismatch articolo vs giacenza canonica',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        a.id,
                        a.codice,
                        a.descrizione,
                        a.sede_id AS articolo_sede_id,
                        g.sede_id AS giacenza_sede_id,
                        a.categoria_merceologica_id AS articolo_categoria_id,
                        g.categoria_merceologica_id AS giacenza_categoria_id,
                        a.magazzino_logico AS articolo_magazzino_logico,
                        g.magazzino_logico AS giacenza_magazzino_logico
                    FROM articoli a
                    JOIN giacenze g ON g.articolo_id = a.id
                    WHERE a.deleted_at IS NULL
                      AND (
                          COALESCE(a.sede_id, 0) <> COALESCE(g.sede_id, 0)
                          OR COALESCE(a.categoria_merceologica_id, 0) <> COALESCE(g.categoria_merceologica_id, 0)
                          OR COALESCE(a.magazzino_logico, 0) <> COALESCE(g.magazzino_logico, 0)
                      )
                ",
            ],
            'giacenze_residua_superiore_storica' => [
                'label' => 'Giacenze con residua superiore alla storica',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        g.id,
                        a.codice,
                        g.sede_id,
                        g.quantita,
                        g.quantita_residua,
                        g.quantita_iniziale
                    FROM giacenze g
                    JOIN articoli a ON a.id = g.articolo_id
                    WHERE COALESCE(g.quantita_residua, 0) > COALESCE(g.quantita, 0)
                ",
            ],
            'soft_deleted_con_stock' => [
                'label' => 'Articoli soft-deleted con stock residuo',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        a.id,
                        a.codice,
                        a.descrizione,
                        a.deleted_at,
                        SUM(COALESCE(g.quantita_residua, 0)) AS quantita_residua
                    FROM articoli a
                    JOIN giacenze g ON g.articolo_id = a.id
                    WHERE a.deleted_at IS NOT NULL
                    GROUP BY a.id, a.codice, a.descrizione, a.deleted_at
                    HAVING SUM(COALESCE(g.quantita_residua, 0)) > 0
                ",
            ],
            'articoli_con_snapshot_carico_senza_documento' => [
                'label' => 'Snapshot carico articolo senza dettaglio documento',
                'severity' => 'media',
                'sql' => "
                    SELECT
                        a.id,
                        a.codice,
                        a.descrizione,
                        a.tipo_carico,
                        a.numero_documento_carico,
                        a.data_carico
                    FROM articoli a
                    LEFT JOIN ddt_dettagli dd ON dd.articolo_id = a.id
                    LEFT JOIN fatture_dettagli fd ON fd.articolo_id = a.id
                    WHERE a.deleted_at IS NULL
                      AND a.numero_documento_carico IS NOT NULL
                      AND TRIM(a.numero_documento_carico) <> ''
                    GROUP BY a.id, a.codice, a.descrizione, a.tipo_carico, a.numero_documento_carico, a.data_carico
                    HAVING COUNT(dd.id) = 0 AND COUNT(fd.id) = 0
                ",
            ],
            'articoli_con_documento_ma_snapshot_divergente' => [
                'label' => 'Documento presente ma snapshot carico divergente',
                'severity' => 'media',
                'sql' => "
                    SELECT DISTINCT
                        a.id,
                        a.codice,
                        a.descrizione,
                        a.numero_documento_carico AS articolo_numero_documento,
                        a.data_carico AS articolo_data_carico,
                        COALESCE(f.numero, d.numero) AS documento_numero,
                        COALESCE(DATE(f.data_documento), DATE(d.data_documento)) AS documento_data
                    FROM articoli a
                    LEFT JOIN fatture_dettagli fd ON fd.articolo_id = a.id
                    LEFT JOIN fatture f ON f.id = fd.fattura_id
                    LEFT JOIN ddt_dettagli dd ON dd.articolo_id = a.id
                    LEFT JOIN ddt d ON d.id = dd.ddt_id
                    WHERE a.deleted_at IS NULL
                      AND (
                          COALESCE(TRIM(a.numero_documento_carico), '') <> COALESCE(TRIM(f.numero), TRIM(d.numero), '')
                          OR (
                              a.data_carico IS NOT NULL
                              AND COALESCE(DATE(a.data_carico), DATE('1900-01-01')) <> COALESCE(DATE(f.data_documento), DATE(d.data_documento), DATE('1900-01-01'))
                          )
                      )
                      AND (fd.id IS NOT NULL OR dd.id IS NOT NULL)
                ",
            ],
            'deposito_drift_articoli_projection' => [
                'label' => 'Drift projection deposito articoli',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        t.id,
                        t.codice,
                        t.descrizione,
                        t.net_qta AS deposito_da_movimenti,
                        t.stored_qta AS deposito_salvato,
                        t.conto_deposito_corrente_id
                    FROM (
                        SELECT
                            a.id,
                            a.codice,
                            a.descrizione,
                            GREATEST(0, COALESCE(SUM(CASE
                                WHEN md.tipo_movimento IN ('invio', 'rimando') THEN md.quantita
                                WHEN md.tipo_movimento IN ('vendita', 'reso') THEN -md.quantita
                                ELSE 0
                            END), 0)) AS net_qta,
                            COALESCE(a.quantita_in_deposito, 0) AS stored_qta,
                            a.conto_deposito_corrente_id
                        FROM articoli a
                        LEFT JOIN movimenti_deposito md ON md.articolo_id = a.id
                        LEFT JOIN conti_deposito cd ON cd.id = md.conto_deposito_id
                        WHERE a.deleted_at IS NULL
                          AND (cd.id IS NULL OR cd.stato IN ('attivo', 'parziale', 'scaduto'))
                        GROUP BY a.id, a.codice, a.descrizione, a.quantita_in_deposito, a.conto_deposito_corrente_id
                    ) t
                    WHERE t.net_qta <> t.stored_qta
                       OR (t.net_qta > 0 AND t.conto_deposito_corrente_id IS NULL)
                ",
            ],
            'deposito_drift_pf_projection' => [
                'label' => 'Drift projection deposito prodotti finiti',
                'severity' => 'alta',
                'sql' => "
                    SELECT
                        t.id,
                        t.codice,
                        t.descrizione,
                        t.net_qta AS deposito_da_movimenti,
                        t.in_dep AS in_conto_deposito,
                        t.conto_deposito_corrente_id
                    FROM (
                        SELECT
                            pf.id,
                            pf.codice,
                            pf.descrizione,
                            GREATEST(0, COALESCE(SUM(CASE
                                WHEN md.tipo_movimento IN ('invio', 'rimando') THEN md.quantita
                                WHEN md.tipo_movimento IN ('vendita', 'reso') THEN -md.quantita
                                ELSE 0
                            END), 0)) AS net_qta,
                            COALESCE(pf.in_conto_deposito, 0) AS in_dep,
                            pf.conto_deposito_corrente_id
                        FROM prodotti_finiti pf
                        LEFT JOIN movimenti_deposito md ON md.prodotto_finito_id = pf.id
                        LEFT JOIN conti_deposito cd ON cd.id = md.conto_deposito_id
                        WHERE pf.deleted_at IS NULL
                          AND (cd.id IS NULL OR cd.stato IN ('attivo', 'parziale', 'scaduto'))
                        GROUP BY pf.id, pf.codice, pf.descrizione, pf.in_conto_deposito, pf.conto_deposito_corrente_id
                    ) t
                    WHERE (t.net_qta > 0 AND t.in_dep = 0)
                       OR (t.net_qta = 0 AND t.in_dep = 1)
                       OR (t.net_qta > 0 AND t.conto_deposito_corrente_id IS NULL)
                ",
            ],
            'split_figli_senza_padre' => [
                'label' => 'Split figli senza padre attivo',
                'severity' => 'media',
                'sql' => "
                    SELECT
                        f.id,
                        f.codice,
                        f.codice_base,
                        f.descrizione
                    FROM articoli f
                    LEFT JOIN articoli p ON p.codice = f.codice_base
                    WHERE f.deleted_at IS NULL
                      AND f.codice_base IS NOT NULL
                      AND TRIM(f.codice_base) <> ''
                      AND f.codice <> f.codice_base
                      AND (p.id IS NULL OR p.deleted_at IS NOT NULL)
                ",
            ],
        ];
    }

    /**
     * @param array<int, object> $rows
     */
    private function writeCsv(string $root, string $timestamp, string $key, array $rows): string
    {
        $filename = "{$key}_{$timestamp}.csv";
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

        $first = (array) $rows[0];
        fputcsv($handle, array_keys($first));

        foreach ($rows as $row) {
            fputcsv($handle, array_map([$this, 'normalizeValue'], (array) $row));
        }

        fclose($handle);

        return $filename;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }
}
