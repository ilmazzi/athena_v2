<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sedi = DB::select("SELECT id, nome FROM sedi WHERE nome IN ('CAVOUR','MAZZINI','MONASTERO','JOLLY','ROMA') OR nome LIKE '%CAVOUR%' OR nome LIKE '%LECCO%' OR nome LIKE '%MAZZINI%' OR nome LIKE '%MONASTERO%' OR nome LIKE '%JOLLY%' OR nome LIKE '%ROMA%'");
print_r($sedi);

$leccoIds = [1];
$romaIds = [5];

$leccoCats = DB::select("SELECT id, nome FROM categorie_merceologiche WHERE sede_id IN (".implode(',', $leccoIds).") AND nome NOT LIKE 'Conto Deposito%'");
$romaCats = DB::select("SELECT id, nome FROM categorie_merceologiche WHERE sede_id IN (".implode(',', $romaIds).") AND nome NOT LIKE 'Conto Deposito%'");

echo "\n-- Lecco categories (non CD) --\n";
foreach ($leccoCats as $c) { echo "{$c->id} | {$c->nome}\n"; }

echo "\n-- Roma categories (non CD) --\n";
foreach ($romaCats as $c) { echo "{$c->id} | {$c->nome}\n"; }
