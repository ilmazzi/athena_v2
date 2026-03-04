<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$matrix = DB::select("SELECT sede_id, GROUP_CONCAT(id ORDER BY id) AS ids FROM categorie_merceologiche GROUP BY sede_id ORDER BY sede_id");
print_r($matrix);

// gemelli mancanti: per ogni nome, mostra sedi mancanti
$missing = DB::select("SELECT base.nome, GROUP_CONCAT(base.sede_id ORDER BY base.sede_id) AS sedi_presenti\nFROM categorie_merceologiche base\nWHERE base.sede_id IS NOT NULL\nGROUP BY base.nome\nHAVING COUNT(DISTINCT base.sede_id) < (SELECT COUNT(*) FROM sedi WHERE id IN (1,2,5))\nORDER BY base.nome");
print_r($missing);
