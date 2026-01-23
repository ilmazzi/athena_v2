<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$artId = 49199;
$pf = App\Models\ProdottoFinito::withTrashed()->where('articolo_risultante_id', $artId)->first();
var_dump($pf?->id, $pf?->codice, $pf?->stato, $pf?->deleted_at);
