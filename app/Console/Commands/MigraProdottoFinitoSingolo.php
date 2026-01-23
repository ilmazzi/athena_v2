<?php

namespace App\Console\Commands;

use App\Models\Articolo;
use App\Models\ComponenteProdotto;
use App\Models\ProdottoFinito;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigraProdottoFinitoSingolo extends Command
{
    protected $signature = 'produzione:migra-pf-singolo 
                            {codice : Codice nel formato "9-268"} 
                            {--dry-run : Mostra cosa verrebbe fatto senza salvare}';

    protected $description = 'Migra un singolo prodotto finito da MSSQL (elenco_articoli_magazzino + mag_diba)';

    public function handle(): int
    {
        $codice = trim($this->argument('codice'));
        if (!str_contains($codice, '-')) {
            $this->error('Formato codice non valido. Usa: 9-268');
            return 1;
        }

        [$magazzino, $carico] = explode('-', $codice, 2);
        $magazzino = trim($magazzino);
        $carico = trim($carico);

        $pf = DB::connection('mssql_prod')
            ->table('elenco_articoli_magazzino')
            ->where('id_magazzino', $magazzino)
            ->where('carico', $carico)
            ->first();

        if (!$pf) {
            $this->error("Prodotto finito non trovato in MSSQL per codice {$codice}");
            return 1;
        }

        $existing = ProdottoFinito::withTrashed()->find($pf->id_pf);
        if ($existing) {
            $this->warn("Prodotto finito già presente (ID {$pf->id_pf}, codice {$existing->codice})");
            return 0;
        }

        $articolo = Articolo::withTrashed()->find($pf->id);
        if (!$articolo) {
            $this->warn("Articolo risultante non trovato in MySQL (id {$pf->id})");
        } elseif ($articolo->trashed()) {
            $this->line("Articolo {$articolo->codice} è soft-deleted: verrà ripristinato");
            if (!$this->option('dry-run')) {
                $articolo->restore();
            }
        }

        $this->info("Migrazione prodotto finito: {$pf->id_magazzino}-{$pf->carico} (ID PF {$pf->id_pf})");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: nessun dato salvato');
            return 0;
        }

        DB::table('prodotti_finiti')->insert([
            'id' => $pf->id_pf,
            'codice' => $pf->id_magazzino . '-' . $pf->carico,
            'descrizione' => $pf->descrizione,
            'tipologia' => $pf->id_magazzino == 9 ? 'prodotto_finito' : 'semilavorato',
            'magazzino_id' => $pf->id_magazzino,
            'costo_totale' => $pf->valore_magazzino ?? 0,
            'stato' => 'completato',
            'data_completamento' => $pf->data_documento ?? now(),
            'note' => "Migrato da MSSQL - ID articolo originale: {$pf->id}, ID PF: {$pf->id_pf}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migraComponenti($pf->id_pf);

        $this->info('✅ Prodotto finito migrato');
        return 0;
    }

    private function migraComponenti(int $idPf): void
    {
        $componentiMssql = DB::connection('mssql_prod')
            ->table('mag_diba')
            ->where('id_pf', $idPf)
            ->get();

        foreach ($componentiMssql as $comp) {
            $articoloComponente = Articolo::withTrashed()->find($comp->id_articolo);
            if (!$articoloComponente) {
                $this->warn("Componente non trovato: articolo_id {$comp->id_articolo}");
                continue;
            }

            $exists = ComponenteProdotto::where('prodotto_finito_id', $idPf)
                ->where('articolo_id', $articoloComponente->id)
                ->exists();

            if ($exists) {
                continue;
            }

            ComponenteProdotto::create([
                'prodotto_finito_id' => $idPf,
                'articolo_id' => $articoloComponente->id,
                'quantita' => $comp->quantita ?? 1,
                'costo_unitario' => $articoloComponente->prezzo_acquisto ?? 0,
                'costo_totale' => ($articoloComponente->prezzo_acquisto ?? 0) * ($comp->quantita ?? 1),
                'stato' => 'prelevato',
                'prelevato_il' => now(),
                'prelevato_da' => 1,
            ]);
        }
    }
}
