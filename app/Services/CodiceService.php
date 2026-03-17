<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\ValueObjects\CodiceArticolo;
use Illuminate\Support\Facades\DB;

/**
 * Service per generazione codici progressivi
 * 
 * Business Rules:
 * - Progressivo PER MAGAZZINO (non globale!)
 * - Trova ultimo carico per magazzino
 * - Restituisce ultimo+1
 * - Thread-safe con lock DB
 */
class CodiceService
{
    /**
     * Query base per generazione codici:
     * - senza global scope sede utente
     * - include soft deleted
     * Perché l'unicità del codice è globale a livello DB.
     */
    private function codiciQuery()
    {
        return Articolo::query()
            ->withoutGlobalScopes()
            ->withTrashed();
    }

    /**
     * Genera prossimo codice carico per magazzino
     * 
     * Thread-safe: usa DB lock per evitare race conditions
     * 
     * @param int $magazzinoId
     * @return CodiceArticolo
     */
    public function generaProssimoCodice(int $magazzinoId): CodiceArticolo
    {
        return DB::transaction(function () use ($magazzinoId) {
            // Trova ultimo carico per questo magazzino con lock
            $ultimoCarico = $this->getUltimoCarico($magazzinoId);
            
            // Prossimo carico = ultimo + 1
            $prossimoCarico = $ultimoCarico + 1;
            
            return new CodiceArticolo($magazzinoId, $prossimoCarico);
        });
    }
    
    /**
     * Ottiene ultimo numero carico per magazzino
     * 
     * @param int $magazzinoId
     * @return int
     */
    private function getUltimoCarico(int $magazzinoId): int
    {
        // Ottieni TUTTI gli articoli per questo magazzino e trova il numero più alto
        $articoli = $this->codiciQuery()
            ->where('categoria_merceologica_id', $magazzinoId)
            ->lockForUpdate()  // Pessimistic lock
            ->get();
        
        if ($articoli->isEmpty()) {
            return 0;  // Primo carico per questo magazzino
        }
        
        $maxCarico = 0;
        
        foreach ($articoli as $articolo) {
            // Parse codice: "2-245" → 245
            try {
                $codiceBase = $articolo->codice_base ?: $articolo->codice;
                $codiceVO = CodiceArticolo::fromString($codiceBase);
                $carico = $codiceVO->getCarico();
                if ($carico > $maxCarico) {
                    $maxCarico = $carico;
                }
            } catch (\InvalidArgumentException $e) {
                // Ignora codici non parsabili
                continue;
            }
        }
        
        return $maxCarico;
    }
    
    /**
     * Verifica se codice esiste già
     * 
     * @param CodiceArticolo $codice
     * @return bool
     */
    public function codiceEsiste(CodiceArticolo $codice): bool
    {
        $value = $codice->toString();
        return $this->codiciQuery()
            ->where('codice', $value)
            ->orWhere('codice_base', $value)
            ->exists();
    }
    
    /**
     * Ottieni prossimo codice carico disponibile
     * Salta eventuali buchi nella numerazione
     * 
     * @param int $magazzinoId
     * @return CodiceArticolo
     */
    public function prossimoCodiceDisponibile(int $magazzinoId): CodiceArticolo
    {
        $codice = $this->generaProssimoCodice($magazzinoId);
        
        // Verifica se esiste già (edge case)
        while ($this->codiceEsiste($codice)) {
            $carico = $codice->getCarico() + 1;
            $codice = new CodiceArticolo($magazzinoId, $carico);
        }
        
        return $codice;
    }
}

