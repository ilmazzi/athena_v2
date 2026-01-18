<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "======================================================\n";
echo "VERIFICA ALLINEAMENTO DATI MSSQL\n";
echo "======================================================\n\n";

try {
    // 1. Conta articoli nella tabella mag_articoli
    echo "📊 CONTEGGIO ARTICOLI:\n";
    echo "----------------------\n";
    
    $countTabella = DB::connection('mssql_prod')->table('mag_articoli')->count();
    echo "Tabella mag_articoli:              {$countTabella}\n";
    
    $countVista = DB::connection('mssql_prod')->table('elenco_articoli_magazzino')->count();
    echo "Vista elenco_articoli_magazzino:   {$countVista}\n";
    
    $diff = abs($countTabella - $countVista);
    echo "Differenza:                        {$diff}\n\n";
    
    if ($diff === 0) {
        echo "✅ PERFETTO! I numeri coincidono!\n\n";
    } else {
        echo "⚠️  ATTENZIONE! C'è una differenza di {$diff} articoli\n\n";
    }
    
    // 2. Verifica articoli orfani (senza giacenza)
    echo "🔍 VERIFICA ORFANI:\n";
    echo "-------------------\n";
    
    $articoliSenzaGiacenza = DB::connection('mssql_prod')->select("
        SELECT COUNT(*) as count
        FROM mag_articoli a
        WHERE NOT EXISTS (
            SELECT 1 FROM mag_articoli_giacenze g 
            WHERE g.id_articolo = a.id
        )
    ");
    
    $orfani = $articoliSenzaGiacenza[0]->count;
    echo "Articoli senza giacenza (orfani):  {$orfani}\n";
    
    if ($orfani === 0) {
        echo "✅ Nessun orfano! Tutto pulito!\n\n";
    } else {
        echo "⚠️  Ci sono {$orfani} articoli senza giacenza\n\n";
    }
    
    // 3. Verifica giacenze duplicate
    echo "🔍 VERIFICA GIACENZE DUPLICATE:\n";
    echo "-------------------------------\n";
    
    $giacenzeDuplicate = DB::connection('mssql_prod')->select("
        SELECT COUNT(*) as count
        FROM (
            SELECT id_articolo, COUNT(*) as cnt
            FROM mag_articoli_giacenze
            GROUP BY id_articolo
            HAVING COUNT(*) > 1
        ) as duplicates
    ");
    
    $duplicati = $giacenzeDuplicate[0]->count;
    echo "Articoli con giacenze duplicate:   {$duplicati}\n";
    
    if ($duplicati === 0) {
        echo "✅ Nessun duplicato! Relazione 1:1 perfetta!\n\n";
    } else {
        echo "⚠️  Ci sono {$duplicati} articoli con più giacenze\n\n";
    }
    
    // 4. Verifica dati NULL critici nella vista
    echo "🔍 VERIFICA DATI NULL CRITICI:\n";
    echo "------------------------------\n";
    
    $checks = [
        'id IS NULL' => 'ID mancanti',
        'id_magazzino IS NULL' => 'Categoria mancante',
        'carico IS NULL' => 'Carico mancante',
        "descrizione IS NULL OR descrizione = ''" => 'Descrizione vuota',
    ];
    
    $problemi = [];
    foreach ($checks as $condition => $label) {
        $count = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->whereRaw($condition)
            ->count();
        
        if ($count > 0) {
            echo "⚠️  {$label}: {$count} articoli\n";
            $problemi[] = $label;
        }
    }
    
    if (empty($problemi)) {
        echo "✅ Tutti i campi critici sono popolati!\n\n";
    } else {
        echo "\n";
    }
    
    // 5. Verifica unicità codice (id_magazzino-carico)
    echo "🔍 VERIFICA UNICITÀ CODICI:\n";
    echo "---------------------------\n";
    
    $codiciDuplicati = DB::connection('mssql_prod')->select("
        SELECT COUNT(*) as count
        FROM (
            SELECT CAST(id_magazzino AS VARCHAR) + '-' + CAST(carico AS VARCHAR) as codice, 
                   COUNT(*) as cnt
            FROM elenco_articoli_magazzino
            WHERE id_magazzino IS NOT NULL AND carico IS NOT NULL
            GROUP BY CAST(id_magazzino AS VARCHAR) + '-' + CAST(carico AS VARCHAR)
            HAVING COUNT(*) > 1
        ) as dups
    ");
    
    $codiciDup = $codiciDuplicati[0]->count;
    echo "Codici duplicati (id_mag-carico):  {$codiciDup}\n";
    
    if ($codiciDup === 0) {
        echo "✅ Tutti i codici sono unici!\n\n";
    } else {
        echo "⚠️  Ci sono {$codiciDup} codici duplicati (necessario suffisso)\n\n";
    }
    
    // 6. Sample dati dalla vista
    echo "📦 SAMPLE DATI VISTA (Prime 5 righe):\n";
    echo "--------------------------------------\n";
    
    $sample = DB::connection('mssql_prod')
        ->table('elenco_articoli_magazzino')
        ->select(['id', 'id_magazzino', 'carico', 'descrizione', 'qta', 'qta_residua', 'costo_unitario'])
        ->limit(5)
        ->get();
    
    foreach ($sample as $art) {
        echo sprintf(
            "ID: %5d | Mag: %2d | Carico: %-8s | Desc: %-35s | Qta: %d/%d | Costo: %.2f\n",
            $art->id,
            $art->id_magazzino,
            $art->carico,
            substr($art->descrizione ?? 'N/A', 0, 35),
            $art->qta ?? 0,
            $art->qta_residua ?? 0,
            $art->costo_unitario ?? 0
        );
    }
    
    echo "\n\n";
    
    // 7. Confronto con MySQL attuale
    echo "🔄 CONFRONTO CON MYSQL:\n";
    echo "-----------------------\n";
    
    $mysqlCount = DB::table('articoli')->count();
    echo "Articoli in MySQL (athena_v2):     {$mysqlCount}\n";
    echo "Articoli in MSSQL (vista):         {$countVista}\n";
    echo "Gap da importare:                  " . ($countVista - $mysqlCount) . "\n\n";
    
    // 8. Verifica se ci sono ID in MSSQL che non sono in MySQL
    echo "🔍 VERIFICA NUOVI ARTICOLI:\n";
    echo "---------------------------\n";
    
    $maxIdMySQL = DB::table('articoli')->max('id') ?? 0;
    echo "Max ID in MySQL:                   {$maxIdMySQL}\n";
    
    $articoliNuovi = DB::connection('mssql_prod')
        ->table('elenco_articoli_magazzino')
        ->where('id', '>', $maxIdMySQL)
        ->count();
    
    echo "Articoli con ID > {$maxIdMySQL}:          {$articoliNuovi}\n\n";
    
    // 9. Riepilogo finale
    echo "======================================================\n";
    echo "📋 RIEPILOGO VERIFICA:\n";
    echo "======================================================\n\n";
    
    $allOk = ($diff === 0) && ($orfani === 0) && ($duplicati === 0) && empty($problemi);
    
    if ($allOk) {
        echo "✅ TUTTO OK! Database MSSQL è PULITO e PRONTO!\n\n";
        echo "Posso procedere con:\n";
        echo "  1. Migrazione COMPLETA (cancella MySQL e reimporta tutto)\n";
        echo "  2. Migrazione INCREMENTALE (aggiungi solo " . ($countVista - $mysqlCount) . " nuovi articoli)\n\n";
        echo "CONSIGLIO: Se il gap è piccolo (<1000), fai incrementale.\n";
        echo "           Se vuoi database 100% pulito, fai completa.\n";
    } else {
        echo "⚠️  CI SONO ALCUNI PROBLEMI:\n";
        if ($diff > 0) echo "  - Differenza tabella/vista\n";
        if ($orfani > 0) echo "  - Articoli orfani presenti\n";
        if ($duplicati > 0) echo "  - Giacenze duplicate\n";
        if (!empty($problemi)) {
            foreach ($problemi as $p) {
                echo "  - {$p}\n";
            }
        }
        echo "\nRisolvi questi problemi prima di procedere.\n";
    }
    
    echo "\n✅ VERIFICA COMPLETATA!\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRORE:\n";
    echo $e->getMessage() . "\n\n";
}
