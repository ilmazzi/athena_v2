<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$art = App\Models\Articolo::with(['giacenza','sede'])
    ->where('codice', 'like', '%35851%')
    ->first();

if (!$art) {
    echo "NOT_FOUND\n";
    exit;
}

$data = [
    'id' => $art->id,
    'codice' => $art->codice,
    'sede_id' => $art->sede_id,
    'sede' => $art->sede ? $art->sede->nome : null,
    'in_vetrina' => $art->in_vetrina,
    'conto_deposito_corrente_id' => $art->conto_deposito_corrente_id,
    'quantita_in_deposito' => $art->quantita_in_deposito,
    'giacenza_residua' => $art->giacenza ? $art->giacenza->quantita_residua : null,
];

echo json_encode($data, JSON_PRETTY_PRINT);
