<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fornitore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillArticoliFornitore extends Command
{
    protected $signature = 'articoli:backfill-fornitore {--dry-run : Simula senza salvare}';

    protected $description = 'Popola fornitore_id in articoli partendo dalla vista MSSQL elenco_articoli_magazzino';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!Schema::connection('mssql_prod')->hasColumn('elenco_articoli_magazzino', 'fornitore')) {
            $this->error('La vista MSSQL elenco_articoli_magazzino non ha il campo fornitore.');
            return 1;
        }

        $records = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->select('id', 'fornitore')
            ->whereNotNull('fornitore')
            ->get();

        $aggiornati = 0;
        foreach ($records as $rec) {
            $fornitoreId = $this->resolveFornitoreId($rec->fornitore);
            if (!$fornitoreId) {
                continue;
            }
            if (!$dryRun) {
                $updated = DB::table('articoli')
                    ->where('id', $rec->id)
                    ->update(['fornitore_id' => $fornitoreId]);
                $aggiornati += $updated;
            }
        }

        $this->info('✅ Backfill completato');
        $this->line("Articoli aggiornati: {$aggiornati}");

        if ($dryRun) {
            $this->warn('DRY RUN - Nessuna modifica salvata');
        }

        return 0;
    }

    private function resolveFornitoreId(?string $ragioneSociale): ?int
    {
        $ragioneSociale = trim((string) $ragioneSociale);
        if ($ragioneSociale === '') {
            return null;
        }

        if (strcasecmp($ragioneSociale, 'NON INSERITO') === 0) {
            $ragioneSociale = 'DE PASCALIS S.P.A.';
        }

        $fornitore = Fornitore::where('ragione_sociale', $ragioneSociale)->first();
        if ($fornitore) {
            return $fornitore->id;
        }

        $fornitore = Fornitore::where('ragione_sociale', 'like', '%' . $ragioneSociale . '%')->first();
        if ($fornitore) {
            return $fornitore->id;
        }

        $nuovo = Fornitore::create([
            'ragione_sociale' => $ragioneSociale,
            'note' => 'Creato da import elenco_articoli_magazzino',
        ]);

        return $nuovo->id;
    }
}
