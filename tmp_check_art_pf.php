<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$art = App\Models\Articolo::withTrashed()->find(49199);
var_dump($art?->id, $art?->codice, $art?->prodotto_finito_id);
