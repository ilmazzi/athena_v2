<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// sample check for a few magazzino 9 articoli and matching PF by id_pf
$rows = DB::connection('mssql_prod')
    ->table('elenco_articoli_magazzino')
    ->where('id_magazzino', 9)
    ->orderBy('carico')
    ->limit(10)
    ->get(['id','id_pf','id_magazzino','carico']);

$out = [];
foreach ($rows as $r) {
    $codice = $r->id_magazzino . '-' . $r->carico;
    $pf = App\Models\ProdottoFinito::withTrashed()->find($r->id_pf);
    $out[] = [
        'mssql_id' => $r->id,
        'mssql_id_pf' => $r->id_pf,
        'mssql_codice' => $codice,
        'mysql_pf_id' => $pf?->id,
        'mysql_pf_codice' => $pf?->codice,
    ];
}
var_dump($out);
