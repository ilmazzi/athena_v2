<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione per l'integrazione con Tesseract OCR
    |
    */

    'tesseract_path' => env(
        'TESSERACT_PATH',
        PHP_OS_FAMILY === 'Windows'
            ? 'C:/Program Files/Tesseract-OCR/tesseract.exe'
            : 'tesseract'
    ),
    
    'tesseract_lang' => env('TESSERACT_LANG', 'ita'),
    
    'confidence_threshold' => env('OCR_CONFIDENCE_THRESHOLD', 70),

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'pdfs' => 'ocr/pdfs',
        'images' => 'ocr/images',
        'temp' => 'ocr/temp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Options
    |--------------------------------------------------------------------------
    */

    'processing' => [
        'dpi' => 300, // DPI per conversione PDF → Immagine
        'format' => 'png', // Formato immagine
        'timeout' => 120, // Timeout in secondi
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Patterns
    |--------------------------------------------------------------------------
    |
    | Pattern regex per riconoscimento campi
    |
    */

    'patterns' => [
        // Numero DDT - pattern multipli più flessibili
        'numero_ddt' => [
            '/NR\.?\s*DOCUM\.?\s*MAGAZZINO\s+([A-Z0-9\/\-]+)/i', // NR. Docum. Magazzino EWFP01/2024/03/19115 (POMELLATO)
            '/^([A-Z0-9]{6,})\s+\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4}\b/m', // Nr + Data sulla stessa riga (es: 24CWS04815 04/12/2024)
            '/DISPATCH\s+NO\.?\s*[:#]?\s*(\d{4,10})/i', // Dispatch No. 1163597 (BERING)
            '/DOCUMENTO\s+DI\s+TRASPORTO[^\r\n]*\|\s*(\d{1,6})\s+\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4}/i', // Documento di trasporto | 352 23/04/2024
            '/N\.?\s*RO\s*DOC\.?\s*\|?\s*(\d{1,6})\s+\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4}/i', // N.ro doc. 352 23/04/2024
            '/NR\.\s*DATA\s*AGENTE\s*CAUSALE\s*PAGINA\s*\n\s*([A-Z0-9\/\-]*\d[A-Z0-9\/\-]*)/i', // Tabella: Nr. Data Agente...
            '/DOCUMENTO\s+DI\s+TRASPORTO[\s\S]{0,120}?\n\s*([A-Z0-9\/\-]*\d[A-Z0-9\/\-]*)/i', // Documento di Trasporto + numero (richiede almeno una cifra)
            '/BOLLA\s+DI\s+CONSEGNA\s+N[°\s]*(\d{5,10})/i', // Bolla di consegna N° 50042826 (SWATCH)
            '/PARCEL\s+NUMERO[:\s\-]*(\d{10,20})/i', // Parcel numero - 76124569052111002
            '/DDT[:\s#\/\-]*N?[°\s\.]*(\d{1,6}[\/-]\d{2,4})/i', // DDT N. 123/2025
            '/DDT[:\s#\/\-]*(\d{4,10})/i', // DDT 0001234567
            '/DOCUMENTO[:\s]*(\d{1,6}[\/-]\d{2,4})/i', // DOCUMENTO 123/2025
            '/PACKING\s+LIST[:\s#]*(\d{5,20})/i', // PACKING LIST 123456789
            '/SHIPPING\s+N[O°\.][:\s]*(\d{5,20})/i', // SHIPPING NO: 123456789
            '/DELIVERY\s+NOTE[:\s#]*(\d{5,20})/i', // DELIVERY NOTE 123456789
            '/N[°\.]?\s*(\d{1,6}[\/-]\d{2,4})/i', // N° 123/2025
        ],
        
        // Numero Fattura - pattern multipli
        'numero_fattura' => [
            '/FATTURA[:\s#\/\-]*N?[°\s\.]*(\d{1,6}[\/-]\d{2,4})/i', // FATTURA N. 123/2025
            '/FATTURA[:\s#\/\-]*(\d{4,10})/i', // FATTURA 0001234567
            '/FATT?[:\s]*(\d{1,6}[\/-]\d{2,4})/i', // FATT 123/2025
        ],
        
        // Data - formati multipli (italiano, europeo, americano)
        'data' => [
            '/DEL\s+(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})/i', // del 04/09/2024
            '/SPEDITO\s+(\d{1,2}\s+[A-Z]{3}\s+\d{4})/i', // SPEDITO 04 DIC 2024
            '/SPEDITO\s+(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', // SPEDITO 04/12/2024
            '/DATE[:\s]*(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})/i', // Date: 19.10.2023
            '/DOCUMENTO\s+DI\s+TRASPORTO[^\r\n]*\|\s*\d{1,6}\s+(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})/i', // Documento di trasporto | 352 23/04/2024
            '/N\.?\s*RO\s*DOC\.?.*?(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})/i', // N.ro doc. 352 23/04/2024
            '/(\d{2}\.\d{2}\.\d{4})/i', // 26.12.2024 (formato svizzero/tedesco)
            '/DATA[:\s]*(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{2,4})/i', // DATA: 14/10/2025
            '/DATE[:\s]*(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{2,4})/i', // DATE: 14/10/2025
            '/(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})\s*$/m', // 14/10/2025 a fine riga
            '/DEL[:\s]*(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{2,4})/i', // DEL: 14/10/2025
            '/(\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})/i', // 2025-12-26 (ISO format)
        ],
        
        // Partita IVA - pattern multipli
        'partita_iva' => [
            '/P\.?\s*I\.?V\.?A[:\s]*([A-Z]{2})?\s*(\d{11})/i', // P.IVA: IT12345678901
            '/PARTITA\s+IVA[:\s]*([A-Z]{2})?\s*(\d{11})/i', // PARTITA IVA: 12345678901
            '/P\.?\s*I\.?V\.?A[:\s]*(\d{11})/i', // P.IVA: 12345678901
            '/\b([A-Z]{2}\d{11})\b/', // IT12345678901 (formato compatto)
            '/P\.?\s*I\.?V\.?A[:\s]*([A-Z]{2})?\s*([0-9IO]{11})/i', // P.IVA con OCR I/O
            '/\b([A-Z]{2}[0-9IO]{11})\b/', // ITI2047960516 (OCR)
            '/VAT\s+NO\.?[:\s]*([A-Z]{2})?\s*([0-9IO]{11})/i', // VAT No.: IT04430000960
        ],
        
        // Importo - pattern multipli
        'importo' => [
            '/TOTALE[:\s]*€?\s*(\d{1,10}[,\.]\d{2})/i', // TOTALE: € 1.234,56
            '/IMPORTO[:\s]*€?\s*(\d{1,10}[,\.]\d{2})/i', // IMPORTO: 1.234,56
            '/€\s*(\d{1,10}[,\.]\d{2})\s*$/m', // € 1.234,56 a fine riga
            '/TOTALE\s+DOCUMENTO[:\s]*€?\s*(\d{1,10}[,\.]\d{2})/i', // TOTALE DOCUMENTO: 1.234,56
        ],
        
        // Quantità articoli
        'quantita' => [
            '/TOTALE\s+QUANTIT.\s*([0-9]{1,5}[,\.]\d{2})\s*(?:PZ|PC|Pcs?)?/i', // Totale quantità 1,00 PZ (tollerante OCR)
            '/N[°\.]?\s*COLLI[\s:]*(\d{1,5})/i', // N. colli 0001 (SWATCH) - PRIORITÀ ALTA
            '/(\d{1,5})\s+PC\b/i', // 1 PC (formato internazionale)
            '/QUANTITY[:\s]*(\d{1,5})/i', // Quantity: 1
            '/QTY[:\s]*(\d{1,5})/i', // Qty: 1
            '/COLLI[:\s]*(\d{1,5})/i', // COLLI: 50
            '/PEZZI[:\s]*(\d{1,5})/i', // PEZZI: 100
            '/Q\.?T[AÀ]?[:\s]*(\d{1,5})/i', // Q.TÀ: 100 o QTA: 100
        ],
        
        // Ragione Sociale Fornitore (per matching)
        'ragione_sociale' => [
            '/^\s*(POMELLATO\s+SPA)\b/im', // POMELLATO SPA
            '/^\s*([A-Z][A-Z\s\.&\-]{3,})\s*\n\s*Indirizzo\s+spedizione/im', // Intestazione fornitore (es: MARCO BICEGO)
            '/^\s*([A-Z][A-Z\s\.&\-]+S\.P\.A\.?)\s*-\s*Unipersonale/im', // Footer società (S.P.A.)
            '/^\s*([A-Z][A-Z\s\.&\-]+S\.R\.L\.?)\s*-\s*/im', // Footer società (S.R.L.)
            '/^\s*(idandi\s+sas[^\r\n]{0,60})$/im', // idandi sas di Andrea Chinali & C.
            '/^\s*(BERING\s+TIME\s+APS[^\r\n]{0,60})$/im', // BERING Time ApS
            '/FORNITORE[:\s]*([A-Z0-9\s\.&\-]{5,60})/i', // FORNITORE: ...
            '/MITTENTE[:\s]*([A-Z0-9\s\.&\-]{5,60})/i', // MITTENTE: ...
            '/SHIP\s+FROM[:\s]*([A-Z0-9\s\.&\-,]{5,60})/i', // SHIP FROM: ...
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fornitori Template
    |--------------------------------------------------------------------------
    |
    | Template specifici per fornitori ricorrenti
    | Verranno popolati dinamicamente con machine learning
    |
    */

    'fornitori_templates' => [
        // Esempi:
        // 'fornitore_1' => [
        //     'numero_ddt_position' => ['x' => 500, 'y' => 100],
        //     'data_position' => ['x' => 500, 'y' => 150],
        // ],
    ],

];

