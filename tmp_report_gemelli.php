<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// sedi principali
$sedi = [1 => 'CAVOUR/LECCO', 2 => 'JOLLY', 5 => 'ROMA'];

// mappa nome => sedi presenti
$rows = DB::select("SELECT nome, sede_id FROM categorie_merceologiche WHERE sede_id IN (1,2,5)");
$map = [];
foreach ($rows as $r) {
    $map[$r->nome][$r->sede_id] = true;
}

// report gemelli mancanti per sede
$report = [];
foreach ($map as $nome => $present) {
    foreach ($sedi as $sedeId => $label) {
        if (!isset($present[$sedeId])) {
            $report[$label][] = $nome;
        }
    }
}

foreach ($report as $label => $nomi) {
    sort($nomi);
    echo "\n--- Mancanti in {$label} ---\n";
    foreach ($nomi as $n) {
        echo "- {$n}\n";
    }
}
