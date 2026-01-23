<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$art = App\Models\Articolo::withoutGlobalScopes()
    ->with(['giacenza','giacenzePerSede'])
    ->where('codice', '5-35851')
    ->first();
if (!$art) {
    echo "NOT_FOUND\n";
    exit;
}
echo "id={$art->id} sede_id={$art->sede_id} in_vetrina=" . var_export($art->in_vetrina, true)
    . " conto_dep=" . var_export($art->conto_deposito_corrente_id, true)
    . " qta_dep=" . var_export($art->quantita_in_deposito, true) . "\n";
if ($art->giacenza) {
    echo "giacenza.qta_residua={$art->giacenza->quantita_residua}\n";
}
foreach ($art->giacenzePerSede as $gs) {
    echo "giacenze_sedi sede={$gs->sede_id} qta_residua={$gs->quantita_residua}\n";
}
