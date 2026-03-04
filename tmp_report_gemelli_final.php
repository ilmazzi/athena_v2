<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sedi = [
    'LECCO' => 1,
    'JOLLY' => 2,
    'MONASTERO' => 3,
    'MAZZINI' => 4,
    'ROMA' => 5,
];

$leccoCats = DB::select("SELECT nome FROM categorie_merceologiche WHERE sede_id = 1 AND nome NOT LIKE 'Conto Deposito%'");
$romaCats = DB::select("SELECT nome FROM categorie_merceologiche WHERE sede_id = 5 AND nome NOT LIKE 'Conto Deposito%'");

$leccoNames = array_map(fn($r) => $r->nome, $leccoCats);
$romaNames = array_map(fn($r) => $r->nome, $romaCats);

$targets = [
    // Lecco -> Roma, Mazzini, Monastero, Jolly
    1 => [5,4,3,2],
    // Roma -> Lecco, Mazzini, Monastero
    5 => [1,4,3],
];

$existing = DB::select("SELECT nome, sede_id FROM categorie_merceologiche WHERE sede_id IN (1,2,3,4,5)");
$exists = [];
foreach ($existing as $r) {
    $exists[$r->sede_id][$r->nome] = true;
}

$report = [];

// Lecco -> other
foreach ($targets[1] as $dest) {
    foreach ($leccoNames as $nome) {
        if (!isset($exists[$dest][$nome])) {
            $report[$dest][] = $nome;
        }
    }
}

// Roma -> other
foreach ($targets[5] as $dest) {
    foreach ($romaNames as $nome) {
        if (!isset($exists[$dest][$nome])) {
            $report[$dest][] = $nome;
        }
    }
}

foreach ($report as $destId => $names) {
    sort($names);
    $label = array_search($destId, $sedi, true);
    echo "\n=== Da creare in {$label} (sede_id={$destId}) ===\n";
    foreach ($names as $n) {
        echo "- {$n}\n";
    }
}
