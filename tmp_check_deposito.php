<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dep = App\Models\ContoDeposito::with('ddtInvio')->find(2);
var_dump($dep?->id, $dep?->stato, $dep?->ddt_invio_id, $dep?->ddtInvio?->id);
