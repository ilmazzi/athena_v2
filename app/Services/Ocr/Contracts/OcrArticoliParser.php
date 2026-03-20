<?php

namespace App\Services\Ocr\Contracts;

use App\Services\Ocr\DocumentProfile;
use App\Services\Ocr\OcrParsingContext;

interface OcrArticoliParser
{
    public function supports(DocumentProfile $profile): bool;

    public function parse(string $text, OcrParsingContext $context): array;
}
