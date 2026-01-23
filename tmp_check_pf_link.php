<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pf = App\Models\ProdottoFinito::withTrashed()->first();
var_dump($pf?->id, $pf?->codice, $pf?->articolo_risultante_id);
if ($pf) {
    $art = App\Models\Articolo::where('prodotto_finito_id', $pf->id)->first();
    var_dump($art?->id, $art?->codice);
}
