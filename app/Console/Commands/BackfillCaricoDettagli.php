<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCaricoDettagli extends Command
{
    protected $signature = 'magazzino:backfill-carico-dettagli
                            {--dry-run : Solo anteprima}
                            {--magazzini= : Es. 2 o 1,2,3}
                            {--force : Senza conferma}';

    protected $description = 'Crea carico_dettagli mancanti collegando articoli a DDT/fatture tramite numero_documento_carico.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $magFilter = $this->parseMagazzini();

        $this->info($dryRun ? '*** DRY-RUN ***' : '*** ESECUZIONE ***');

        $sql = "
            SELECT
                a.id AS articolo_id,
                a.codice,
                a.numero_documento_carico,
                a.tipo_carico,
                a.prezzo_acquisto,
                d_pick.id AS ddt_id,
                fat_pick.id AS fattura_id,
                COALESCE(f_d.ragione_sociale, f_f.ragione_sociale) AS fornitore
            FROM articoli a
            LEFT JOIN carico_dettagli cd ON cd.articolo_id = a.id
            LEFT JOIN (
                SELECT numero, MIN(id) AS id FROM ddt
                WHERE numero IS NOT NULL AND TRIM(numero) != ''
                GROUP BY numero
            ) d_pick ON d_pick.numero = TRIM(a.numero_documento_carico)
            LEFT JOIN fornitori f_d ON f_d.id = (SELECT fornitore_id FROM ddt WHERE id = d_pick.id)
            LEFT JOIN (
                SELECT numero, MIN(id) AS id FROM fatture
                WHERE numero IS NOT NULL AND TRIM(numero) != ''
                GROUP BY numero
            ) fat_pick ON fat_pick.numero = TRIM(a.numero_documento_carico)
            LEFT JOIN fornitori f_f ON f_f.id = (SELECT fornitore_id FROM fatture WHERE id = fat_pick.id)
            WHERE a.deleted_at IS NULL
              AND cd.id IS NULL
              AND a.numero_documento_carico IS NOT NULL
              AND TRIM(a.numero_documento_carico) != ''
              AND TRIM(a.numero_documento_carico) != '0'
              AND (d_pick.id IS NOT NULL OR fat_pick.id IS NOT NULL)
        ";

        if ($magFilter !== null) {
            $sql .= ' AND CAST(SUBSTRING_INDEX(a.codice, \'-\', 1) AS UNSIGNED) IN (' . implode(',', $magFilter) . ')';
        }

        $sql .= ' ORDER BY a.codice';

        $rows = DB::select($sql);

        if (empty($rows)) {
            $this->info('Nessun articolo senza carico_dettagli da collegare.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Trovati %d articoli senza carico_dettagli (documento trovato):', count($rows)));
        $this->table(
            ['Codice', 'Num. doc.', 'Tipo', 'Fornitore', 'DDT id', 'Fattura id'],
            array_map(fn ($r) => [
                $r->codice,
                $r->numero_documento_carico,
                $r->tipo_carico ?? '—',
                $r->fornitore ?? '—',
                $r->ddt_id ?? '—',
                $r->fattura_id ?? '—',
            ], array_slice($rows, 0, 30))
        );
        if (count($rows) > 30) {
            $this->line('... e altri ' . (count($rows) - 30));
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Creare ' . count($rows) . ' record carico_dettagli?')) {
            return self::SUCCESS;
        }

        $n = 0;
        DB::beginTransaction();
        try {
            foreach ($rows as $r) {
                [$ddtId, $fatturaId] = match (true) {
                    $r->fattura_id && $r->tipo_carico === 'fattura' => [null, $r->fattura_id],
                    $r->ddt_id !== null => [$r->ddt_id, null],
                    $r->fattura_id !== null => [null, $r->fattura_id],
                    default => [null, null],
                };
                DB::table('carico_dettagli')->insert([
                    'articolo_id' => $r->articolo_id,
                    'ddt_id' => $ddtId,
                    'fattura_id' => $fatturaId,
                    'prezzo_unitario' => $r->prezzo_acquisto,
                    'quantita' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $n++;
            }
            DB::commit();
            $this->info("<fg=green>✓ Creati {$n} carico_dettagli.</>");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function parseMagazzini(): ?array
    {
        if (! $this->option('magazzini')) {
            return null;
        }

        return array_map('intval', explode(',', $this->option('magazzini')));
    }
}
