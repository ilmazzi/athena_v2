<?php

namespace App\Services\Ocr\Parsers;

use App\Services\Ocr\Contracts\OcrArticoliParser;
use App\Services\Ocr\DocumentProfile;
use App\Services\Ocr\OcrParsingContext;

class TudorArticoliParser implements OcrArticoliParser
{
    public function supports(DocumentProfile $profile): bool
    {
        return $profile->is('tudor');
    }

    public function parse(string $text, OcrParsingContext $context): array
    {
        return $context->service->parseTudorProfileArticoli($text);
    }
}
