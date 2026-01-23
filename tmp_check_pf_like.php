<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$arts = App\Models\Articolo::withTrashed()
    ->where('codice', 'like', '%9-268%')
    ->limit(5)
    ->get(['id','codice','deleted_at']);
var_dump($arts->toArray());
