<?php

namespace App\Services\Ocr;

class DocumentProfileDetector
{
    public function detect(string $text, string $tipoDocumento): DocumentProfile
    {
        $normalized = mb_strtoupper($text);

        if ($tipoDocumento === 'fattura' && str_contains($normalized, 'POMELLATO')) {
            if (
                preg_match('/IN\s*VOICE|INVOICE/', $normalized)
                || preg_match('/\d{4}\/VE\/\d+/', $normalized)
                || preg_match('/EWFP\d/', $normalized)
            ) {
                return new DocumentProfile('pomellato_fattura', $tipoDocumento, 'pomellato', ['keyword' => 'POMELLATO']);
            }
        }

        if ($tipoDocumento === 'ddt' && str_contains($normalized, 'POMELLATO')) {
            if (
                str_contains($normalized, 'DOCUMENTO DI TRASPORTO')
                || preg_match('/\bDDT\b/', $normalized)
                || preg_match('/^\s*\d{1,3}\s+[A-Z0-9]{4,10}\s+[A-Z0-9]{3,6}\s+[A-Z0-9]{3,6}\s+\d{1,3}/m', $normalized)
            ) {
                return new DocumentProfile('pomellato_ddt', $tipoDocumento, 'pomellato', ['keyword' => 'POMELLATO']);
            }
        }

        if (str_contains($normalized, 'ROLEX') && str_contains($normalized, 'REFERENZA')) {
            return new DocumentProfile('rolex', $tipoDocumento, 'rolex', ['keyword' => 'ROLEX']);
        }

        if (str_contains($normalized, 'SWATCH GROUP')) {
            return new DocumentProfile('swatch_group', $tipoDocumento, 'swatch_group', ['keyword' => 'SWATCH GROUP']);
        }

        if (
            str_contains($normalized, 'LISTA ANALITICA')
            && (
                str_contains($normalized, 'COD. ARTICOLO')
                || str_contains($normalized, 'N. SERIE')
            )
            && (
                preg_match('/[A-Z0-9\-]{8,24}\s+[A-Z0-9]{6,10}\s+.+?\s+\d+\s*PCE\s+[\d\.,]+\s+[\d\.,]+/m', $normalized)
                || str_contains($normalized, 'TUDOR')
            )
        ) {
            return new DocumentProfile('tudor', $tipoDocumento, 'tudor', ['keyword' => 'TUDOR']);
        }

        if (
            str_contains($normalized, 'IDANDI')
            || preg_match('/\b(IDAN|DANDI|ANDI|SATC|SAT|SATO)\b/', $normalized)
        ) {
            return new DocumentProfile('idandi', $tipoDocumento, 'idandi', ['keyword' => 'IDANDI']);
        }

        if (
            str_contains($normalized, 'BERING')
            || str_contains($normalized, 'ITEM NO')
            || preg_match('/^\s*[0-9]{4,6}(?:-[A-Z0-9]{2,})?\s+.+?\s+[0-9]{1,5}\s+[0-9]{1,5}\s+[0-9]{1,5}$/m', $normalized)
        ) {
            return new DocumentProfile('bering', $tipoDocumento, 'bering', ['keyword' => 'BERING']);
        }

        if (str_contains($normalized, 'MARCO BICEGO')) {
            return new DocumentProfile('marco_bicego', $tipoDocumento, 'marco_bicego', ['keyword' => 'MARCO BICEGO']);
        }

        if (
            str_contains($normalized, 'DODO')
            && str_contains($normalized, 'STYLE')
            && str_contains($normalized, 'ITEM')
            && (
                str_contains($normalized, 'GROSS AMOUNT')
                || str_contains($normalized, 'INVOICE NUMBER')
                || preg_match('/^[A-Z]{3}\d{4}\s+[A-Z0-9]{3,}\s+[A-Z0-9]{3,}\s+[A-Z0-9]{1,4}\s+[\d\.,]+\s+[\d\.,]+\s+[\d\.,]+\s+[\d\.,]+\s+[\d\.,]+\s+[\d\.,]+$/m', $normalized)
            )
        ) {
            return new DocumentProfile('dodo', $tipoDocumento, 'dodo', ['keyword' => 'DODO']);
        }

        return new DocumentProfile(
            $tipoDocumento === 'fattura' ? 'generic_fattura' : 'generic_ddt',
            $tipoDocumento,
            'generic'
        );
    }
}
