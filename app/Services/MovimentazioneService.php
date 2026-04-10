<?php

namespace App\Services;

use App\Domain\Magazzino\DTOs\MovimentazioneDTO;
use App\Models\Movimentazione;
use App\Models\MovimentazioneDettaglio;
use App\Models\Articolo;
use App\Models\Giacenza;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service per gestione movimentazioni tra magazzini
 * 
 * Business Rules:
 * - Trasferimenti interni tra sedi fisiche
 * - Mantiene invariati magazzino logico, categoria merceologica e quantita residua
 * - Aggiorna solo la collocazione operativa (sede/ubicazione)
 * - Traccia storico completo
 */
class MovimentazioneService
{
    public function __construct(
        private readonly GiacenzaService $giacenzaService,
        private readonly MagazzinoLogicoService $magazzinoLogicoService,
    ) {
    }
    
    /**
     * Esegui movimentazione tra magazzini
     * 
     * Workflow:
     * 1. Verifica disponibilita operativa nella sede origine
     * 2. Sposta fisicamente la giacenza nella sede destinazione
     * 3. Mantiene invariati magazzino/categoria di business
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
            
            $giacenzaOrigine = $this->resolveGiacenzaOrigine($dto);
            if (!$giacenzaOrigine || !$giacenzaOrigine->hasDisponibilita($dto->quantita)) {
                \Log::error('❌ Giacenza insufficiente (movimentazione)', [
                    'articolo_id' => $dto->articoloId,
                    'quantita_richiesta' => $dto->quantita,
                    'magazzino_origine_id' => $dto->magazzinoOrigineId,
                    'magazzino_destinazione_id' => $dto->magazzinoDestinazioneId,
                    'giacenza_id' => $giacenzaOrigine?->id,
                    'giacenza_sede_id' => $giacenzaOrigine?->sede_id,
                    'giacenza_quantita' => $giacenzaOrigine?->quantita,
                    'giacenza_quantita_residua' => $giacenzaOrigine?->quantita_residua,
                ]);
                throw new \DomainException(
                    "Giacenza insufficiente nel magazzino origine per articolo ID {$dto->articoloId}"
                );
            }
            
            $this->spostaArticoloTraSedi($dto);
            
            // Registra movimentazione
            $movimentazione = Movimentazione::create($dto->toModelArray());

            // Registra dettaglio articolo per ricerche/storico
            MovimentazioneDettaglio::create([
                'movimentazione_id' => $movimentazione->id,
                'articolo_id' => $dto->articoloId,
                'prodotto_finito_id' => $dto->prodottoFinitoId,
                'quantita' => $dto->quantita,
                'note' => $dto->note,
            ]);
            
            return $movimentazione->fresh(['dettagli.articolo', 'magazzinoPartenza', 'magazzinoDestinazione', 'creataDa']);
        });
    }

    public function creaMovimentazioneMaster(
        int $magazzinoOrigineId,
        int $magazzinoDestinazioneId,
        string $dataMovimentazione,
        ?string $note = null,
        ?string $trasportoMezzo = null,
        ?string $aspettoBeni = null,
        ?string $colli = null,
        ?string $vettore = null
    ): Movimentazione {
        return Movimentazione::create([
            'magazzino_partenza_id' => $magazzinoOrigineId,
            'magazzino_logico_partenza' => $this->magazzinoLogicoService->resolveFromCategoriaId($magazzinoOrigineId),
            'magazzino_destinazione_id' => $magazzinoDestinazioneId,
            'magazzino_logico_destinazione' => $this->magazzinoLogicoService->resolveFromCategoriaId($magazzinoDestinazioneId),
            'data_movimentazione' => $dataMovimentazione,
            'note' => $note,
            'trasporto_mezzo' => $trasportoMezzo,
            'aspetto_beni' => $aspettoBeni,
            'colli' => $colli,
            'vettore' => $vettore,
            'numero_documento' => $this->generateNumeroDocumento(),
            'creata_da' => Auth::id(),
        ]);
    }

    public function eseguiMovimentazioneDettaglio(Movimentazione $movimentazione, MovimentazioneDTO $dto): Movimentazione
    {
        return DB::transaction(function () use ($movimentazione, $dto) {
            $articolo = Articolo::findOrFail($dto->articoloId);
            
            $giacenzaOrigine = $this->resolveGiacenzaOrigine($dto);
            if (!$giacenzaOrigine || !$giacenzaOrigine->hasDisponibilita($dto->quantita)) {
                \Log::error('❌ Giacenza insufficiente (movimentazione dettaglio)', [
                    'articolo_id' => $dto->articoloId,
                    'quantita_richiesta' => $dto->quantita,
                    'magazzino_origine_id' => $dto->magazzinoOrigineId,
                    'magazzino_destinazione_id' => $dto->magazzinoDestinazioneId,
                    'giacenza_id' => $giacenzaOrigine?->id,
                    'giacenza_sede_id' => $giacenzaOrigine?->sede_id,
                    'giacenza_quantita' => $giacenzaOrigine?->quantita,
                    'giacenza_quantita_residua' => $giacenzaOrigine?->quantita_residua,
                ]);
                throw new \DomainException(
                    "Giacenza insufficiente nel magazzino origine per articolo ID {$dto->articoloId}"
                );
            }
            
            $this->spostaArticoloTraSedi($dto);
            
            $dettaglio = MovimentazioneDettaglio::where('movimentazione_id', $movimentazione->id)
                ->where('articolo_id', $dto->articoloId)
                ->first();
            
            if ($dettaglio) {
                $dettaglio->quantita += $dto->quantita;
                $dettaglio->note = $dto->note ?: $dettaglio->note;
                if ($dto->prodottoFinitoId) {
                    $dettaglio->prodotto_finito_id = $dto->prodottoFinitoId;
                }
                $dettaglio->save();
            } else {
                MovimentazioneDettaglio::create([
                    'movimentazione_id' => $movimentazione->id,
                    'articolo_id' => $dto->articoloId,
                    'prodotto_finito_id' => $dto->prodottoFinitoId,
                    'quantita' => $dto->quantita,
                    'note' => $dto->note,
                ]);
            }
            
            return $movimentazione->fresh(['dettagli.articolo', 'magazzinoPartenza', 'magazzinoDestinazione', 'creataDa']);
        });
    }

    private function generateNumeroDocumento(): string
    {
        $anno = now()->format('Y');
        $prefix = "MOV-{$anno}-";
        $progressivo = 1;

        $numeri = Movimentazione::whereYear('data_movimentazione', $anno)
            ->where('numero_documento', 'like', $prefix . '%')
            ->pluck('numero_documento');

        foreach ($numeri as $numero) {
            if (str_starts_with($numero, $prefix)) {
                $suffix = substr($numero, strlen($prefix));
                if (ctype_digit($suffix) && strlen($suffix) <= 6) {
                    $progressivo = max($progressivo, (int) $suffix + 1);
                }
            }
        }

        return $prefix . str_pad((string) $progressivo, 4, '0', STR_PAD_LEFT);
    }

    private function resolveGiacenzaOrigine(MovimentazioneDTO $dto): ?Giacenza
    {
        return Giacenza::where('articolo_id', $dto->articoloId)
            ->when($dto->sedeOrigineId, function ($query) use ($dto) {
                $query->where('sede_id', $dto->sedeOrigineId);
            }, function ($query) use ($dto) {
                $query->where('categoria_merceologica_id', $dto->magazzinoOrigineId);
            })
            ->orderByDesc('quantita_residua')
            ->orderByDesc('quantita')
            ->first();
    }

    private function spostaArticoloTraSedi(MovimentazioneDTO $dto): void
    {
        if (!$dto->sedeDestinazioneId) {
            throw new \DomainException("Sede destinazione mancante per articolo ID {$dto->articoloId}");
        }

        $this->giacenzaService->spostaSede(
            articoloId: $dto->articoloId,
            sedeDestinazioneId: $dto->sedeDestinazioneId,
            quantita: $dto->quantita,
            sedeOrigineId: $dto->sedeOrigineId
        );
    }
    
    /**
     * Ottieni storico movimentazioni per articolo
     */
    public function getStoricoArticolo(int $articoloId): \Illuminate\Database\Eloquent\Collection
    {
        return Movimentazione::with(['dettagli.articolo', 'magazzinoPartenza', 'magazzinoDestinazione', 'creataDa'])
            ->whereHas('dettagli', function ($q) use ($articoloId) {
                $q->where('articolo_id', $articoloId);
            })
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
        $query = Movimentazione::with(['dettagli.articolo', 'magazzinoPartenza', 'magazzinoDestinazione'])
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
