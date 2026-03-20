<?php

namespace App\Services\Ocr\Parsers;

use App\Services\Ocr\Contracts\OcrArticoliParser;
use App\Services\Ocr\DocumentProfile;
use App\Services\Ocr\OcrParsingContext;

class SwatchGroupArticoliParser implements OcrArticoliParser
{
    public function supports(DocumentProfile $profile): bool
    {
        return $profile->is('swatch_group');
    }

    public function parse(string $text, OcrParsingContext $context): array
    {
        return $context->service->parseSwatchGroupProfileArticoli($text);
    }
}
