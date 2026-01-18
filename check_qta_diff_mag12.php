<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$magazzinoId = 12;
$mssqlRows = DB::connection('mssql_prod')
    ->table('elenco_articoli_magazzino')
    ->where('id_magazzino', $magazzinoId)
    ->select('id', 'qta_residua', 'costo_unitario')
    ->get()
    ->keyBy('id');

$mysqlRows = DB::table('articoli')
    ->join('giacenze', 'articoli.id', '=', 'giacenze.articolo_id')
    ->where('articoli.categoria_merceologica_id', $magazzinoId)
    ->select('articoli.id', 'giacenze.quantita_residua', 'articoli.prezzo_acquisto')
    ->get()
    ->keyBy('id');

$diffCount = 0;
$diffVal = 0.0;
$examples = [];

foreach ($mssqlRows as $id => $row) {
    $mssqlQta = (int)($row->qta_residua ?? 0);
    $mssqlCosto = (float)($row->costo_unitario ?? 0);
    $mysqlQta = (int)($mysqlRows[$id]->quantita_residua ?? 0);
    $mysqlCosto = (float)($mysqlRows[$id]->prezzo_acquisto ?? 0);
    $mssqlVal = $mssqlQta * $mssqlCosto;
    $mysqlVal = $mysqlQta * $mysqlCosto;
    if (abs($mssqlVal - $mysqlVal) > 0.001) {
        $diffCount++;
        $diffVal += ($mssqlVal - $mysqlVal);
        if (count($examples) < 20) {
            $examples[] = [
                'id' => $id,
                'mssql_qta' => $mssqlQta,
                'mysql_qta' => $mysqlQta,
                'mssql_costo' => $mssqlCosto,
                'mysql_costo' => $mysqlCosto,
                'diff' => $mssqlVal - $mysqlVal,
            ];
        }
    }
}

echo "Magazzino {$magazzinoId} differenze: {$diffCount} articoli\n";
echo "Delta valore totale: " . number_format($diffVal, 2, ',', '.') . "\n";
echo "Esempi:\n";
foreach ($examples as $ex) {
    echo " - ID {$ex['id']}: MSSQL {$ex['mssql_qta']}x{$ex['mssql_costo']} vs MySQL {$ex['mysql_qta']}x{$ex['mysql_costo']} (diff " . number_format($ex['diff'], 2, ',', '.') . ")\n";
}

