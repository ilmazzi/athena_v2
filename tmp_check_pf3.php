<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pfs = App\Models\ProdottoFinito::withTrashed()
    ->where('codice','like','%9-268%')
    ->limit(5)
    ->get(['id','codice','stato','deleted_at']);
var_dump($pfs->toArray());
