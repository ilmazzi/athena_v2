<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$art = App\Models\Articolo::where('codice', '9-268')->first();
var_dump($art?->id, $art?->codice, $art?->descrizione);
if ($art) {
    $pf = App\Models\ProdottoFinito::where('articolo_risultante_id', $art->id)->first();
    var_dump($pf?->id, $pf?->codice, $pf?->stato);
}
