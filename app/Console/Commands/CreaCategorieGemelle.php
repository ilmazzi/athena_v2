<?php

namespace App\Console\Commands;

use App\Models\CategoriaMerceologica;
use Illuminate\Console\Command;

class CreaCategorieGemelle extends Command
{
    protected $signature = 'categorie:crea-gemelli
        {--apply : Applica le creazioni (default: solo report)}
        {--exclude=Conto Deposito% : Pattern nome da escludere}';

    protected $description = 'Crea categorie gemelle tra sedi secondo la regola concordata.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $exclude = (string) $this->option('exclude');

        // Regola concordata:
        // Lecco (1) -> Roma (5), Mazzini (4), Monastero (3), Jolly (2)
        // Roma (5) -> Lecco (1), Mazzini (4), Monastero (3)
        $rules = [
            1 => [5, 4, 3, 2],
            5 => [1, 4, 3],
        ];

        $this->info($apply ? 'Creo categorie gemelle...' : 'Report (nessuna creazione applicata).');

        foreach ($rules as $sourceSedeId => $targets) {
            $sourceCategories = CategoriaMerceologica::where('sede_id', $sourceSedeId)
                ->where('nome', 'not like', $exclude)
                ->get();

            foreach ($targets as $targetSedeId) {
                $missing = [];

                foreach ($sourceCategories as $source) {
                    $exists = CategoriaMerceologica::where('sede_id', $targetSedeId)
                        ->where('nome', $source->nome)
                        ->exists();

                    if (!$exists) {
                        $missing[] = $source;
                    }
                }

                $count = count($missing);
                $this->line("Sede {$sourceSedeId} -> {$targetSedeId}: {$count} da creare.");

                if ($apply && $count > 0) {
                    foreach ($missing as $source) {
                        $codice = $this->buildUniqueCodice($source->codice, $targetSedeId);
                        CategoriaMerceologica::create([
                            'sede_id' => $targetSedeId,
                            'nome' => $source->nome,
                            'codice' => $codice,
                            'indirizzo' => $source->indirizzo,
                            'citta' => $source->citta,
                            'provincia' => $source->provincia,
                            'cap' => $source->cap,
                            'tipo' => $source->tipo,
                            'note' => $source->note,
                            'configurazione' => $source->configurazione,
                            'attivo' => $source->attivo,
                        ]);
                    }
                }
            }
        }

        $this->info('Operazione completata.');
        return self::SUCCESS;
    }

    private function buildUniqueCodice(string $baseCodice, int $targetSedeId): string
    {
        $codice = $baseCodice;
        if (!CategoriaMerceologica::where('codice', $codice)->exists()) {
            return $codice;
        }

        $codice = $baseCodice . '-S' . $targetSedeId;
        if (!CategoriaMerceologica::where('codice', $codice)->exists()) {
            return $codice;
        }

        $suffix = 2;
        while (CategoriaMerceologica::where('codice', $codice . '-' . $suffix)->exists()) {
            $suffix++;
        }

        return $codice . '-' . $suffix;
    }
}
