<?php

namespace App\Services;

use App\Domain\Magazzino\Exceptions\GiacenzaInsufficienteException;
use App\Models\Giacenza;
use App\Models\Articolo;
use Illuminate\Support\Facades\DB;

/**
 * Service per gestione giacenze
 * 
     * Business Rules:
     * - Una giacenza per coppia Articolo + Categoria
 * - Mai quantità negative
 * - Decremento aggiorna stato articolo se quantità = 0
 * - NO data_scarico (solo stato)
 */
class GiacenzaService
{
    /**
     * Crea giacenza per articolo (1:1)
     * 
     * @param int $articoloId
     * @param int $magazzinoId
     * @param int $quantita
     * @param string|null $scaffale
     * @return Giacenza
     */
    public function creaGiacenza(
        int $articoloId,
        int $magazzinoId,
        int $quantita = 1,
        ?string $scaffale = null
    ): Giacenza {
        return DB::transaction(function () use ($articoloId, $magazzinoId, $quantita, $scaffale) {
            // Verifica che non esista già
            $esistente = Giacenza::where('articolo_id', $articoloId)
                ->where('categoria_merceologica_id', $magazzinoId)
                ->first();
            if ($esistente) {
                throw new \LogicException("Giacenza già esistente per articolo ID {$articoloId}");
            }
            
            // Crea giacenza
            return Giacenza::create([
                'articolo_id' => $articoloId,
                'categoria_merceologica_id' => $magazzinoId,
                'quantita' => $quantita,
                'scaffale' => $scaffale,
                'ultimo_movimento_at' => now(),
            ]);
        });
    }
    
    /**
     * Incrementa giacenza (carico)
     * 
     * @param int $articoloId
     * @param int $quantita
     * @return Giacenza
     */
    public function incrementa(int $articoloId, int $quantita = 1): Giacenza
    {
        return DB::transaction(function () use ($articoloId, $quantita) {
            $giacenza = Giacenza::where('articolo_id', $articoloId)
                ->lockForUpdate()
                ->firstOrFail();
            
            $giacenza->incrementa($quantita);
            
            return $giacenza->fresh();
        });
    }
    
    /**
     * Decrementa giacenza (scarico/vendita)
     * 
     * @param int $articoloId
     * @param int $quantita
     * @return Giacenza
     * @throws GiacenzaInsufficienteException
     */
    public function decrementa(int $articoloId, int $quantita = 1): Giacenza
    {
        return DB::transaction(function () use ($articoloId, $quantita) {
            $giacenza = Giacenza::where('articolo_id', $articoloId)
                ->lockForUpdate()
                ->firstOrFail();
            
            $giacenza->decrementa($quantita);
            
            return $giacenza->fresh();
        });
    }
    
    /**
     * Verifica disponibilità giacenza
     * 
     * @param int $articoloId
     * @param int $quantita
     * @return bool
     */
    public function verificaDisponibilita(int $articoloId, int $quantita = 1): bool
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)->first();
        
        if (!$giacenza) {
            return false;
        }
        
        return $giacenza->hasDisponibilita($quantita);
    }
    
    /**
     * Ottieni quantità disponibile per articolo
     * 
     * @param int $articoloId
     * @return int
     */
    public function getQuantitaDisponibile(int $articoloId): int
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)->first();
        
        return $giacenza ? $giacenza->quantita : 0;
    }
    
    /**
     * Trasferisci giacenza tra magazzini
     * 
     * @param int $articoloId
     * @param int $magazzinoDestinazioneId
     * @param int $quantita
     * @return Giacenza Nuova giacenza nel magazzino destinazione
     */
    public function trasferisci(
        int $articoloId,
        int $magazzinoDestinazioneId,
        int $quantita = 1
    ): Giacenza {
        $articolo = Articolo::findOrFail($articoloId);
        
        return $this->trasferisciDaA(
            $articoloId,
            $articolo->categoria_merceologica_id,
            $magazzinoDestinazioneId,
            $quantita
        );
    }

    /**
     * Trasferisci giacenza tra magazzini specificando origine e destinazione
     * 
     * @param int $articoloId
     * @param int $magazzinoOrigineId
     * @param int $magazzinoDestinazioneId
     * @param int $quantita
     * @return Giacenza
     */
    public function trasferisciDaA(
        int $articoloId,
        int $magazzinoOrigineId,
        int $magazzinoDestinazioneId,
        int $quantita = 1
    ): Giacenza {
        return DB::transaction(function () use ($articoloId, $magazzinoOrigineId, $magazzinoDestinazioneId, $quantita) {
            $giacenzaOrigine = Giacenza::where('articolo_id', $articoloId)
                ->where('categoria_merceologica_id', $magazzinoOrigineId)
                ->lockForUpdate()
                ->firstOrFail();
            
            $giacenzaOrigine->decrementa($quantita);
            
            $giacenzaDestinazione = Giacenza::where('categoria_merceologica_id', $magazzinoDestinazioneId)
                ->where('articolo_id', $articoloId)
                ->first();
            
            if ($giacenzaDestinazione) {
                $giacenzaDestinazione->incrementa($quantita);
            } else {
                $giacenzaDestinazione = $this->creaGiacenza(
                    $articoloId,
                    $magazzinoDestinazioneId,
                    $quantita
                );
            }
            
            Articolo::find($articoloId)->update([
                'categoria_merceologica_id' => $magazzinoDestinazioneId
            ]);
            
            return $giacenzaDestinazione;
        });
    }
    
    /**
     * Ottieni report giacenze per magazzino
     * 
     * @param int $magazzinoId
     * @return array
     */
    public function reportGiacenzeMagazzino(int $magazzinoId): array
    {
        $giacenze = Giacenza::where('categoria_merceologica_id', $magazzinoId)
            ->with('articolo')
            ->disponibili()
            ->get();
        
        return [
            'totale_articoli' => $giacenze->count(),
            'valore_totale' => $giacenze->sum(fn($g) => $g->articolo->prezzo_acquisto * $g->quantita),
            'quantita_totale' => $giacenze->sum('quantita'),
            'giacenze' => $giacenze,
        ];
    }
}

