<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixFornitoriMigrazione extends Command
{
    protected $signature = 'magazzino:fix-fornitori-migrazione
                            {--dry-run : Mostra le modifiche senza applicarle}
                            {--verbose-detail : Mostra ogni singolo articolo}
                            {--force : Applica senza chiedere conferma}
                            {--no-cap-qta : Non limita qta a 1 sui magazzini orologi (2,10,18,19,20,21)}
                            {--solo-fantasma : Solo DDT fantasma 10/03/2026 (più conservativo)}';

    protected $description = 'Corregge carichi errati da migrazione: DDT/fornitore, numero e data documento, prezzi, qta giacenza (orologi max 1).';

    /** Magazzini con articoli serializzati (un pezzo = qta 1). */
    private const MAG_OROLOGI = [2, 10, 18, 19, 20, 21];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $verbose = $this->option('verbose-detail');
        $capQta = ! $this->option('no-cap-qta');

        $this->info('');
        $this->info('=== Fix carichi migrazione (fornitore + dati carico) ===');
        $this->info($dryRun ? '*** DRY-RUN ***' : '*** ESECUZIONE ***');
        $this->info('');

        $candidati = $this->trovaCandidati((bool) $this->option('solo-fantasma'));

        if (empty($candidati)) {
            $this->info('Nessun articolo da correggere.');
            return self::SUCCESS;
        }

        $piano = array_map(fn ($r) => $this->calcolaFix($r, $capQta), $candidati);

        $this->info(sprintf('Trovati <fg=yellow>%d</> articoli con carico da correggere:', count($piano)));
        $this->info('');

        $this->mostraRiepilogo($piano);

        if ($verbose) {
            $this->mostraDettaglio($piano);
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Nessuna modifica applicata.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf(
            'Confermi la correzione di %d articoli?',
            count($piano)
        ))) {
            $this->info('Annullato.');
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $n = 0;
            foreach ($piano as $fix) {
                $this->applicaFix($fix);
                if ($verbose) {
                    $this->line('  ✓ ' . $fix['codice'] . ' — ' . implode('; ', $fix['azioni']));
                }
                $n++;
            }
            DB::commit();
            $this->info('');
            $this->info("<fg=green>✓ Corretti {$n} articoli.</>");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('ERRORE: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Articoli il cui carico_dettagli punta a un DDT con numero diverso da articoli.numero_documento_carico,
     * oppure al DDT fantasma 10/03/2026, quando esiste un DDT con il numero corretto.
     */
    private function trovaCandidati(bool $soloFantasma): array
    {
        $whereMismatch = $soloFantasma
            ? "d_att.quantita_totale = 0 AND d_att.numero_articoli = 0 AND DATE(d_att.created_at) = '2026-03-10'"
            : "(
                TRIM(d_att.numero) != TRIM(a.numero_documento_carico)
                OR (
                    d_att.quantita_totale = 0
                    AND d_att.numero_articoli = 0
                    AND DATE(d_att.created_at) = '2026-03-10'
                )
            )";

        return DB::select("
            SELECT
                cd.id                    AS cd_id,
                a.id                     AS articolo_id,
                a.codice                 AS codice,
                a.descrizione            AS descrizione,
                a.numero_documento_carico AS num_doc_articolo,
                a.data_carico            AS data_carico_articolo,
                a.prezzo_acquisto        AS prezzo_acquisto,
                a.tipo_carico            AS tipo_carico,
                cd.ddt_id                AS ddt_attuale_id,
                cd.prezzo_unitario       AS cd_prezzo_attuale,
                d_att.numero             AS ddt_attuale_num,
                d_att.data_documento     AS ddt_attuale_data,
                f_att.ragione_sociale    AS fornitore_attuale,
                g.id                     AS giacenza_id,
                g.quantita               AS qta,
                g.quantita_residua       AS qta_residua,
                g.costo_unitario         AS costo_unitario,
                d_ok.id                  AS ddt_corretto_id,
                d_ok.numero              AS ddt_corretto_num,
                d_ok.data_documento      AS ddt_corretto_data,
                f_ok.ragione_sociale     AS fornitore_corretto,
                dd_ok.prezzo_unitario    AS dd_prezzo,
                dd_ok.quantita           AS dd_qta
            FROM carico_dettagli cd
            JOIN articoli a ON a.id = cd.articolo_id
                AND a.numero_documento_carico IS NOT NULL
                AND TRIM(a.numero_documento_carico) != ''
                AND a.numero_documento_carico != '0'
            JOIN ddt d_att ON d_att.id = cd.ddt_id
            JOIN fornitori f_att ON f_att.id = d_att.fornitore_id
            LEFT JOIN giacenze g ON g.articolo_id = a.id
            JOIN (
                SELECT numero, MIN(id) AS id
                FROM ddt
                WHERE numero IS NOT NULL AND TRIM(numero) != ''
                GROUP BY numero
            ) pick ON pick.numero = TRIM(a.numero_documento_carico)
            JOIN ddt d_ok ON d_ok.id = pick.id AND d_ok.id != cd.ddt_id
            JOIN fornitori f_ok ON f_ok.id = d_ok.fornitore_id
            LEFT JOIN ddt_dettagli dd_ok
                ON dd_ok.ddt_id = d_ok.id AND dd_ok.articolo_id = a.id
            WHERE {$whereMismatch}
            ORDER BY a.codice
        ");
    }

    private function calcolaFix(object $r, bool $capQta): array
    {
        $mag = (int) explode('-', $r->codice)[0];
        $azioni = [];

        $prezzo = $this->risolviPrezzo($r);
        if ($prezzo !== null && (float) $r->cd_prezzo_attuale !== (float) $prezzo) {
            $azioni[] = 'prezzo carico';
        }

        if ($r->fornitore_attuale !== $r->fornitore_corretto) {
            $azioni[] = 'fornitore';
        }
        if (trim((string) $r->ddt_attuale_num) !== trim((string) $r->ddt_corretto_num)) {
            $azioni[] = 'n. documento';
        }
        if ($r->data_carico_articolo !== $r->ddt_corretto_data) {
            $azioni[] = 'data carico';
        }

        $qtaNuova = $r->qta;
        $qtaResNuova = $r->qta_residua;
        if ($capQta && in_array($mag, self::MAG_OROLOGI, true)) {
            if ((int) $r->qta_residua > 1) {
                $qtaResNuova = 1;
                $azioni[] = 'qta residua→1';
            }
            if ((int) $r->qta > 1) {
                $qtaNuova = 1;
                $azioni[] = 'qta→1';
            }
        } elseif ($r->dd_qta !== null && (int) $r->dd_qta > 0 && (int) $r->dd_qta !== (int) $r->qta) {
            $qtaNuova = (int) $r->dd_qta;
            $azioni[] = 'qta da ddt_dettagli';
        }

        $costoNuovo = $prezzo ?? $r->costo_unitario;
        if ($costoNuovo !== null && (float) ($r->costo_unitario ?? 0) !== (float) $costoNuovo) {
            $azioni[] = 'costo giacenza';
        }

        if (empty($azioni)) {
            $azioni[] = 'solo ddt_id';
        }

        return [
            'cd_id' => $r->cd_id,
            'articolo_id' => $r->articolo_id,
            'giacenza_id' => $r->giacenza_id,
            'codice' => $r->codice,
            'descrizione' => $r->descrizione,
            'ddt_corretto_id' => $r->ddt_corretto_id,
            'fornitore_attuale' => $r->fornitore_attuale,
            'fornitore_corretto' => $r->fornitore_corretto,
            'ddt_attuale_num' => $r->ddt_attuale_num,
            'ddt_corretto_num' => $r->ddt_corretto_num,
            'data_carico' => $r->ddt_corretto_data,
            'prezzo' => $prezzo,
            'qta_prima' => $r->qta,
            'qta_residua_prima' => $r->qta_residua,
            'qta' => $qtaNuova,
            'qta_residua' => $qtaResNuova,
            'costo_unitario' => $costoNuovo,
            'azioni' => $azioni,
        ];
    }

    private function risolviPrezzo(object $r): ?float
    {
        if ($r->dd_prezzo !== null && (float) $r->dd_prezzo > 0) {
            return (float) $r->dd_prezzo;
        }
        if ($r->prezzo_acquisto !== null && (float) $r->prezzo_acquisto > 0) {
            return (float) $r->prezzo_acquisto;
        }
        if ($r->cd_prezzo_attuale !== null && (float) $r->cd_prezzo_attuale > 0) {
            return (float) $r->cd_prezzo_attuale;
        }

        return null;
    }

    private function applicaFix(array $fix): void
    {
        $giaCollegato = DB::table('carico_dettagli')
            ->where('articolo_id', $fix['articolo_id'])
            ->where('ddt_id', $fix['ddt_corretto_id'])
            ->where('id', '!=', $fix['cd_id'])
            ->exists();

        if ($giaCollegato) {
            DB::table('carico_dettagli')->where('id', $fix['cd_id'])->delete();
        } else {
            DB::table('carico_dettagli')
                ->where('id', $fix['cd_id'])
                ->update([
                    'ddt_id' => $fix['ddt_corretto_id'],
                    'prezzo_unitario' => $fix['prezzo'],
                ]);
        }

        $articoloUpdate = [
            'numero_documento_carico' => $fix['ddt_corretto_num'],
            'data_carico' => $fix['data_carico'],
        ];
        $tipo = DB::table('articoli')->where('id', $fix['articolo_id'])->value('tipo_carico');
        if (empty($tipo)) {
            $articoloUpdate['tipo_carico'] = 'ddt';
        }
        DB::table('articoli')->where('id', $fix['articolo_id'])->update($articoloUpdate);

        if ($fix['giacenza_id']) {
            $giacenzaUpdate = [];
            if ($fix['qta'] !== null) {
                $giacenzaUpdate['quantita'] = $fix['qta'];
            }
            if ($fix['qta_residua'] !== null) {
                $giacenzaUpdate['quantita_residua'] = $fix['qta_residua'];
            }
            if ($fix['costo_unitario'] !== null) {
                $giacenzaUpdate['costo_unitario'] = $fix['costo_unitario'];
            }
            if ($giacenzaUpdate !== []) {
                DB::table('giacenze')->where('id', $fix['giacenza_id'])->update($giacenzaUpdate);
            }
        }

        // Sposta ddt_dettagli dal DDT sbagliato se non esiste già sul DDT corretto
        $haRigaOk = DB::table('ddt_dettagli')
            ->where('articolo_id', $fix['articolo_id'])
            ->where('ddt_id', $fix['ddt_corretto_id'])
            ->exists();
        if (! $haRigaOk) {
            DB::table('ddt_dettagli')
                ->where('articolo_id', $fix['articolo_id'])
                ->where('ddt_id', '!=', $fix['ddt_corretto_id'])
                ->limit(1)
                ->update(['ddt_id' => $fix['ddt_corretto_id']]);
        }
    }

    private function mostraRiepilogo(array $piano): void
    {
        $byAction = [];
        foreach ($piano as $f) {
            foreach ($f['azioni'] as $a) {
                $byAction[$a] = ($byAction[$a] ?? 0) + 1;
            }
        }
        ksort($byAction);
        $this->table(
            ['Tipo correzione', 'N. articoli'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($byAction), array_values($byAction))
        );
    }

    private function mostraDettaglio(array $piano): void
    {
        $this->info('');
        $this->table(
            [
                'Codice', 'Fornitore', '→', 'Doc attuale', '→ Doc corretto',
                'Qta res.', '→', 'Prezzo', 'Azioni',
            ],
            array_map(function ($f) {
                $qtaRes = $f['qta_residua_prima'] ?? '—';
                $qtaResNew = $f['qta_residua'] ?? '—';

                return [
                    $f['codice'],
                    mb_strimwidth($f['fornitore_attuale'], 0, 22, '…'),
                    '→',
                    $f['ddt_attuale_num'],
                    $f['ddt_corretto_num'],
                    $qtaRes,
                    $qtaResNew,
                    $f['prezzo'] !== null ? number_format($f['prezzo'], 2, ',', '.') : '—',
                    implode(', ', $f['azioni']),
                ];
            }, $piano)
        );
    }
}
