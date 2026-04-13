<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use RuntimeException;

class SyncMagazzino8FromCsv extends Command
{
    protected $signature = 'sync:magazzino8-from-csv
                            {--csv=C:\Users\dmazz\Desktop\elenco_articoli_magazzino.csv : Percorso CSV locale}
                            {--database=athena_v2_ok : Database MySQL target}
                            {--magazzino=8 : Numero magazzino da sincronizzare}
                            {--sede-id=1 : Sede target}
                            {--categoria-id= : Categoria target (default = magazzino)}
                            {--apply : Applica le modifiche su athena_v2_ok}
                            {--backup-dir=magazzino_sync : Cartella sotto storage/app per backup e report}';

    protected $description = 'Sincronizza i dati documentali/carico di un magazzino dal CSV locale verso athena_v2_ok:3307';

    private array $supplierCache = [];

    public function handle(): int
    {
        $csvPath = (string) $this->option('csv');
        $apply = (bool) $this->option('apply');
        $magazzino = (int) $this->option('magazzino');
        $sedeId = (int) $this->option('sede-id');
        $categoriaId = $this->option('categoria-id') !== null && $this->option('categoria-id') !== ''
            ? (int) $this->option('categoria-id')
            : $magazzino;
        $backupDir = trim((string) $this->option('backup-dir'));
        $root = storage_path('app/' . ($backupDir !== '' ? $backupDir : 'magazzino_sync'));

        if (!is_file($csvPath)) {
            $this->error("CSV non trovato: {$csvPath}");
            return self::FAILURE;
        }

        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            $this->error("Impossibile creare directory: {$root}");
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $pdo = $this->connectAuditDb();

        $csvRows = $this->loadCsvRows($csvPath, $magazzino);
        [$articleMap, $existingRows, $missingArticles] = $this->loadMagazzinoRows($pdo, $magazzino);

        $docGroups = [];
        $articlePlans = [];
        $skipped = [];

        foreach ($csvRows as $carico => $csv) {
            $article = $articleMap[$carico] ?? null;
            if (!$article) {
                $missingArticles[] = [
                    'carico' => $carico,
                    'codice' => $magazzino . '-' . $carico,
                    'reason' => 'articolo_non_trovato',
                ];
                continue;
            }

            $supplierResolution = $this->resolveSupplier($pdo, $csv['fornitore_csv'], $apply);
            $supplierId = $supplierResolution['supplier_id'];
            $supplierKey = $supplierResolution['supplier_key'];

            $docKey = implode('|', [
                $supplierKey,
                $csv['numero_documento_csv'],
                $csv['data_documento_csv'],
            ]);

            $docGroups[$docKey] ??= [
                'supplier_id' => $supplierId,
                'supplier_key' => $supplierKey,
                'numero' => $csv['numero_documento_csv'],
                'data_documento' => $csv['data_documento_csv'],
                'rows' => [],
            ];

            $existing = $existingRows[$article['id']] ?? null;
            $plan = [
                'articolo_id' => $article['id'],
                'codice' => $article['codice'],
                'carico' => $carico,
                'csv_descrizione' => $csv['descrizione_csv'],
                'csv_fornitore' => $csv['fornitore_csv'],
                'csv_numero' => $csv['numero_documento_csv'],
                'csv_data' => $csv['data_documento_csv'],
                'csv_costo_unitario' => $csv['costo_unitario_csv'],
                'csv_quantita' => $csv['quantita_csv'],
                'existing_carico_dettaglio_id' => $existing['carico_dettaglio_id'] ?? null,
                'existing_tipo' => $existing['existing_tipo'] ?? 'none',
                'existing_documento' => $existing['existing_documento'] ?? null,
                'existing_data_documento' => $existing['existing_data_documento'] ?? null,
                'existing_supplier' => $existing['existing_supplier'] ?? null,
                'existing_numero_seriale' => $existing['numero_seriale'] ?? null,
                'existing_ean' => $existing['ean'] ?? null,
                'existing_referenza_fornitore' => $existing['referenza_fornitore'] ?? null,
                'existing_quantita' => $existing['carico_quantita'] ?? null,
                'existing_prezzo_unitario' => $existing['carico_prezzo_unitario'] ?? null,
                'new_supplier_id' => $supplierId,
                'doc_key' => $docKey,
            ];

            $articlePlans[] = $plan;
            $docGroups[$docKey]['rows'][] = $plan;
        }

        $backup = $this->buildBackup($pdo, array_column($articlePlans, 'articolo_id'));
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . "backup_mag{$magazzino}_{$timestamp}.json",
            json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $summary = [
            'csv_rows' => count($csvRows),
            'article_plans' => count($articlePlans),
            'doc_groups' => count($docGroups),
            'missing_articles' => count($missingArticles),
            'skipped' => count($skipped),
            'mode' => $apply ? 'apply' : 'dry-run',
        ];

        file_put_contents(
            $root . DIRECTORY_SEPARATOR . "summary_mag{$magazzino}_{$timestamp}.json",
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['CSV rows', $summary['csv_rows']],
                ['Articoli matchati', $summary['article_plans']],
                ['Gruppi documento canonici', $summary['doc_groups']],
                ['Articoli non trovati', $summary['missing_articles']],
                ['Righe saltate', $summary['skipped']],
                ['Modalita', $summary['mode']],
            ]
        );

