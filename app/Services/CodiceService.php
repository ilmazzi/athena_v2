<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
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
    public function __construct(
        private readonly MagazzinoLogicoService $magazzinoLogicoService,
    ) {
    }

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

    private function categorieQuery()
    {
        return CategoriaMerceologica::query()
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
            $magazzinoCode = $this->resolveMagazzinoCode($magazzinoId);

            // Trova ultimo carico per questo magazzino con lock
            $ultimoCarico = $this->getUltimoCarico($magazzinoCode);
            
            // Prossimo carico = ultimo + 1
            $prossimoCarico = $ultimoCarico + 1;
            
            return new CodiceArticolo($magazzinoCode, $prossimoCarico);
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
        $prefix = $magazzinoId . '-';
        // Ottieni TUTTI gli articoli per questo magazzino e trova il numero più alto
        $articoli = $this->codiciQuery()
            ->where(function ($query) use ($prefix) {
                $query->where('codice', 'like', $prefix . '%')
                    ->orWhere('codice_base', 'like', $prefix . '%');
            })
            ->lockForUpdate()  // Pessimistic lock
            ->get(['codice', 'codice_base']);
        
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

    private function resolveMagazzinoCode(int $magazzinoId): int
    {
        return $this->magazzinoLogicoService->resolveFromCategoriaId($magazzinoId) ?? $magazzinoId;
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
        $magazzinoCode = $this->resolveMagazzinoCode($magazzinoId);
        $codice = $this->generaProssimoCodice($magazzinoId);
        
        // Verifica se esiste già (edge case)
        while ($this->codiceEsiste($codice)) {
            $carico = $codice->getCarico() + 1;
            $codice = new CodiceArticolo($magazzinoCode, $carico);
        }
        
        return $codice;
    }
}

