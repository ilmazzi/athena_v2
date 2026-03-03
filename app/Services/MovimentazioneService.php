<?php

namespace App\Services;

use App\Domain\Magazzino\DTOs\MovimentazioneDTO;
use App\Models\Movimentazione;
use App\Models\MovimentazioneDettaglio;
use App\Models\Articolo;
use Illuminate\Support\Facades\DB;

/**
 * Service per gestione movimentazioni tra magazzini
 * 
 * Business Rules:
 * - Trasferimenti tra sedi
 * - Decrementa giacenza origine
 * - Incrementa giacenza destinazione
 * - Traccia storico completo
 */
class MovimentazioneService
{
    public function __construct(
        private readonly GiacenzaService $giacenzaService,
    ) {
    }
    
    /**
     * Esegui movimentazione tra magazzini
     * 
     * Workflow:
     * 1. Decrementa giacenza magazzino origine
     * 2. Incrementa/crea giacenza magazzino destinazione
     * 3. Aggiorna magazzino_id dell'articolo
     * 4. Registra movimentazione
     * 
     * @param MovimentazioneDTO $dto
     * @return Movimentazione
     */
    public function eseguiMovimentazione(MovimentazioneDTO $dto): Movimentazione
    {
        return DB::transaction(function () use ($dto) {
            // Verifica articolo esiste
            $articolo = Articolo::findOrFail($dto->articoloId);
            
            // Verifica giacenza origine sufficiente
            if (!$this->giacenzaService->verificaDisponibilita($dto->articoloId, $dto->quantita)) {
                throw new \DomainException(
                    "Giacenza insufficiente nel magazzino origine per articolo ID {$dto->articoloId}"
                );
            }
            
            // Trasferisci giacenza
            $this->giacenzaService->trasferisci(
                $dto->articoloId,
                $dto->magazzinoDestinazioneId,
                $dto->quantita
            );
            
            // Registra movimentazione
            $movimentazione = Movimentazione::create($dto->toModelArray());

            // Registra dettaglio articolo per ricerche/storico
            MovimentazioneDettaglio::create([
                'movimentazione_id' => $movimentazione->id,
                'articolo_id' => $dto->articoloId,
                'quantita' => $dto->quantita,
                'note' => $dto->note,
            ]);
            
            return $movimentazione->fresh(['articolo', 'magazzinoOrigine', 'magazzinoDestinazione']);
        });
    }
    
    /**
     * Ottieni storico movimentazioni per articolo
     */
    public function getStoricoArticolo(int $articoloId): \Illuminate\Database\Eloquent\Collection
    {
        return Movimentazione::with(['magazzinoOrigine', 'magazzinoDestinazione', 'user'])
            ->where('articolo_id', $articoloId)
            ->orderBy('data_movimentazione', 'desc')
            ->get();
    }
    
    /**
     * Ottieni movimentazioni per magazzino nel periodo
     */
    public function getMovimentazioniMagazzino(
        int $magazzinoId,
        ?\DateTime $da = null,
        ?\DateTime $a = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = Movimentazione::with(['articolo', 'magazzinoOrigine', 'magazzinoDestinazione'])
            ->delMagazzino($magazzinoId);
        
        if ($da && $a) {
            $query->nelPeriodo($da, $a);
        }
        
        return $query->orderBy('data_movimentazione', 'desc')->get();
    }
    
    /**
     * Ottieni report movimentazioni
     */
    public function reportMovimentazioni(
        int $magazzinoId,
        \DateTime $da,
        \DateTime $a
    ): array {
        $entrate = Movimentazione::inEntrata($magazzinoId)
            ->nelPeriodo($da, $a)
            ->count();
        
        $uscite = Movimentazione::inUscita($magazzinoId)
            ->nelPeriodo($da, $a)
            ->count();
        
        return [
            'entrate' => $entrate,
            'uscite' => $uscite,
            'saldo' => $entrate - $uscite,
            'periodo' => [
                'da' => $da->format('Y-m-d'),
                'a' => $a->format('Y-m-d'),
            ],
        ];
    }
}

