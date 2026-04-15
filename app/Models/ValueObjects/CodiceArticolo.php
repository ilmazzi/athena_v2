<?php

namespace App\Models\ValueObjects;

class CodiceArticolo
{
    public function __construct(
        public readonly int $magazzinoId,
        public readonly int $numero
    ) {}

    public static function fromString(string $codice): self
    {
        // Formato: M-N dove M è il magazzino e N è il progressivo numerico
        // (accetta anche storici con zeri iniziali, es: 5-00042)
        if (!preg_match('/^(\d+)-(\d+)$/', $codice, $matches)) {
            throw new \InvalidArgumentException("Formato codice non valido: {$codice}");
        }

        return new self(
            magazzinoId: (int) $matches[1],
            numero: (int) $matches[2]
        );
    }
    public function toDisplayString(): string
    {
        $prefix = match ($this->magazzinoId) {
            8 => 'D',
            13 => '1',
            14 => '2',
            15 => '3',
            16 => '4',
            17 => '5',
            18 => '6',
            19 => '7',
            20 => '8',
            21 => '9',
            22 => 'R',
            default => (string) $this->magazzinoId,
        };

        return "{$prefix}-{$this->numero}";
    }


    public function toString(): string
    {
        // Nuovo formato canonico senza zeri iniziali.
        return "{$this->magazzinoId}-{$this->numero}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function getCarico(): int
    {
        return $this->numero;
    }

    public function getMagazzinoId(): int
    {
        return $this->magazzinoId;
    }
}
