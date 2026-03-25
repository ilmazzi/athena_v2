<?php

namespace App\Services\Ocr;

use App\Services\Ocr\Contracts\OcrArticoliParser;

class OcrParserRegistry
{
    /**
     * @param  iterable<OcrArticoliParser>  $parsers
     */
    public function __construct(private readonly iterable $parsers = [])
    {
    }

    public function resolve(DocumentProfile $profile): ?OcrArticoliParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($profile)) {
                return $parser;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        foreach ($this->parsers as $parser) {
            return false;
        }

        return true;
    }
}


