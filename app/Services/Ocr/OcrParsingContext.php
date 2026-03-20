<?php

namespace App\Services\Ocr;

use App\Services\OcrService;

class OcrParsingContext
{
    public function __construct(
        public readonly OcrService $service,
        public readonly DocumentProfile $profile,
        public readonly ?string $pdfPath,
    ) {
    }
}
