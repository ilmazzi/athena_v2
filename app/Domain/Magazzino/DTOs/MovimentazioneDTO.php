<?php

namespace App\Domain\Magazzino\DTOs;

/**
 * DTO per movimentazioni tra magazzini/sedi
 */
class MovimentazioneDTO
{
    public function __construct(
        public readonly int $articoloId,
        public readonly int $quantita,
        public readonly int $magazzinoOrigineId,
        public readonly int $magazzinoDestinazioneId,
        public readonly string $dataMovimentazione,
        public readonly ?string $note = null,
        public readonly ?int $prodottoFinitoId = null,
    ) {
    }

    /**
     * Converti in array per il modello
     */
    public function toModelArray(): array
    {
        return [
            'articolo_id' => $this->articoloId,
            'quantita' => $this->quantita,
            'magazzino_origine_id' => $this->magazzinoOrigineId,
            'magazzino_destinazione_id' => $this->magazzinoDestinazioneId,
            'data_movimentazione' => $this->dataMovimentazione,
            'note' => $this->note,
            'user_id' => auth()->id() ?? 1, // Default user ID se non autenticato
            'numero_documento' => $this->generateNumeroDocumento(),
        ];
    }

    /**
     * Genera numero documento DDT
     */
    private function generateNumeroDocumento(): string
    {
        $anno = date('Y');
        $timestamp = time();
        return "MOV-{$anno}-{$timestamp}";
    }

    /**
     * Validazione dei dati
     */
    public function validate(): bool
    {
        return $this->articoloId > 0 
            && $this->quantita > 0
            && $this->magazzinoOrigineId > 0
            && $this->magazzinoDestinazioneId > 0
            && $this->magazzinoOrigineId !== $this->magazzinoDestinazioneId
            && !empty($this->dataMovimentazione);
    }
}
