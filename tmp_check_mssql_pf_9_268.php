<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pf = DB::connection('mssql_prod')
    ->table('elenco_articoli_magazzino')
    ->where('id_magazzino', 9)
    ->where('carico', 268)
    ->first();
var_dump($pf?->id, $pf?->id_pf, $pf?->id_magazzino, $pf?->carico, $pf?->descrizione);
