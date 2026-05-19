<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixFornitoriMigrazione extends Command
{
    protected $signature = 'magazzino:fix-fornitori-migrazione
                            {--dry-run : Mostra le modifiche senza applicarle}
                            {--verbose-detail : Mostra ogni singolo articolo}';

    protected $description = 'Corregge i carico_dettagli che puntano ai DDT fantasma del 10/03/2026 (quantita=0) verso i DDT reali con il fornitore corretto.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $verbose = $this->option('verbose-detail');

        $this->info('');
        $this->info('=== Fix Fornitori Migrazione ===');
        $this->info($dryRun ? '*** DRY-RUN: nessuna modifica verrà applicata ***' : '*** MODALITÀ ESECUZIONE REALE ***');
        $this->info('');

        // Trova tutti i carico_dettagli che puntano a un DDT fantasma
        // (creato il 10/03/2026 con quantita_totale=0)
        // E per cui esiste un DDT reale (quantita_totale>0) con lo stesso numero
        // ma fornitore diverso.
        $candidati = DB::select("
            SELECT
                cd.id                   AS cd_id,
                a.id                    AS articolo_id,
                a.codice                AS codice,
                a.descrizione           AS descrizione,
                a.numero_documento_carico AS num_doc,
                cd.ddt_id               AS ddt_sbagliato_id,
                d_wrong.numero          AS ddt_sbagliato_num,
                f_wrong.id              AS fornitore_sbagliato_id,
                f_wrong.ragione_sociale AS fornitore_sbagliato,
                d_ok.id                 AS ddt_corretto_id,
                d_ok.numero             AS ddt_corretto_num,
                f_ok.id                 AS fornitore_corretto_id,
                f_ok.ragione_sociale    AS fornitore_corretto
            FROM carico_dettagli cd
            JOIN articoli a
                ON a.id = cd.articolo_id
                AND a.numero_documento_carico IS NOT NULL
                AND a.numero_documento_carico != ''
                AND a.numero_documento_carico != '0'
            JOIN ddt d_wrong
                ON d_wrong.id = cd.ddt_id
                AND d_wrong.quantita_totale = 0
                AND d_wrong.numero_articoli = 0
                AND DATE(d_wrong.created_at) = '2026-03-10'
            JOIN fornitori f_wrong
                ON f_wrong.id = d_wrong.fornitore_id
            JOIN (
                SELECT numero, MIN(id) AS id
                FROM ddt
                WHERE quantita_totale > 0
                GROUP BY numero
            ) best ON best.numero = a.numero_documento_carico
            JOIN ddt d_ok ON d_ok.id = best.id AND d_ok.id != cd.ddt_id
            JOIN fornitori f_ok ON f_ok.id = d_ok.fornitore_id
            WHERE f_wrong.id != f_ok.id
            ORDER BY a.codice
        ");

        if (empty($candidati)) {
            $this->info('Nessun articolo da correggere. Tutto OK.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Trovati <fg=yellow>%d</> articoli con fornitore errato:', count($candidati)));
        $this->info('');

        // Raggruppa per coppia fornitore sbagliato → corretto per il riepilogo
        $riepilogo = [];
        foreach ($candidati as $row) {
            $key = $row->fornitore_sbagliato . ' → ' . $row->fornitore_corretto;
            $riepilogo[$key] = ($riepilogo[$key] ?? 0) + 1;
        }

        $this->table(
            ['Fornitore sbagliato → corretto', 'N. articoli'],
            array_map(
                fn($k, $v) => [$k, $v],
                array_keys($riepilogo),
                array_values($riepilogo)
            )
        );

        if ($verbose) {
            $this->info('');
            $this->info('Dettaglio articoli:');
            $this->table(
                ['Codice', 'Descrizione', 'Num. Doc.', 'Fornitore sbagliato', 'Fornitore corretto', 'DDT sbagliato ID', 'DDT corretto ID'],
                array_map(fn($r) => [
                    $r->codice,
                    mb_strimwidth($r->descrizione, 0, 45, '…'),
                    $r->num_doc,
                    $r->fornitore_sbagliato,
                    $r->fornitore_corretto,
                    $r->ddt_sbagliato_id,
                    $r->ddt_corretto_id,
                ], $candidati)
            );
        }

        if ($dryRun) {
            $this->info('');
            $this->warn('[DRY-RUN] Nessuna modifica applicata.');
            $this->info('Riesegui senza --dry-run per applicare le correzioni.');
            return self::SUCCESS;
        }

        // Chiedi conferma prima di modificare
        if (! $this->confirm(sprintf(
            'Confermi la correzione di %d record carico_dettagli in produzione?',
            count($candidati)
        ))) {
            $this->info('Operazione annullata.');
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $corretti = 0;
            foreach ($candidati as $row) {
                DB::table('carico_dettagli')
                    ->where('id', $row->cd_id)
                    ->update(['ddt_id' => $row->ddt_corretto_id]);

                if ($verbose) {
                    $this->line(sprintf(
                        '  ✓ cd#%d | %s | %s → %s',
                        $row->cd_id,
                        $row->codice,
                        $row->fornitore_sbagliato,
                        $row->fornitore_corretto
                    ));
                }
                $corretti++;
            }

            DB::commit();

            $this->info('');
            $this->info(sprintf('<fg=green>✓ Corretti %d record carico_dettagli.</>', $corretti));
            $this->info('');
            $this->info('Verifica post-fix:');
            $rimasti = DB::select("
                SELECT COUNT(*) AS n
                FROM carico_dettagli cd
                JOIN ddt d ON d.id = cd.ddt_id
                    AND d.quantita_totale = 0
                    AND d.numero_articoli = 0
                    AND DATE(d.created_at) = '2026-03-10'
            ");
            $this->line(sprintf(
                '  Articoli ancora su DDT fantasma 10/03: <fg=%s>%d</>',
                $rimasti[0]->n === 0 ? 'green' : 'red',
                $rimasti[0]->n
            ));

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('ERRORE: ' . $e->getMessage());
            $this->error('Rollback eseguito. Nessuna modifica applicata.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
