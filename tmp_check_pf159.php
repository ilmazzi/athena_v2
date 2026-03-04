<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pf = DB::selectOne("SELECT id, codice, magazzino_id FROM prodotti_finiti WHERE id=159");
var_export($pf);

echo "\n";
if ($pf) {
    $cat = DB::selectOne("SELECT id, nome, sede_id FROM categorie_merceologiche WHERE id = ?", [$pf->magazzino_id]);
    var_export($cat);
    echo "\n";
    if ($cat && $cat->sede_id) {
        $sede = DB::selectOne("SELECT id, nome FROM sedi WHERE id = ?", [$cat->sede_id]);
        var_export($sede);
        echo "\n";
    }
}
