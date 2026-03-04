<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$summary = DB::select("SELECT s.id AS sede_id, s.nome AS sede, COUNT(*) AS totale FROM categorie_merceologiche cm LEFT JOIN sedi s ON s.id = cm.sede_id GROUP BY s.id, s.nome ORDER BY totale DESC");
print_r($summary);

$topCats = DB::select("SELECT cm.id, cm.nome, cm.sede_id, s.nome AS sede FROM categorie_merceologiche cm LEFT JOIN sedi s ON s.id=cm.sede_id ORDER BY cm.id LIMIT 50");
print_r($topCats);
