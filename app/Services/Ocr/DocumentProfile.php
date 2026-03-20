<?php

namespace App\Services\Ocr;

class DocumentProfile
{
    public function __construct(
        public readonly string $key,
        public readonly string $tipoDocumento,
        public readonly string $family,
        public readonly array $signals = [],
    ) {
    }

    public function is(string $key): bool
    {
        return $this->key === $key;
    }
}
