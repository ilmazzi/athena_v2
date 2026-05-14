<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Riconcilia le giacenze del nuovo sistema con l'inventario ufficiale.
 *
 * Fonte di verità: inventariati.rpt (inventario ufficiale certificato)
 *
 * Regole:
 *  - Carico multiplo ({mag}-{num}-{n}): SKIP — trattare separatamente
 *  - Codice presente in inventariati.rpt:
 *      · soft-deleted nel nuovo → RESTORE + setta qta corretta
 *      · esiste ma qta sbagliata → FIX QTA
 *      · già corretto → OK
 *  - Codice NON in inventariati.rpt:
 *      · numero > CUTOFF (nuovo carico nel nuovo sistema):
 *          - soft-deleted → RESTORE + qta originale di carico
 *          - non deleted  → OK (lascia invariato)
 *      · numero ≤ CUTOFF (era nell'old DB ma non inventariato):
 *          - già soft-deleted → OK (corretto)
 *          - non deleted      → SOFT-DELETE
 *
 * Cutoff per magazzino (ultimo carico registrato nell'old DB):
 *   MAG1=746  MAG2=64316  MAG3=14272  MAG4=11611  MAG5=36245  MAG6=28516
 *   MAG7=10373 MAG8=1302  MAG9=271    MAG10=18302 MAG11=4371  MAG12=13921
 *   MAG13=1810 MAG14=501  MAG15=1020  MAG16=812   MAG17=1301  MAG18=1663
 *   MAG19=3351 MAG20=24838 MAG21=2100 MAG22=8
 */
class RiconciliaGiacenzeOldDb extends Command
{
    protected $signature = 'magazzino:riconcilia-giacenze-old-db
                            {--inventariati= : Percorso file inventariati.rpt (default: storage/app/inventariati.rpt)}
                            {--magazzini=    : Limita ai soli magazzini specificati, es. --magazzini=1,2,3,4,5,6,7}
                            {--export=       : Esporta tutte le azioni in un file CSV per revisione}
                            {--dry-run       : Mostra cosa verrebbe fatto senza applicare modifiche}';

    protected $description = 'Riconcilia giacenze del nuovo DB con l\'inventario ufficiale (inventariati.rpt)';

    /**
     * Magazzini esclusi dalla riconciliazione automatica — gestiti separatamente.
     * MAG9, MAG22 = PF (inventariati per componenti)
     * MAG8 = escluso per gestione separata
     */
    private const MAG_ESCLUSI = [8, 9, 22];

    private ?array $magFiltroAttivo = null;

    /** Ultimo numero progressivo per magazzino nell'old DB. */
    private const CUTOFF = [
        1 => 746,    2 => 64316,  3 => 14272,  4 => 11611,  5 => 36245,
        6 => 28516,  7 => 10373,  8 => 1302,   9 => 271,   10 => 18302,
       11 => 4371,  12 => 13921, 13 => 1810,  14 => 501,   15 => 1020,
       16 => 812,   17 => 1301,  18 => 1663,  19 => 3351,  20 => 24838,
       21 => 2100,  22 => 8,
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $pathInv = $this->option('inventariati') ?? storage_path('app/inventariati.rpt');

        if (!file_exists($pathInv)) {
            $this->error("File non trovato: {$pathInv}");
            $this->line("Copia inventariati.rpt in storage/app/ oppure passa il percorso con --inventariati=");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('MODALITÀ DRY-RUN — nessuna modifica verrà applicata.');
        } else {
            if (!$this->confirm('Stai per modificare giacenze e articoli. Continuare?')) {
                return self::SUCCESS;
            }
        }

        $this->info('Caricamento inventariati.rpt...');
        $inventariati = $this->loadRpt($pathInv);
        $this->line("  Articoli inventariati: " . count($inventariati));

        // Filtro magazzini opzionale
        $magFiltro = null;
        if ($this->option('magazzini')) {
            $magFiltro = array_map('intval', explode(',', $this->option('magazzini')));
            $this->magFiltroAttivo = $magFiltro;
            $this->info('Filtro magazzini: MAG ' . implode(', ', $magFiltro));
        }

        $this->info('');
        $this->info('Analisi in corso...');
        [$stats, $azioni] = $this->analizza($inventariati, $magFiltro);

        $this->printStats($stats);
        $this->printSample($azioni);

        // Export CSV se richiesto
        if ($exportPath = $this->option('export')) {
            $this->exportCsv($azioni, $exportPath);
        }

        if ($dryRun) {
            $this->info('');
            $this->info('Dry-run completato. Riesegui senza --dry-run per applicare.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('Applicazione modifiche...');
        $this->esegui($azioni);

        $this->info('');
        $this->info('Riconciliazione completata.');
        return self::SUCCESS;
    }

    /**
     * Legge inventariati.rpt → [ codice => qta_residua ]
     * Formato: header su riga 1-2, poi "{codice}   {qta}" fino alla riga sommario.
     */
    private function loadRpt(string $path): array
    {
        $result = [];
        $handle = fopen($path, 'r');
        $skip = 2; // salta header + separatore
        while (($line = fgets($handle)) !== false) {
            if ($skip > 0) { $skip--; continue; }
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '(')) { continue; }
            // Formato: codice seguito da spazi e poi il numero
            if (preg_match('/^(\S+)\s+(\d+)$/', $line, $m)) {
                $result[$m[1]] = (int) $m[2];
            }
        }
        fclose($handle);
        return $result;
    }

    private function analizza(array $inventariati, ?array $magFiltro = null): array
    {
        $stats = [
            'inv_restore'    => 0,  // in inventario + soft-deleted → restore + qta corretta
            'inv_fix_qta'    => 0,  // in inventario + qta sbagliata → fix qta
            'new_restore'    => 0,  // nuovo carico soft-deleted → restore + qta originale
            'old_delete'     => 0,  // old carico non in inventario + non deleted → soft-delete
            'ok'             => 0,  // già corretto
            'skip_multiplo'  => 0,  // carico multiplo → skip (trattare a parte)
            'skip_pf_mag'    => 0,  // MAG9/MAG22 PF → skip (inventariati per componenti)
            'skip_pf'        => 0,  // articoloRisultante PF annullato → skip
            'skip_inesistente'=> 0, // placeholder → skip
        ];

        $azioni = [];

        $articoli = DB::table('articoli as a')
            ->leftJoin('giacenze as g', 'g.articolo_id', '=', 'a.id')
            ->select(
                'a.id', 'a.codice', 'a.deleted_at', 'a.descrizione',
                'a.prodotto_finito_id',
                'g.id as giacenza_id', 'g.quantita_residua',
                'g.quantita as quantita_originale',
                'a.categoria_merceologica_id', 'a.sede_id'
            )
            ->get();

        foreach ($articoli as $art) {
            // Skip articoli risultanti di PF annullati
            if ($art->prodotto_finito_id !== null && $art->deleted_at !== null) {
                $stats['skip_pf']++;
                continue;
            }

            // Skip placeholder
            if ($art->descrizione === 'INESISTENTE') {
                $stats['skip_inesistente']++;
                continue;
            }

            $codice    = $art->codice;
            $isDeleted = $art->deleted_at !== null;
            $qtaAttuale = (int) ($art->quantita_residua ?? 0);

            // Skip carichi multipli ({mag}-{num}-{n})
            if (preg_match('/^\d+-\d+-\d+/', $codice)) {
                $stats['skip_multiplo']++;
                continue;
            }

            $mag = $this->parseMag($codice);
            $num = $this->parseNum($codice);

            // Skip magazzini esclusi (MAG8, MAG9, MAG22): gestiti separatamente
            if ($mag !== null && in_array($mag, self::MAG_ESCLUSI)) {
                $stats['skip_pf_mag']++;
                continue;
            }

            // Skip se non nel filtro magazzini opzionale
            if ($magFiltro !== null && ($mag === null || !in_array($mag, $magFiltro))) {
                $stats['skip_pf_mag']++;
                continue;
            }
            $isInInventario = isset($inventariati[$codice]);
            $cutoff = ($mag !== null) ? (self::CUTOFF[$mag] ?? null) : null;
            $isNewCarico = ($cutoff !== null && $num !== null && $num > $cutoff);

            if ($isInInventario) {
                $qtaTarget = $inventariati[$codice];
                if ($isDeleted) {
                    $stats['inv_restore']++;
                    $azioni[] = [
                        'id' => $art->id, 'tipo' => 'inv_restore',
                        'codice' => $codice, 'qta_target' => $qtaTarget,
                        'qta_attuale' => $qtaAttuale, 'giacenza_id' => $art->giacenza_id,
                        'cat_id' => $art->categoria_merceologica_id, 'sede_id' => $art->sede_id,
                    ];
                } elseif ($qtaAttuale !== $qtaTarget) {
                    $stats['inv_fix_qta']++;
                    $azioni[] = [
                        'id' => $art->id, 'tipo' => 'inv_fix_qta',
                        'codice' => $codice, 'qta_target' => $qtaTarget,
                        'qta_attuale' => $qtaAttuale, 'giacenza_id' => $art->giacenza_id,
                        'cat_id' => $art->categoria_merceologica_id, 'sede_id' => $art->sede_id,
                    ];
                } else {
                    $stats['ok']++;
                }
            } elseif ($isNewCarico) {
                if ($isDeleted) {
                    $qtaOriginale = max(1, (int) ($art->quantita_originale ?? 1));
                    $stats['new_restore']++;
                    $azioni[] = [
                        'id' => $art->id, 'tipo' => 'new_restore',
                        'codice' => $codice, 'qta_target' => $qtaOriginale,
                        'qta_attuale' => $qtaAttuale, 'giacenza_id' => $art->giacenza_id,
                        'cat_id' => $art->categoria_merceologica_id, 'sede_id' => $art->sede_id,
                    ];
                } else {
                    $stats['ok']++;
                }
            } else {
                // Old carico non in inventario
                if ($isDeleted) {
                    $stats['ok']++; // già soft-deleted, corretto
                } else {
                    $stats['old_delete']++;
                    $azioni[] = [
                        'id' => $art->id, 'tipo' => 'old_delete',
                        'codice' => $codice, 'qta_target' => 0,
                        'qta_attuale' => $qtaAttuale, 'giacenza_id' => $art->giacenza_id,
                        'cat_id' => $art->categoria_merceologica_id, 'sede_id' => $art->sede_id,
                    ];
                }
            }
        }

        return [$stats, $azioni];
    }

    private function esegui(array $azioni): void
    {
        $bar = $this->output->createProgressBar(count($azioni));
        $bar->start();

        DB::transaction(function () use ($azioni, $bar) {
            foreach ($azioni as $a) {
                match ($a['tipo']) {
                    'inv_restore' => $this->doRestore($a),
                    'inv_fix_qta' => $this->doFixQta($a),
                    'new_restore' => $this->doRestore($a),
                    'old_delete'  => $this->doDelete($a),
                };
                $bar->advance();
            }
        });

        $bar->finish();
        $this->info('');
        Log::info('RiconciliaGiacenzeOldDb: completato', ['azioni' => count($azioni)]);
    }

    private function doRestore(array $a): void
    {
        DB::table('articoli')->where('id', $a['id'])->update(['deleted_at' => null]);
        $this->doFixQta($a);
    }

    private function doFixQta(array $a): void
    {
        if ($a['giacenza_id']) {
            DB::table('giacenze')->where('id', $a['giacenza_id'])->update([
                'quantita_residua' => $a['qta_target'],
                'updated_at'       => now(),
            ]);
        } elseif ($a['qta_target'] > 0) {
            DB::table('giacenze')->insert([
                'articolo_id'               => $a['id'],
                'categoria_merceologica_id' => $a['cat_id'],
                'sede_id'                   => $a['sede_id'],
                'quantita'                  => $a['qta_target'],
                'quantita_iniziale'         => $a['qta_target'],
                'quantita_residua'          => $a['qta_target'],
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);
        }
    }

    private function doDelete(array $a): void
    {
        DB::table('articoli')->where('id', $a['id'])->update(['deleted_at' => now()]);
        if ($a['giacenza_id']) {
            DB::table('giacenze')->where('id', $a['giacenza_id'])->update([
                'quantita_residua' => 0,
                'updated_at'       => now(),
            ]);
        }
    }

    private function printStats(array $stats): void
    {
        $this->info('');
        $this->info('── Riepilogo azioni ─────────────────────────────────────────');
        $this->line("  Inventariati soft-deleted → RESTORE + qta:   {$stats['inv_restore']}");
        $this->line("  Inventariati qta sbagliata → FIX qta:         {$stats['inv_fix_qta']}");
        $this->line("  Nuovi carichi soft-deleted → RESTORE:          {$stats['new_restore']}");
        $this->line("  Old non-inventariati       → SOFT-DELETE:      {$stats['old_delete']}");
        $this->line("  Già corretti (nessuna azione):                 {$stats['ok']}");
        $magSkipLabel = $this->magFiltroAttivo
            ? 'Esclusi (fuori filtro + MAG8/9/22):'
            : 'Skip MAG8/MAG9/MAG22 (trattare a parte):';
        $this->line("  {$magSkipLabel}           {$stats['skip_pf_mag']}");
        $this->line("  Skip carichi multipli (trattare a parte):     {$stats['skip_multiplo']}");
        $this->line("  Skip PF annullati:                             {$stats['skip_pf']}");
        $this->line("  Skip INESISTENTE:                              {$stats['skip_inesistente']}");
        $totale = $stats['inv_restore'] + $stats['inv_fix_qta'] + $stats['new_restore'] + $stats['old_delete'];
        $this->info("  ─────────────────────────────────────────────────────────");
        $this->info("  TOTALE azioni: {$totale}");
    }

    private function printSample(array $azioni): void
    {
        $gruppi = [
            'inv_restore' => 'Inventariati da RIPRISTINARE:',
            'inv_fix_qta' => 'Inventariati con qta da CORREGGERE:',
            'old_delete'  => 'Old non-inventariati da SOFT-DELETE:',
            'new_restore' => 'Nuovi carichi da RIPRISTINARE:',
        ];

        foreach ($gruppi as $tipo => $titolo) {
            $campione = array_slice(array_filter($azioni, fn($a) => $a['tipo'] === $tipo), 0, 10);
            if (empty($campione)) { continue; }
            $this->info('');
            $this->info($titolo);
            $this->table(
                ['Codice', 'Qta attuale', 'Qta target'],
                array_map(fn($a) => [$a['codice'], $a['qta_attuale'], $a['qta_target']], $campione)
            );
        }
    }

    private function exportCsv(array $azioni, string $path): void
    {
        // Arricchisci con descrizione articolo
        $ids = array_column($azioni, 'id');
        $descrizioni = DB::table('articoli')
            ->whereIn('id', $ids)
            ->pluck('descrizione', 'id');

        $handle = fopen($path, 'w');
        fputcsv($handle, ['azione', 'codice', 'descrizione', 'qta_attuale', 'qta_target']);
        foreach ($azioni as $a) {
            fputcsv($handle, [
                $a['tipo'],
                $a['codice'],
                $descrizioni[$a['id']] ?? '',
                $a['qta_attuale'],
                $a['qta_target'],
            ]);
        }
        fclose($handle);
        $this->info('');
        $this->info("Export salvato in: {$path}");
    }

    private function parseMag(string $codice): ?int
    {
        if (preg_match('/^(\d+)-\d+/', $codice, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function parseNum(string $codice): ?int
    {
        if (preg_match('/^\d+-(\d+)/', $codice, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
