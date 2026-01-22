<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::where('email','d.mazzitelli@depascalisgioielli.it')->first();
var_dump($user?->roles->pluck('name'), $user?->can('conti_deposito.manage'), $user?->sede_id);