        if (!$apply) {
            $this->line('Dry-run completato. Backup e summary salvati in: ' . $root);
            return self::SUCCESS;
        }

        $this->applySync($pdo, $docGroups, $articlePlans, $magazzino, $sedeId, $categoriaId);
        $this->line("Sync applicata su athena_v2_ok:3307 per magazzino {$magazzino}");
        return self::SUCCESS;
    }

    private function connectAuditDb(): PDO
    {
        $host = env('AUDIT_DB_HOST', '127.0.0.1');
        $port = env('AUDIT_DB_PORT', '3307');
        $database = (string) $this->option('database');
        $username = env('AUDIT_DB_USERNAME', 'athena');
        $password = env('AUDIT_DB_PASSWORD', '');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;
    }

    private function loadCsvRows(string $csvPath, int $magazzino): array
    {
        $rows = [];
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            throw new RuntimeException("Impossibile aprire CSV: {$csvPath}");
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if ((int) ($row[1] ?? 0) !== $magazzino) {
                continue;
            }

            $carico = (int) ($row[2] ?? 0);
            $rows[$carico] = [
                'carico' => $carico,
                'descrizione_csv' => trim((string) ($row[4] ?? '')),
                'fornitore_csv' => trim((string) ($row[13] ?? '')),
                'numero_documento_csv' => trim((string) ($row[21] ?? '')),
                'data_documento_csv' => trim((string) ($row[22] ?? '')),
                'quantita_csv' => (float) str_replace(',', '.', (string) ($row[23] ?? '0')),
                'costo_unitario_csv' => (float) str_replace(',', '.', (string) ($row[24] ?? '0')),
                'costo_totale_csv' => (float) str_replace(',', '.', (string) ($row[25] ?? '0')),
            ];
        }

        fclose($handle);
        ksort($rows);

        return $rows;
    }

    private function loadMagazzinoRows(PDO $pdo, int $magazzino): array
    {
        $articles = [];
        $existingRows = [];
        $missing = [];

        $articleSql = "
            SELECT a.id, a.codice, a.descrizione, a.prezzo_acquisto
            FROM articoli a
            WHERE a.deleted_at IS NULL
              AND a.codice LIKE '{$magazzino}-%'
            ORDER BY a.codice
        ";

        foreach ($pdo->query($articleSql) as $row) {
            if (!preg_match('/^' . preg_quote((string) $magazzino, '/') . '-0*(\d+)$/', (string) $row['codice'], $m)) {
                continue;
            }
            $carico = (int) $m[1];
            $articles[$carico] = $row;
        }

        $detailSql = "
            SELECT
                a.id AS articolo_id,
                cd.id AS carico_dettaglio_id,
                CASE
                    WHEN cd.ddt_id IS NOT NULL THEN 'ddt'
                    WHEN cd.fattura_id IS NOT NULL THEN 'fattura'
                    ELSE 'none'
                END AS existing_tipo,
                COALESCE(d.numero, f.numero) AS existing_documento,
                COALESCE(d.data_documento, f.data_documento) AS existing_data_documento,
                COALESCE(fd.ragione_sociale, ff.ragione_sociale) AS existing_supplier,
                cd.referenza_fornitore,
                cd.numero_seriale,
                cd.ean,
                cd.quantita AS carico_quantita,
                cd.prezzo_unitario AS carico_prezzo_unitario
            FROM articoli a
            LEFT JOIN carico_dettagli cd ON cd.articolo_id = a.id
            LEFT JOIN ddt d ON d.id = cd.ddt_id
            LEFT JOIN fatture f ON f.id = cd.fattura_id
            LEFT JOIN fornitori fd ON fd.id = d.fornitore_id
            LEFT JOIN fornitori ff ON ff.id = f.fornitore_id
            WHERE a.deleted_at IS NULL
              AND a.codice LIKE '{$magazzino}-%'
        ";

        foreach ($pdo->query($detailSql) as $row) {
            $existingRows[(int) $row['articolo_id']] = $row;
        }

        return [$articles, $existingRows, $missing];
    }

    private function resolveSupplier(PDO $pdo, string $supplier, bool $allowCreate): array
    {
        $normalized = $this->normalizeSupplier($supplier);

        if ($normalized === '') {
            return [
                'supplier_id' => null,
                'supplier_key' => 'NULL_SUPPLIER',
            ];
        }

        if ($this->supplierCache === []) {
            foreach ($pdo->query("SELECT id, ragione_sociale FROM fornitori WHERE deleted_at IS NULL") as $row) {
                $this->supplierCache[$this->normalizeSupplier((string) $row['ragione_sociale'])] = (int) $row['id'];
            }
        }

        $aliases = [
            'POMELLATO' => 'POMELLATO S.P.A.',
            'DODO' => 'DODO SRL',
            'DOLMEN' => 'DOLMEN S.R.L.',
            'WOLF' => 'WOLF 1834, LTD',
            'CALEGARO' => 'CALEGARO Fratelli srl',
            'CASSETTI' => 'CASSETTI S.P.A.',
            'SILVART' => 'SILVART ARGENTERIE DI G.SILVA',
            'FERREIRA' => 'Ferreira Marques',
            'SARA' => 'SARA S.R.L.',
            'DALIANA ANDREA' => 'DALIANA ANDREA & C. S.P.A.',
            'GREGGIO' => 'Greggio argento',
        ];

        if (isset($aliases[$normalized])) {
            $normalized = $this->normalizeSupplier($aliases[$normalized]);
        }

        if (isset($this->supplierCache[$normalized])) {
            return [
                'supplier_id' => $this->supplierCache[$normalized],
                'supplier_key' => 'SUPPLIER_' . $this->supplierCache[$normalized],
            ];
        }

        if ($allowCreate) {
            $insert = $pdo->prepare("
                INSERT INTO fornitori (
                    ragione_sociale,
                    attivo,
                    created_at,
                    updated_at
                ) VALUES (
                    :ragione_sociale,
                    1,
                    NOW(),
                    NOW()
                )
            ");
            $insert->execute([
                'ragione_sociale' => trim($supplier),
            ]);

            $id = (int) $pdo->lastInsertId();
            $this->supplierCache[$normalized] = $id;

            return [
                'supplier_id' => $id,
                'supplier_key' => 'SUPPLIER_' . $id,
            ];
        }

        return [
            'supplier_id' => null,
            'supplier_key' => 'NEW_SUPPLIER_' . $normalized,
        ];
    }

    private function normalizeSupplier(string $supplier): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $supplier)));
    }

    private function buildBackup(PDO $pdo, array $articleIds): array
    {
        if (empty($articleIds)) {
            return ['articoli' => [], 'carico_dettagli' => [], 'ddt' => [], 'fatture' => []];
        }

        $in = implode(',', array_map('intval', $articleIds));

        return [
            'articoli' => $pdo->query("SELECT * FROM articoli WHERE id IN ({$in})")->fetchAll(),
            'carico_dettagli' => $pdo->query("SELECT * FROM carico_dettagli WHERE articolo_id IN ({$in})")->fetchAll(),
            'ddt' => $pdo->query("
                SELECT DISTINCT d.*
                FROM ddt d
                INNER JOIN carico_dettagli cd ON cd.ddt_id = d.id
                WHERE cd.articolo_id IN ({$in})
            ")->fetchAll(),
            'fatture' => $pdo->query("
                SELECT DISTINCT f.*
                FROM fatture f
                INNER JOIN carico_dettagli cd ON cd.fattura_id = f.id
                WHERE cd.articolo_id IN ({$in})
            ")->fetchAll(),
        ];
    }

    private function applySync(PDO $pdo, array $docGroups, array $articlePlans, int $magazzino, int $sedeId, int $categoriaId): void
    {
        $pdo->beginTransaction();

        try {
            $docIdByKey = [];
            foreach ($docGroups as $key => $group) {
                $docIdByKey[$key] = $this->findOrCreateCanonicalDdt($pdo, $group, $magazzino, $sedeId, $categoriaId);
            }

            $updateArticle = $pdo->prepare("
                UPDATE articoli
                SET descrizione = :descrizione,
                    prezzo_acquisto = :prezzo_acquisto,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $updateCarico = $pdo->prepare("
                UPDATE carico_dettagli
                SET ddt_id = :ddt_id,
                    fattura_id = NULL,
                    descrizione = :descrizione,
                    prezzo_unitario = :prezzo_unitario,
                    prezzo_totale = :prezzo_totale,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $insertCarico = $pdo->prepare("
                INSERT INTO carico_dettagli (
                    ddt_id,
                    fattura_id,
                    articolo_id,
                    referenza_fornitore,
                    descrizione,
                    quantita,
                    numero_seriale,
                    ean,
                    prezzo_unitario,
                    prezzo_totale,
                    verificato,
                    creato_nuovo,
                    created_at,
                    updated_at
                ) VALUES (
                    :ddt_id,
                    NULL,
                    :articolo_id,
                    :referenza_fornitore,
                    :descrizione,
                    :quantita,
                    :numero_seriale,
                    :ean,
                    :prezzo_unitario,
                    :prezzo_totale,
                    1,
                    0,
                    NOW(),
                    NOW()
                )
            ");

            foreach ($articlePlans as $plan) {
                $ddtId = $docIdByKey[$plan['doc_key']];

                $updateArticle->execute([
                    'id' => $plan['articolo_id'],
                    'descrizione' => $plan['csv_descrizione'],
                    'prezzo_acquisto' => $plan['csv_costo_unitario'],
                ]);

                if ($plan['existing_carico_dettaglio_id']) {
                    $quantita = $plan['existing_quantita'] !== null ? (int) $plan['existing_quantita'] : max(1, (int) $plan['csv_quantita']);
                    $updateCarico->execute([
                        'id' => $plan['existing_carico_dettaglio_id'],
                        'ddt_id' => $ddtId,
                        'descrizione' => $plan['csv_descrizione'],
                        'prezzo_unitario' => $plan['csv_costo_unitario'],
                        'prezzo_totale' => round($quantita * $plan['csv_costo_unitario'], 2),
                    ]);
                    continue;
                }

                $quantita = max(1, (int) $plan['csv_quantita']);
                $insertCarico->execute([
                    'ddt_id' => $ddtId,
                    'articolo_id' => $plan['articolo_id'],
                    'referenza_fornitore' => $plan['existing_referenza_fornitore'],
                    'descrizione' => $plan['csv_descrizione'],
                    'quantita' => $quantita,
                    'numero_seriale' => $plan['existing_numero_seriale'],
                    'ean' => $plan['existing_ean'],
                    'prezzo_unitario' => $plan['csv_costo_unitario'],
                    'prezzo_totale' => round($quantita * $plan['csv_costo_unitario'], 2),
                ]);
            }

            $this->refreshMagazzinoDdtTotals($pdo, $magazzino);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function findOrCreateCanonicalDdt(PDO $pdo, array $group, int $magazzino, int $sedeId, int $categoriaId): int
    {
        if ($group['supplier_id'] === null) {
            $select = $pdo->prepare("
                SELECT id
                FROM ddt
                WHERE numero = :numero
                  AND data_documento = :data_documento
                  AND fornitore_id IS NULL
                  AND COALESCE(magazzino_logico, :magazzino_logico) = :magazzino_logico
                  AND COALESCE(categoria_id, :categoria_id) = :categoria_id
                  AND COALESCE(sede_id, :sede_id) = :sede_id
                  AND deleted_at IS NULL
                ORDER BY id
                LIMIT 1
            ");

            $select->execute([
                'numero' => $group['numero'],
                'data_documento' => $group['data_documento'],
                'magazzino_logico' => $magazzino,
                'categoria_id' => $categoriaId,
                'sede_id' => $sedeId,
            ]);
        } else {
            $select = $pdo->prepare("
                SELECT id
                FROM ddt
                WHERE numero = :numero
                  AND data_documento = :data_documento
                  AND fornitore_id = :fornitore_id
                  AND COALESCE(magazzino_logico, :magazzino_logico) = :magazzino_logico
                  AND COALESCE(categoria_id, :categoria_id) = :categoria_id
                  AND COALESCE(sede_id, :sede_id) = :sede_id
                  AND deleted_at IS NULL
                ORDER BY id
                LIMIT 1
            ");

            $select->execute([
                'numero' => $group['numero'],
                'data_documento' => $group['data_documento'],
                'fornitore_id' => $group['supplier_id'],
                'magazzino_logico' => $magazzino,
                'categoria_id' => $categoriaId,
                'sede_id' => $sedeId,
            ]);
        }

        $found = $select->fetchColumn();
        if ($found) {
            return (int) $found;
        }

        $insert = $pdo->prepare("
            INSERT INTO ddt (
                numero,
                data_documento,
                anno,
                tipo_documento,
                fornitore_id,
                stato,
                tipo_carico,
                data_carico,
                sede_id,
                categoria_id,
                magazzino_logico,
                quantita_totale,
                numero_articoli,
                created_at,
                updated_at
            ) VALUES (
                :numero,
                :data_documento,
                :anno,
                'fornitore',
                :fornitore_id,
                'caricato',
                'manuale',
                :data_documento,
                :sede_id,
                :categoria_id,
                :magazzino_logico,
                0,
                0,
                NOW(),
                NOW()
            )
        ");

        $insert->execute([
            'numero' => $group['numero'],
            'data_documento' => $group['data_documento'],
            'anno' => (int) substr($group['data_documento'], 0, 4),
            'fornitore_id' => $group['supplier_id'],
            'sede_id' => $sedeId,
            'categoria_id' => $categoriaId,
            'magazzino_logico' => $magazzino,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function refreshMagazzinoDdtTotals(PDO $pdo, int $magazzino): void
    {
        $sql = "
            UPDATE ddt d
            INNER JOIN (
                SELECT
                    cd.ddt_id,
                    COALESCE(SUM(cd.quantita), 0) AS quantita_totale,
                    COUNT(*) AS numero_articoli
                FROM carico_dettagli cd
                INNER JOIN articoli a ON a.id = cd.articolo_id
                WHERE a.deleted_at IS NULL
                  AND a.codice LIKE '{$magazzino}-%'
                  AND cd.ddt_id IS NOT NULL
                GROUP BY cd.ddt_id
            ) x ON x.ddt_id = d.id
            SET d.quantita_totale = x.quantita_totale,
                d.numero_articoli = x.numero_articoli,
                d.updated_at = NOW()
        ";

        $pdo->exec($sql);
    }
}
