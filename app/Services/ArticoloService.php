<?php

namespace App\Services;

use App\Domain\Magazzino\DTOs\CaricoArticoloDTO;
use App\Domain\Magazzino\DTOs\ScaricoArticoloDTO;
use App\Domain\Magazzino\Exceptions\ArticoloNonTrovatoException;
use App\Models\Articolo;
use Illuminate\Support\Facades\DB;

/**
 * Service principale per gestione Articoli
 * 
 * Orchestrator per business logic completa:
 * - Carico articoli con codice progressivo
 * - Creazione giacenza 1:1
 * - Scarico articoli
 * - Ricerca e filtri
 * 
 * ⚠️ COMPLIANCE: NO prezzo_vendita, NO data_scarico in DB!
 */
class ArticoloService
{
    public function __construct(
        private readonly CodiceService $codiceService,
        private readonly GiacenzaService $giacenzaService,
    ) {
    }
    
    /**
     * Carica un articolo da DDT/Fattura
     * 
     * Workflow completo:
     * 1. Genera codice progressivo
     * 2. Crea articolo
     * 3. Crea giacenza 1:1
     * 4. (Opzionale) Stampa etichetta automatica
     * 
     * @param CaricoArticoloDTO $dto
     * @return Articolo
     */
    public function caricaArticolo(CaricoArticoloDTO $dto): Articolo
    {
        return DB::transaction(function () use ($dto) {
            // 1. Genera codice progressivo per magazzino
            $codice = $this->codiceService->prossimoCodiceDisponibile($dto->magazzinoId);
            
            // 2. Crea articolo
            $dataArticolo = $dto->toModelArray();
            $dataArticolo['codice'] = $codice->toString();
            
            $articolo = Articolo::create($dataArticolo);
            
            // 3. Crea giacenza 1:1 (obbligatoria)
            $this->giacenzaService->creaGiacenza(
                $articolo->id,
                $dto->magazzinoId,
                $dto->quantita
            );
            
            // Ricarica con relazioni
            return $articolo->fresh(['giacenza', 'magazzino']);
        });
    }
    
    /**
     * Carica multipli articoli in batch (da DDT/Fattura)
     * 
     * @param array<CaricoArticoloDTO> $dtos
     * @return array<Articolo>
     */
    public function caricaArticoliBatch(array $dtos): array
    {
        return DB::transaction(function () use ($dtos) {
            $articoli = [];
            
            foreach ($dtos as $dto) {
                $articoli[] = $this->caricaArticolo($dto);
            }
            
            return $articoli;
        });
    }
    
    /**
     * Scarica articolo (vendita, trasferimento, etc)
     * 
     * Workflow:
     * 1. Decrementa giacenza
     * 2. Se giacenza=0 → stato diventa 'venduto'
     * 3. NO data_scarico salvata!
     * 
     * @param ScaricoArticoloDTO $dto
     * @return Articolo
     */
    public function scaricaArticolo(ScaricoArticoloDTO $dto): Articolo
    {
        return DB::transaction(function () use ($dto) {
            $articolo = Articolo::findOrFail($dto->articoloId);
            
            // Decrementa giacenza
            $this->giacenzaService->decrementa(
                $dto->articoloId,
                $dto->quantita
            );
            
            // Se giacenza = 0, aggiorna stato
            // (fatto automaticamente da Giacenza::decrementa)
            
            return $articolo->fresh(['giacenza']);
        });
    }
    
    /**
     * Trova articolo per ID
     * 
     * @throws ArticoloNonTrovatoException
     */
    public function findById(int $id): Articolo
    {
        $articolo = Articolo::with([
            'giacenza',
            'magazzino',
            'ddtDettaglio.ddt.fornitore',
            'fatturaDettaglio.fattura.fornitore',
        ])->find($id);
        
        if (!$articolo) {
            throw ArticoloNonTrovatoException::conId($id);
        }
        
        return $articolo;
    }
    
