<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = DB::select("SHOW COLUMNS FROM categorie_merceologiche");
print_r($cols);
