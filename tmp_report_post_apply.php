<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$summary = DB::select("SELECT s.id AS sede_id, s.nome AS sede, COUNT(*) AS totale FROM categorie_merceologiche cm JOIN sedi s ON s.id = cm.sede_id GROUP BY s.id, s.nome ORDER BY s.id");
print_r($summary);

$missing = DB::select("SELECT base.nome, GROUP_CONCAT(base.sede_id ORDER BY base.sede_id) AS sedi_presenti\nFROM categorie_merceologiche base\nWHERE base.sede_id IN (1,2,3,4,5) AND base.nome NOT LIKE 'Conto Deposito%'\nGROUP BY base.nome\nHAVING COUNT(DISTINCT base.sede_id) < 5\nORDER BY base.nome");
print_r($missing);
