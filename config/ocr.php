<?php

return [
    'storage' => [
        'pdfs' => 'ocr/pdfs',
        'images' => 'ocr/images',
    ],

    'processing' => [
        'dpi' => 300,
        'timeout' => 120,
    ],

    'tesseract_path' => env('TESSERACT_PATH', 'C:/Program Files/Tesseract-OCR/tesseract.exe'),
    'tesseract_lang' => env('TESSERACT_LANG', 'ita'),

    'confidence_threshold' => 70,

    // Pattern regex per estrarre dati; si possono personalizzare per fornitore
    'patterns' => [
        'numero_ddt' => [
            '/DDT\s*[:\-]?\s*(\w[\w\/\-\.]+)/i',
            '/Documento\s+n\.?\s*(\w[\w\/\-\.]+)/i',
            '/Transport\.\s*Unit\s*(\d+)/i',
            '/D\.?\s*D\.?\s*T\.?(?:\s*nr\.?|\s*n\.?)\s*(\d+)/i',
            '/H\s*U\s*B\s*L\s*O\s*T\s*(\d{6,})/i',
        ],
        'numero_fattura' => [
            '/Fattur[a|e]\s*[:\-]?\s*(\w[\w\/\-\.]+)/i',
        ],
        'data' => [
            '/D\.?\s*D\.?\s*T\.?(?:\s*nr\.?|\s*n\.?)\s*\d+\s*(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/i',
            '/H\s*U\s*B\s*L\s*O\s*T\s*\d+\s*(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/i',
            '/(?:Data|Del)\s*[:\-]?\s*(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/i',
            '/Transport\.\s*Unit\s*\d+[^0-9]+(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/i',
            '/\b(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})\b/',
        ],
        'partita_iva' => [
            '/P\.?(?:IVA|I\.V\.A\.)\s*[:\-]?\s*([A-Z0-9]{2}\s*\d{11}|\d{11})/i',
        ],
        'importo' => [
            '/Totale\s*(?:Documento|Fattura)?\s*[:\-]?\s*([\d\.,]+)/i',
        ],
        'quantita' => [
            '/Totale\s*(?:colli|pezzi|articoli)\s*[:\-]?\s*(\d+)/i',
        ],
        'ragione_sociale' => [
            '/(?:Fornitore|Mittente|Destinatario)\s*[:\-]?\s*(.+)/i',
        ],
    ],
];


