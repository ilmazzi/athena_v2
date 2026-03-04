<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sedi = DB::select("SELECT id, nome FROM sedi WHERE nome LIKE '%Mazzini%' OR nome LIKE '%Lecco%'");
print_r($sedi);

$cats = DB::select("SELECT cm.id, cm.nome, cm.sede_id, s.nome AS sede FROM categorie_merceologiche cm JOIN sedi s ON s.id=cm.sede_id WHERE s.nome LIKE '%Mazzini%' OR s.nome LIKE '%Lecco%' ORDER BY s.nome, cm.nome");
print_r($cats);

$pf = DB::select("SELECT id, codice, magazzino_id FROM prodotti_finiti WHERE id=159 OR codice='9-158'");
print_r($pf);
