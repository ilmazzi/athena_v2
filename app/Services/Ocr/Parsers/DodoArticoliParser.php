<?php

namespace App\Services\Ocr\Parsers;

use App\Services\Ocr\Contracts\OcrArticoliParser;
use App\Services\Ocr\DocumentProfile;
use App\Services\Ocr\OcrParsingContext;

class DodoArticoliParser implements OcrArticoliParser
{
    public function supports(DocumentProfile $profile): bool
    {
        return $profile->is('dodo');
    }

    public function parse(string $text, OcrParsingContext $context): array
    {
        return $context->service->parseDodoProfileArticoli($text);
    }
}
