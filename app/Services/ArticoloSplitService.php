<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\GiacenzaSede;
use Illuminate\Support\Facades\DB;

class ArticoloSplitService
{
    public function splitArticolo(Articolo $articolo, int $quantita): Articolo
    {
        if ($quantita <= 0) {
            throw new \InvalidArgumentException('Quantità split non valida.');
        }

        $articolo->loadMissing('giacenza');
        $giacenza = $articolo->giacenza;
        if (!$giacenza) {
            throw new \InvalidArgumentException('Articolo senza giacenza associata.');
        }

        $disponibile = $giacenza->quantita_residua ?? $giacenza->quantita ?? 0;
        if ($quantita >= $disponibile) {
            throw new \InvalidArgumentException('Quantità split deve essere inferiore alla giacenza disponibile.');
        }

        return DB::transaction(function () use ($articolo, $giacenza, $quantita) {
            $rootId = $articolo->articolo_padre_id ?: $articolo->id;
            $codiceBase = $articolo->codice_base ?: $articolo->codice;

            if (!$articolo->codice_base) {
                $articolo->update(['codice_base' => $codiceBase]);
            }

            $maxIndex = Articolo::where('articolo_padre_id', $rootId)->max('split_index');
            $nextIndex = $maxIndex ? $maxIndex + 1 : 1;
            $nuovoCodice = $codiceBase . '-' . $nextIndex;

            $figlio = $articolo->replicate();
            $figlio->codice = $nuovoCodice;
            $figlio->codice_base = $codiceBase;
            $figlio->articolo_padre_id = $rootId;
            $figlio->split_index = $nextIndex;
            $figlio->in_vetrina = false;
            $figlio->ultimo_testo_vetrina = null;
            $figlio->conto_deposito_corrente_id = null;
            $figlio->quantita_in_deposito = 0;
            $figlio->save();

            Giacenza::create([
                'articolo_id' => $figlio->id,
                'categoria_merceologica_id' => $giacenza->categoria_merceologica_id,
                'sede_id' => $giacenza->sede_id,
                'ubicazione_id' => $giacenza->ubicazione_id,
                'quantita' => $quantita,
                'quantita_iniziale' => $quantita,
                'quantita_residua' => $quantita,
                'quantita_deposito' => 0,
                'quantita_minima' => $giacenza->quantita_minima,
                'quantita_riservata' => 0,
                'costo_unitario' => $giacenza->costo_unitario,
                'scaffale' => $giacenza->scaffale,
                'box' => $giacenza->box,
                'posizione' => $giacenza->posizione,
                'ultimo_movimento_at' => now(),
                'ultimo_inventario_at' => $giacenza->ultimo_inventario_at,
                'ultima_verifica_at' => $giacenza->ultima_verifica_at,
                'note' => $giacenza->note,
            ]);

            $nuovaQuantita = max(0, (int) $giacenza->quantita - $quantita);
            $residuaBase = $giacenza->quantita_residua ?? $giacenza->quantita;
            $nuovaResidua = max(0, (int) $residuaBase - $quantita);

            $giacenza->update([
                'quantita' => $nuovaQuantita,
                'quantita_residua' => $nuovaResidua,
                'ultimo_movimento_at' => now(),
            ]);

            $giacenzaSede = GiacenzaSede::where('articolo_id', $articolo->id)
                ->where('sede_id', $giacenza->sede_id)
                ->first();

            if ($giacenzaSede) {
                $giacenzaSede->update([
                    'quantita' => max(0, (int) $giacenzaSede->quantita - $quantita),
                    'quantita_residua' => max(0, (int) $giacenzaSede->quantita_residua - $quantita),
                ]);

                GiacenzaSede::create([
                    'articolo_id' => $figlio->id,
                    'sede_id' => $giacenzaSede->sede_id,
                    'quantita' => $quantita,
                    'quantita_residua' => $quantita,
                ]);
            }

            return $figlio;
        });
    }
}