    /**
     * Trova articolo per codice
     * 
     * @throws ArticoloNonTrovatoException
     */
    public function findByCodice(string $codice): Articolo
    {
        $articolo = Articolo::with([
                'giacenza',
                'magazzino',
                'ddtDettaglio.ddt.fornitore',
                'fatturaDettaglio.fattura.fornitore',
            ])
            ->where('codice', $codice)
            ->first();
        
        if (!$articolo) {
            throw ArticoloNonTrovatoException::conCodice($codice);
        }
        
        return $articolo;
    }
    
    /**
     * Cerca articoli con filtri
     * 
     * @param array $filtri
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function cerca(array $filtri = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Articolo::with([
            'giacenza',
            'magazzino',
            'ddtDettaglio.ddt.fornitore',
            'fatturaDettaglio.fattura.fornitore',
        ]);
        
        if (isset($filtri['magazzino_id'])) {
            $query->inMagazzino($filtri['magazzino_id']);
        }
        
        if (isset($filtri['stato'])) {
            $query->where('stato', $filtri['stato']);
        }
        
        if (isset($filtri['disponibili']) && $filtri['disponibili']) {
            $query->disponibili();
        }
        
        if (isset($filtri['in_vetrina']) && $filtri['in_vetrina']) {
            $query->inVetrina();
        }
        
        if (isset($filtri['fornitore_id'])) {
            $fornitoreId = (int) $filtri['fornitore_id'];
            $query->whereNull('prodotto_finito_id')
                ->where(function ($q) use ($fornitoreId) {
                    $q->whereHas('fatturaDettaglio.fattura', function ($subQ) use ($fornitoreId) {
                        $subQ->where('fornitore_id', $fornitoreId);
                    })->orWhereHas('ddtDettaglio.ddt', function ($subQ) use ($fornitoreId) {
                        $subQ->where('fornitore_id', $fornitoreId);
                    });
                });
        }
        
        if (isset($filtri['materiale'])) {
            $query->conMateriale($filtri['materiale']);
        }
        
        if (isset($filtri['descrizione'])) {
            $query->where('descrizione', 'like', "%{$filtri['descrizione']}%");
        }
        
        if (isset($filtri['data_carico_da']) && isset($filtri['data_carico_a'])) {
            $query->caricatiNelPeriodo(
                new \DateTime($filtri['data_carico_da']),
                new \DateTime($filtri['data_carico_a'])
            );
        }
        
        return $query->get();
    }
    
    /**
     * Ottieni articoli disponibili per magazzino
     */
    public function getDisponibiliPerMagazzino(int $magazzinoId): \Illuminate\Database\Eloquent\Collection
    {
        return Articolo::with([
                'giacenza',
                'ddtDettaglio.ddt.fornitore',
                'fatturaDettaglio.fattura.fornitore',
            ])
            ->inMagazzino($magazzinoId)
            ->disponibili()
            ->get();
    }
    
    /**
     * Sposta articolo in vetrina
     */
    public function spostaInVetrina(int $articoloId): Articolo
    {
        $articolo = $this->findById($articoloId);
        
        $articolo->update([
            'in_vetrina' => true,
            'stato' => 'in_vetrina'
        ]);
        
        return $articolo->fresh();
    }
    
    /**
     * Rimuovi articolo da vetrina
     */
    public function rimuoviDaVetrina(int $articoloId): Articolo
    {
        $articolo = $this->findById($articoloId);
        
        $articolo->update([
            'in_vetrina' => false,
            'stato' => 'disponibile'
        ]);
        
        return $articolo->fresh();
    }
    
    /**
     * Calcola prezzo vendita suggerito
     * 
     * ⚠️ ATTENZIONE: Questo calcolo è SOLO per riferimento!
     * Il prezzo vendita vero è passato dall'utente alla stampa etichetta
     * NON salvare mai in DB!
     */
    public function calcolaPrezzoVenditaSuggerito(int $articoloId, float $percentualeRicarico = 30.0): float
    {
        $articolo = $this->findById($articoloId);
        return $articolo->calcolaPrezzoVenditaSuggerito($percentualeRicarico);
    }
}
