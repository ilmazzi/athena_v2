<?php

namespace App\Services;

use App\Models\Fornitore;
use App\Models\OcrDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    /**
     * Processa un PDF caricato
     */
    public function processPdf(UploadedFile $file, string $tipo): OcrDocument
    {
        // 1. Salva PDF
        $pdfPath = $this->storePdf($file);
        
        // 2. Crea record OcrDocument
        $ocrDocument = OcrDocument::create([
            'tipo' => $tipo,
            'pdf_path' => $pdfPath,
            'pdf_original_name' => $file->getClientOriginalName(),
            'pdf_size' => $file->getSize(),
            'status' => 'processing',
        ]);

        try {
            // 3. Converti PDF → Immagini
            $imagePaths = $this->convertPdfToImages($pdfPath);
            
            // 4. Estrai testo con OCR
            $rawText = $this->extractTextFromImages($imagePaths);
            
            // 5. Struttura dati estratti
            $structuredData = $this->parseExtractedText($rawText, $tipo);
            
            // 6. Trova fornitore automaticamente
            $fornitoreId = $this->findFornitore($structuredData, $rawText);
            
            // 7. Calcola confidence score
            $confidenceScore = $this->calculateConfidence($structuredData);
            
            // 8. Aggiorna OcrDocument
            $ocrDocument->update([
                'ocr_raw_data' => ['text' => $rawText],
                'ocr_structured_data' => $structuredData,
                'confidence_score' => $confidenceScore,
                'status' => 'completed',
                'fornitore_id' => $fornitoreId,
            ]);
            
            // 8. Cleanup immagini temporanee
            $this->cleanupImages($imagePaths);
            
        } catch (\Exception $e) {
            $ocrDocument->update([
                'status' => 'rejected',
                'notes' => 'Errore OCR: ' . $e->getMessage(),
            ]);
            
            throw $e;
        }

        return $ocrDocument->fresh();
    }

    /**
     * Salva PDF nello storage
     */
    protected function storePdf(UploadedFile $file): string
    {
        $path = config('ocr.storage.pdfs');
        $filename = date('Y-m-d_His') . '_' . uniqid() . '.pdf';
        
        return $file->storeAs($path, $filename);
    }

    /**
     * Converti PDF in immagini usando Ghostscript
     */
    protected function convertPdfToImages(string $pdfPath): array
    {
        $fullPath = Storage::path($pdfPath);
        $outputDir = Storage::path(config('ocr.storage.images'));
        
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Usa Ghostscript invece di Imagick (più compatibile Windows)
        $gsPath = $this->findGhostscript();
        
        if (!$gsPath) {
            // Fallback: Usa solo prima pagina senza conversione
            Log::warning('Ghostscript not found, OCR might have lower accuracy');
            return [$fullPath]; // Tesseract può processare PDF direttamente (con limiti)
        }
        
        $imagePaths = [];
        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        
        // Converti PDF in immagini PNG con Ghostscript
        $outputPattern = $outputDir . '/' . $baseName . '_page_%d.png';
        
        $command = sprintf(
            '"%s" -dNOPAUSE -dBATCH -sDEVICE=png16m -r%d -sOutputFile="%s" "%s" 2>&1',
            $gsPath,
            config('ocr.processing.dpi', 300),
            $outputPattern,
            $fullPath
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::error('Ghostscript conversion failed', ['output' => $output]);
            // Fallback a PDF diretto
            return [$fullPath];
        }
        
        // Trova tutti i file PNG generati
        $pattern = $outputDir . '/' . $baseName . '_page_*.png';
        $files = glob($pattern);
        
        if (empty($files)) {
            // Fallback a PDF diretto
            return [$fullPath];
        }
        
        return $files;
    }
    
    /**
     * Trova Ghostscript installato
     */
    protected function findGhostscript(): ?string
    {
        // Percorsi comuni Ghostscript su Windows
        $commonPaths = [
            'C:/Program Files/gs/gs10.04.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.03.1/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.03.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.02.1/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.02.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.01.2/bin/gswin64c.exe',
            'C:/Program Files (x86)/gs/gs10.04.0/bin/gswin32c.exe',
            'C:/Program Files (x86)/gs/gs10.03.1/bin/gswin32c.exe',
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Prova a trovare nella directory gs
        $gsDir = 'C:/Program Files/gs';
        if (is_dir($gsDir)) {
            $dirs = glob($gsDir . '/gs*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $exe = $dir . '/bin/gswin64c.exe';
                if (file_exists($exe)) {
                    return $exe;
                }
            }
        }
        
        // Prova comando globale (se nel PATH)
        exec('where gswin64c 2>nul', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
        
        return null;
    }

    /**
     * Estrai testo da immagini con Tesseract
     */
    protected function extractTextFromImages(array $imagePaths): string
    {
        $fullText = '';
        
        foreach ($imagePaths as $imagePath) {
            $ocr = new TesseractOCR($imagePath);
            $ocr->executable(config('ocr.tesseract_path'));
            $ocr->lang(config('ocr.tesseract_lang', 'ita'));
            $ocr->timeout(config('ocr.processing.timeout', 120));
            
            try {
                $text = $ocr->run();
                $fullText .= $text . "\n\n";
            } catch (\Exception $e) {
                Log::error("OCR failed for image: {$imagePath}", ['error' => $e->getMessage()]);
            }
        }
        
        return trim($fullText);
    }

    /**
     * Parsing testo estratto e strutturazione dati
     */
    protected function parseExtractedText(string $text, string $tipo): array
    {
        $data = [
            'tipo' => $tipo,
            'raw_text_length' => strlen($text),
        ];

        $patterns = config('ocr.patterns');

        // Numero documento (DDT o Fattura) - prova pattern multipli
        $numeroKey = $tipo === 'ddt' ? 'numero_ddt' : 'numero_fattura';
        $numeroPatterns = is_array($patterns[$numeroKey]) ? $patterns[$numeroKey] : [$patterns[$numeroKey]];
        
        foreach ($numeroPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data['numero'] = trim($matches[1]);
                $data['numero_confidence'] = 85;
                break;
            }
        }

        // Data - prova pattern multipli
        $dataPatterns = is_array($patterns['data']) ? $patterns['data'] : [$patterns['data']];
        
        foreach ($dataPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $parsedDate = $this->parseDate($matches[1]);
                if ($parsedDate) {
                    $data['data'] = $parsedDate;
                    $data['data_confidence'] = 80;
                    break;
                }
            }
        }

        // Partita IVA - prova pattern multipli
        $pivaPatterns = is_array($patterns['partita_iva']) ? $patterns['partita_iva'] : [$patterns['partita_iva']];
        
        foreach ($pivaPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Estrai solo i numeri (ultimi 11 caratteri se c'è prefisso paese)
                $piva = isset($matches[2]) ? $matches[2] : $matches[1];
                $piva = preg_replace('/[^\d]/', '', $piva);
                if (strlen($piva) === 11) {
                    $data['partita_iva'] = $piva;
                    $data['partita_iva_confidence'] = 90;
                    break;
                }
            }
        }

        // Importo totale - prova pattern multipli
        $importoPatterns = is_array($patterns['importo']) ? $patterns['importo'] : [$patterns['importo']];
        
        foreach ($importoPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $importo = str_replace(['.', ','], ['', '.'], $matches[1]);
                $data['importo_totale'] = (float) $importo;
                $data['importo_confidence'] = 75;
                break;
            }
        }

        // Quantità articoli/colli - prova pattern multipli
        if (isset($patterns['quantita'])) {
            $qtaPatterns = is_array($patterns['quantita']) ? $patterns['quantita'] : [$patterns['quantita']];
            
            foreach ($qtaPatterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $data['quantita_articoli'] = (int) $matches[1];
                    $data['quantita_confidence'] = 70;
                    break;
                }
            }
        }

        // Ragione Sociale (per auto-match fornitore)
        if (isset($patterns['ragione_sociale'])) {
            $rsPatterns = is_array($patterns['ragione_sociale']) ? $patterns['ragione_sociale'] : [$patterns['ragione_sociale']];
            
            foreach ($rsPatterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $data['ragione_sociale_estratta'] = trim($matches[1]);
                    break;
                }
            }
        }

        // Articoli (parsing complesso)
        $articoli = $this->parseArticoli($text);
        
        // De-duplicazione articoli (per documenti multi-pagina)
        if (!empty($articoli)) {
            $articoli = $this->deduplicateArticoli($articoli);
            $data['articoli'] = $articoli;
            $data['numero_articoli'] = count($articoli);
            $data['articoli_confidence'] = 70; // Confidence media per articoli trovati
        }

        return $data;
    }

    /**
     * Parsing data in vari formati
     */
    protected function parseDate(string $dateString): ?string
    {
        $dateString = trim($dateString);
        
        // Prova vari formati
        $formats = [
            'd.m.Y',    // 26.12.2024 (formato svizzero/tedesco)
            'd/m/Y',    // 14/10/2025 (formato italiano)
            'd-m-Y',    // 14-10-2025
            'Y-m-d',    // 2025-12-26 (ISO)
            'd.m.y',    // 26.12.24
            'd/m/y',    // 14/10/25
            'd-m-y',    // 14-10-25
        ];
        
        foreach ($formats as $format) {
            try {
                $date = \Carbon\Carbon::createFromFormat($format, $dateString);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }

    /**
     * Parsing articoli da tabella
     */
    protected function parseArticoli(string $text): array
    {
        $articoli = [];
        
        // Blacklist: parole che NON sono articoli (intestazioni, indirizzi, ecc)
        $blacklistWords = [
            'invoice', 'fattura', 'ddt', 'totale', 'total', 'subtotal',
            'partner', 'information', 'descrizione', 'description',
            'quantity', 'quantita', 'prezzo', 'price', 'codice', 'code',
            'numero', 'number', 'data', 'date', 'mittente', 'sender',
            'destinatario', 'recipient', 'ordine', 'order',
            // Indirizzi
            'milano', 'lecco', 'roma', 'via', 'viale', 'piazza', 'corso',
            'italy', 'italia', 'switzerland', 'svizzera'
        ];
        
        // Pattern per righe articolo (multi-formato)
        // PRIORITÀ: I pattern più specifici PRIMA, quelli generici DOPO
        $patterns = [
            // Pattern IWC: codice (referenza) + descrizione + quantita + unita (PC) con seriale sulla stessa riga o successiva
            '/^(?:IWC\s+)?([A-Z0-9]{6,15})\s+(.+?)\s+(\d{1,5})\s*(?:PC|PZ|Pcs?)/im',
            // Pattern Citizen/Bulova: codice + descrizione + quantità + separatore + EAN
            '/^([A-Z0-9\-]{3,15})\s+(.+?)\s+(\d{1,5})\s*\|\s*(\d{8,14})$/m',
            '/^([A-Z0-9\-]{3,15})\s+(.+?)\s+(\d{1,5})\s+(\d{8,14})$/m',
            // Pattern 1A: SWATCH GROUP - Con unità esplicita (PZ/PC)
            // Es: "0100352110/05.09.2025 L37594966 CONQU 41mm qtz ACC.BLU,BRACC.ACC 18 1PZ"
            '/\d{10}\/[\d\.,:\s\/]+\s+([\$A-Z0-9\.\-]{4,20})\s+(.+?)\s+[\\\\|\"\(\{\#\s]*(\d{1,3})\s*(?:PZ|PC|Pz|pz|P2|ez|192|Mz|R2|SP|flez|Fà|TPZ|PEPE|TRE|Mailat|PÀ|Tez|È)[\s\)\/\\\\]*/i',
            
            // Pattern 1B: SWATCH GROUP - Senza unità visibile (fallback)
            // Es: "0100031693/23.11,2023 GB743-526 ONCE AGAIN \"
            // Es: "0100031693/23.11,2023 SO29B403 A DASH OF YELLOW"
            '/\d{10}\/[\d\.,:\s\/]+\s+([\$A-Z0-9\.\-]{4,20})\s+([A-Z][A-Z0-9\s\+\-\/,\.]{3,60}?)\s*[\\\\|\"\(\{\#\s]*$/im',
            
            // Pattern 2: Standard con numero ordine/data + codice numerico puro
            // Es: "0100153299/02.08.2024  098000399  CINTURINO ALLIGATORE NERO 18X14  1PZ"
            // Es: "0100153299/02.08,2024  098000399  CINTURINO ALLIGATORE NERO 18X14  1PZ"
            '/\d{10}\/[\d\.,:\s]+\s+(\d{6,12})\s+([A-Z][A-Z\s\d\-\/]{5,80}?)\s+(\d{1,5})\s*(?:PZ|PC|Pz)?/i',
            
            // Pattern 3: Codice 6-12 cifre + Descrizione (con caratteri speciali) + Quantità
            // Es: "098000399  CINTURINO ALLIGATORE NERO 18X14  1 PZ"
            //     "098000399  OROLOGIO DA POLSO REF.ABC-123  2"
            '/^(\d{6,12})\s+([A-Z][\w\s\-\/\.]{5,80}?)\s+(\d{1,5})\s*(?:PZ|PC|PCS)?/im',
            
            // Pattern 4: Codice alfanumerico + descrizione + quantità
            // Es: "ABC-123  Descrizione articolo  5"
            '/^([A-Z0-9\-]{3,15})\s+([A-Z][^\r\n]{10,120}?)\s+(\d{1,5})\s*(?:\|\s*(\d{8,14}))?$/m',
            
            // Pattern 5: Solo codice numerico e quantità separati (SWATCH style vecchio)
            // Es: "20572    1"
            '/^(\d{4,10})\s{2,}(\d{1,5})\s*$/m',
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $articolo = [];
                
                // Determina struttura match basata su numero elementi
                if (count($match) >= 4) {
                    // Pattern con 3 gruppi catturati: codice, descrizione, quantità
                    $articolo['codice'] = strtoupper(trim(str_replace('$', 'S', $match[1]))); // Fix OCR: $ → S
                    $articolo['codice'] = preg_replace('/\s+/', '', $articolo['codice']);
                    $articolo['codice'] = rtrim($articolo['codice'], '.');
                    if (preg_match('/[A-Z0-9][A-Z0-9\.\-]*\d[A-Z0-9\.\-]*/', $articolo['codice'], $codeMatch)) {
                        $articolo['codice'] = $codeMatch[0];
                    }

                    $articolo['descrizione'] = trim($match[2]);
                    // Se quantità è vuota (es: "pz" senza numero), default = 1
                    $qta = trim($match[3]);
                    
                    // Normalizza OCR errors comuni per quantità
                    if (empty($qta)) {
                        $articolo['quantita'] = 1;
                    } else {
                        // Mappa OCR errors → quantità corretta
                        $qta = (int) $qta;
                        if ($qta == 0) $qta = 1;      // vuoto → 1
                        if ($qta == 192) $qta = 1;    // 192 → 1Pz
                        if ($qta == 12) $qta = 1;     // 12 → 1Pz
                        if ($qta == 2) $qta = 1;      // P2 → 1Pz (spesso)
                        $articolo['quantita'] = $qta;
                    }

                    if (isset($match[4])) {
                        $eanCandidate = preg_replace('/[^0-9]/', '', $match[4]);
                        $eanLength = strlen($eanCandidate);
                        if ($eanLength >= 8 && $eanLength <= 14) {
                            $articolo['ean'] = $eanCandidate;
                        }
                    }
                } elseif (count($match) == 3) {
                    // Pattern con 2 gruppi: codice + descrizione (senza quantità esplicita)
                    // Es: Pattern 1B - articoli senza unità visibile
                    $articolo['codice'] = strtoupper(trim(str_replace('$', 'S', $match[1])));
                    $articolo['descrizione'] = trim($match[2]);
                    $articolo['quantita'] = 1; // Default per articoli senza quantità visibile
                }
                
                // Validazioni
                $isValid = true;
                
                // 1. Controlla che codice e quantità esistano
                if (empty($articolo['codice']) || !isset($articolo['quantita']) || $articolo['quantita'] <= 0) {
                    $isValid = false;
                }
                
                // 2. Controlla che quantità sia ragionevole (max 10000)
                if ($articolo['quantita'] > 10000) {
                    $isValid = false;
                }
                
                // 3. Esclude false positive (blacklist) - controlla CODICE e DESCRIZIONE
                $codeLower = strtolower($articolo['codice']);
                $descLower = !empty($articolo['descrizione']) ? strtolower($articolo['descrizione']) : '';
                
                foreach ($blacklistWords as $blackWord) {
                    if (stripos($codeLower, $blackWord) !== false || stripos($descLower, $blackWord) !== false) {
                        $isValid = false;
                        break;
                    }
                }
                
                // 4. Esclude se codice contiene troppe parole (probabilmente header)
                if (str_word_count($articolo['codice']) > 3) {
                    $isValid = false;
                }
                
                // 5. Esclude codici che sembrano CAP (5 cifre esatte, iniziano con 0-3)
                // Esempio: 20146, 23900, 00144
                if (preg_match('/^[0-3]\d{4}$/', $articolo['codice'])) {
                    $isValid = false;
                }
                
                // 6. Esclude se codice è troppo corto (< 4 caratteri) SENZA descrizione
                if (strlen($articolo['codice']) < 4 && empty($articolo['descrizione'])) {
                    $isValid = false;
                }
                
                // 7. Richiede almeno una cifra nel codice (esclude parole spurie OCR)
                if ($isValid && !preg_match('/\d/', $articolo['codice'])) {
                    $isValid = false;
                }

                // 7. Esclude materiale sussidiario (codici che iniziano con Z o descrizione dedicata)
                if ($isValid && preg_match('/^Z[A-Z0-9\-]*/', $articolo['codice'])) {
                    $isValid = false;
                }

                if ($isValid && stripos($descLower, 'materiale sussidiario') !== false) {
                    $isValid = false;
                }

                if ($isValid) {
                    // Cerca numero seriale associato (nelle righe successive)
                    $articolo['numero_seriale'] = $this->extractSerialNumber($text, $articolo['codice']);
                    
                    // Cerca codice EAN/Barcode
                    $articolo['ean'] = $this->extractEAN($text, $articolo['codice']);
                    
                    // Evita duplicati
                    $key = $articolo['codice'];
                    if (!isset($articoli[$key])) {
                        $articoli[$key] = $articolo;
                    }
                }
            }
        }

        // Fallback IWC: blocco "NUMERO DI SERIE" con coppie codice/seriale
        if (preg_match_all('/^\s*([A-Z0-9]{5,})\s*[:\-]\s*([A-Z0-9]+)\b/mi', $text, $serialMatches, PREG_SET_ORDER)) {
            foreach ($serialMatches as $serialMatch) {
                $code = strtoupper(trim($serialMatch[1]));
                $serial = strtoupper(trim($serialMatch[2]));

                if (!preg_match('/\d/', $code)) {
                    continue;
                }

                if (isset($articoli[$code])) {
                    $articoli[$code]['numero_seriale'] = $serial;
                    if (empty($articoli[$code]['quantita'])) {
                        $articoli[$code]['quantita'] = 1;
                    }
                } else {
                    $articoli[$code] = [
                        'codice' => $code,
                        'descrizione' => 'IWC Referenza ' . $code,
                        'quantita' => 1,
                        'numero_seriale' => $serial,
                    ];
                }
            }
        }

        // Fallback Hublot: linee "Numero di ser(e/ie)" o varianti OCR
        $lines = preg_split('/\r?\n/', $text);
        $lineCount = count($lines);
        $stopTokens = ['CF', 'GF', 'CE', 'OROLOGIO', 'SPIRIT', 'AIMM'];
        for ($idx = 0; $idx < $lineCount; $idx++) {
            $line = trim($lines[$idx]);

            if ($line === '') {
                continue;
            }

            $matchesNumero = [];
            if (!preg_match('/(?:m|n)[a-z]{0,4}ro\s+di\s+sa?r[ea]/i', $line, $matchesNumero, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $posNumero = $matchesNumero[0][1];

            $quantity = 1;
            if (preg_match('/(\d{1,5})\s*$/', $line, $qMatch)) {
                $quantity = max(1, (int) $qMatch[1]);
            }

            if ($quantity === 1 && preg_match('/\bP[O0]\s*[1IL]\b/i', $line)) {
                $quantity = 1;
            }

            $preNumero = trim(substr($line, 0, $posNumero));

            if ($preNumero === '') {
                continue;
            }

            $preClean = trim(preg_replace('/\s+/', ' ', $preNumero));
            $tokens = preg_split('/\s+/', $preClean);

            if (empty($tokens)) {
                continue;
            }

            $codeTokens = [];
            $splitIndex = count($tokens);

            foreach ($tokens as $tokenIndex => $token) {
                $upper = strtoupper($token);
                if (in_array($upper, $stopTokens, true)) {
                    $splitIndex = $tokenIndex;
                    break;
                }

                $codeTokens[] = $token;

                if (preg_match('/\d/', $token)) {
                    $splitIndex = $tokenIndex + 1;
                    break;
                }
            }

            if (empty($codeTokens)) {
                continue;
            }

            $descTokens = array_slice($tokens, $splitIndex);
            $description = trim(implode(' ', $descTokens));

            $codeRaw = strtoupper(implode('', $codeTokens));
            $codeNormalized = preg_replace('/[^A-Z0-9\.]/', '', $codeRaw);
            $codeNormalized = rtrim($codeNormalized, '.');

            if ($codeNormalized === '' || strlen($codeNormalized) < 2) {
                continue;
            }

            if (!isset($articoli[$codeNormalized])) {
                $serial = null;
                $serialDescription = null;
                for ($forward = 1; $forward <= 3; $forward++) {
                    $nextLine = trim($lines[$idx + $forward] ?? '');
                    if ($nextLine === '') {
                        continue;
                    }

                    if (preg_match('/(\d{5,})/', $nextLine, $serialMatch)) {
                        $serial = $serialMatch[1];
                        $serialDescription = trim(Str::before($nextLine, $serialMatch[1]));
                        break;
                    }
                }

                $articoli[$codeNormalized] = [
                    'codice' => $codeNormalized,
                    'descrizione' => $description,
                    'quantita' => $quantity,
                    'numero_seriale' => $serial,
                ];

                if (($articoli[$codeNormalized]['descrizione'] ?? '') === '' && $serialDescription) {
                    $articoli[$codeNormalized]['descrizione'] = $serialDescription;
                }
            } else {
                if (empty($articoli[$codeNormalized]['numero_seriale'])) {
                    for ($forward = 1; $forward <= 3; $forward++) {
                        $nextLine = trim($lines[$idx + $forward] ?? '');
                        if ($nextLine === '') {
                            continue;
                        }

                        if (preg_match('/(\d{5,})/', $nextLine, $serialMatch)) {
                            $articoli[$codeNormalized]['numero_seriale'] = $serialMatch[1];
                            $serialDescription = trim(Str::before($nextLine, $serialMatch[1]));
                            if (($articoli[$codeNormalized]['descrizione'] ?? '') === '' && $serialDescription) {
                                $articoli[$codeNormalized]['descrizione'] = $serialDescription;
                            }
                            break;
                        }
                    }
                }

                if (empty($articoli[$codeNormalized]['descrizione']) && $description !== '') {
                    $articoli[$codeNormalized]['descrizione'] = $description;
                }
            }
        }

        for ($idx = 0; $idx < $lineCount; $idx++) {
            $line = trim($lines[$idx]);

            if ($line === '') {
                continue;
            }

            if (!preg_match('/OROLOGIO\s+(\d{5,})/i', $line, $serialMatch)) {
                continue;
            }

            $serial = $serialMatch[1];
            $serialDescription = trim(Str::before($line, $serial));

            $codeCandidate = null;
            for ($back = 1; $back <= 4; $back++) {
                $candidate = trim($lines[$idx - $back] ?? '');
                if ($candidate === '' || stripos($candidate, 'orologio') !== false) {
                    continue;
                }

                if (preg_match('/^(?:AUTOMATICO|CRONO|TITANIO|CERAMICA|CAUCCIU|ALLIGATORE|QUARZO|TOTAL|VALORE)/i', $candidate)) {
                    continue;
                }

                $codeCandidate = $candidate;
                break;
            }

            if (!$codeCandidate) {
                continue;
            }

            $candidateNorm = trim(preg_replace('/\s+/', ' ', $codeCandidate));
            $lowerCandidate = Str::lower($candidateNorm);
            $posNumeroCand = strpos($lowerCandidate, 'mero di');
            if ($posNumeroCand !== false) {
                $candidateNorm = trim(substr($candidateNorm, 0, $posNumeroCand));
            }

            $tokens = preg_split('/\s+/', $candidateNorm);
            if (empty($tokens)) {
                continue;
            }

            $codeTokens = [];
            $splitIdx = count($tokens);
            foreach ($tokens as $tokenIndex => $token) {
                $upper = strtoupper($token);
                if (in_array($upper, $stopTokens, true)) {
                    $splitIdx = $tokenIndex;
                    break;
                }

                $codeTokens[] = $token;

                if (preg_match('/\d/', $token)) {
                    $splitIdx = $tokenIndex + 1;
                    break;
                }
            }

            if (empty($codeTokens)) {
                continue;
            }

            $descTokens = array_slice($tokens, $splitIdx);
            $description = trim(implode(' ', $descTokens));
            if ($description === '' && $serialDescription !== '') {
                $description = $serialDescription;
            }

            $codeRaw = strtoupper(implode('', $codeTokens));
            $codeNormalized = preg_replace('/[^A-Z0-9\.]/', '', $codeRaw);
            $codeNormalized = rtrim($codeNormalized, '.');

            if ($codeNormalized === '' || isset($articoli[$codeNormalized])) {
                continue;
            }

            $articoli[$codeNormalized] = [
                'codice' => $codeNormalized,
                'descrizione' => $description ?: 'Articolo Hublot',
                'quantita' => 1,
                'numero_seriale' => $serial,
            ];
        }

        foreach ($articoli as &$articolo) {
            if (empty($articolo['descrizione'])) {
                $articolo['descrizione'] = 'Articolo Hublot';
            }
        }
        
        return array_values($articoli); // Re-index array
    }

    /**
     * Estrae numero seriale associato a un articolo
     */
    protected function extractSerialNumber(string $text, string $codiceArticolo): ?string
    {
        // Trova la posizione del codice articolo nel testo
        $pos = stripos($text, $codiceArticolo);
        if ($pos === false) {
            return null;
        }
        
        // Estrai le righe successive (max 500 caratteri dopo il codice)
        $contextText = substr($text, $pos, 500);
        
        // Pattern per numero seriale (vari formati)
        $serialPatterns = [
            // "N° serie: (12345678)" - SWATCH GROUP style
            '/N\s*[°º┬░]?\s*serie[:\s]+\(?\s*(\d{6,12})\s*\)?/iu',
            
            // "N° serie: 12345678" - varianti
            '/N[°º┬░]?\s*serie[:\s]+(\d{6,12})/iu',
            
            // "Serial#: 12345678" o "Serial: 12345678"
            '/Serial\s*#?[:\s]+[\(\[]?(\d{6,12})[\)\]]?/i',
            
            // "S/N: 12345678"
            '/S\/N[:\s]+[\(\[]?(\d{6,12})[\)\]]?/i',
            
            // "Serial number: 12345678"
            '/Serial\s+number[:\s]+[\(\[]?([A-Z0-9]{6,15})[\)\]]?/i',
            
            // "Seriale: 12345678"
            '/Seriale[:\s]+[\(\[]?(\d{6,12})[\)\]]?/i',
            
            // Pattern IWC/ROLEX: "Serial#: 6517629" o "Serial: 6517629."
            '/Serial[#:\s]+(\d{6,10})[\.;\s]/i',

            // Italian: "Numero di serie" con eventuale quantità PC/PZ e seriale su stessa riga o successiva
            '/Numero\s+di\s+serie[\s:]+(?:PC|PZ|PCS)?[\s\S]{0,120}?([A-Z0-9]{5,})/iu',
        ];
        
        foreach ($serialPatterns as $pattern) {
            if (preg_match($pattern, $contextText, $matches)) {
                $serial = trim($matches[1]);

                // Valida che sia un seriale ragionevole
                if (strlen($serial) >= 6 && strlen($serial) <= 15 && preg_match('/\d/', $serial)) {
                    return $serial;
                }
            }
        }

        if (stripos($contextText, 'Numero di ser') !== false) {
            if (preg_match('/Numero\s+di\s+ser(?:ie|e)[\s\S]{0,200}?(\d{5,})/i', $contextText, $matches)) {
                $serial = trim($matches[1]);

                if (strlen($serial) >= 5 && strlen($serial) <= 20) {
                    return $serial;
                }
            }
        }

        return null;
    }

    /**
     * Estrae codice EAN/Barcode associato a un articolo
     */
    protected function extractEAN(string $text, string $codiceArticolo): ?string
    {
        // Trova la posizione del codice articolo nel testo
        $pos = stripos($text, $codiceArticolo);
        if ($pos === false) {
            return null;
        }
        
        // Estrai le righe successive (max 500 caratteri dopo il codice)
        $contextText = substr($text, $pos, 500);
        
        // Pattern per EAN/Barcode (vari formati)
        $eanPatterns = [
            // "Codice EAN" seguito da numero 13 cifre (standard EAN-13)
            '/Codice\s+EAN[:\s]+(\d{13})/i',
            
            // "EAN:" seguito da numero
            '/EAN[:\s]+(\d{8,14})/i',
            
            // "Barcode:" seguito da numero
            '/Barcode[:\s]+(\d{8,14})/i',
            
            // Numero di 13 cifre isolato (probabile EAN)
            // Ma solo se preceduto da "EAN", "Barcode" o simili nelle vicinanze
            '/(?:EAN|Barcode|Codice).*?(\d{13})/is',
            
            // Pattern SWATCH GROUP: numero 13 cifre dopo il codice EAN
            // Es: "7612356203252" (riga successiva dopo "Codice EAN")
            '/(\d{13})(?:\s|$)/m',
        ];
        
        foreach ($eanPatterns as $pattern) {
            if (preg_match($pattern, $contextText, $matches)) {
                $ean = trim($matches[1]);
                
                // Valida che sia un EAN ragionevole (8, 12, 13 o 14 cifre)
                $len = strlen($ean);
                if ($len >= 8 && $len <= 14 && ctype_digit($ean)) {
                    return $ean;
                }
            }
        }
        
        return null;
    }

    /**
     * De-duplica articoli (per documenti multi-pagina o OCR duplicati)
     */
    protected function deduplicateArticoli(array $articoli): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($articoli as $articolo) {
            $codice = strtoupper(trim($articolo['codice']));
            
            // Crea chiave univoca basata su codice
            $key = $codice;
            
            // Se codice già visto, salta
            if (isset($seen[$key])) {
                continue;
            }
            
            // Controlla similarity con codici esistenti (solo OCR errors evidenti)
            $isDuplicate = false;
            foreach ($seen as $existingCode => $index) {
                // Calcola similarità tra codici
                $distance = levenshtein($codice, $existingCode);
                $similarity = similar_text($codice, $existingCode, $percent);
                
                // Considera duplicato SOLO se:
                // 1. Distanza 1 E similarità > 92% (es: O→0, I→1)
                // 2. OPPURE codici molto simili con solo caratteri confusi OCR
                $isOcrError = false;
                
                if ($distance === 1 && $percent > 92) {
                    // Verifica che sia un OCR error comune (O/0, I/1, S/5, B/8)
                    $diff = $this->findDifference($codice, $existingCode);
                    $ocrPairs = ['O0', '0O', 'I1', '1I', 'S5', '5S', 'B8', '8B', 'Z2', '2Z'];
                    
                    foreach ($ocrPairs as $pair) {
                        if ($diff === $pair) {
                            $isOcrError = true;
                            break;
                        }
                    }
                }
                
                if ($isOcrError) {
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $seen[$key] = count($unique);
                $unique[] = $articolo;
            }
        }
        
        Log::info('Deduplicazione articoli', [
            'originali' => count($articoli),
            'unici' => count($unique),
            'rimossi' => count($articoli) - count($unique)
        ]);
        
        return $unique;
    }

    /**
     * Trova la differenza tra due stringhe (per OCR error detection)
     */
    protected function findDifference(string $str1, string $str2): string
    {
        $len = min(strlen($str1), strlen($str2));
        $diff = '';
        
        for ($i = 0; $i < $len; $i++) {
            if ($str1[$i] !== $str2[$i]) {
                $diff .= $str1[$i] . $str2[$i];
            }
        }
        
        return $diff;
    }

    /**
     * Calcola confidence score globale
     * Considera TUTTI i campi richiesti, non solo quelli trovati
     */
    protected function calculateConfidence(array $structuredData): float
    {
        // Campi obbligatori con peso
        $requiredFields = [
            'numero' => 20,        // 20% - CRITICO
            'data' => 15,          // 15% - CRITICO
            'partita_iva' => 10,   // 10% - IMPORTANTE
            'importo_totale' => 10,// 10% - IMPORTANTE
            'quantita_articoli' => 10, // 10% - IMPORTANTE
            'numero_articoli' => 20,   // 20% - CRUCIALE (articoli trovati)
            // Totale: 85%
            // Resto 15% per qualità articoli (dettagli)
        ];
        
        $totalScore = 0;
        $maxScore = 0;
        
        foreach ($requiredFields as $field => $weight) {
            $maxScore += $weight;
            
            // Se il campo esiste ed è compilato
            if (isset($structuredData[$field]) && !empty($structuredData[$field])) {
                // Usa la confidence specifica se disponibile, altrimenti peso pieno
                $fieldConfidence = $structuredData[$field . '_confidence'] ?? 100;
                $totalScore += ($weight * $fieldConfidence / 100);
            }
            // Se il campo non esiste o è vuoto, contribuisce 0
        }
        
        // Bonus per articoli con dettagli completi
        if (isset($structuredData['articoli']) && is_array($structuredData['articoli'])) {
            $articoliCompleti = 0;
            foreach ($structuredData['articoli'] as $art) {
                if (!empty($art['codice']) && !empty($art['descrizione']) && isset($art['quantita'])) {
                    $articoliCompleti++;
                }
            }
            
            if ($articoliCompleti > 0) {
                // Bonus fino a 15% se tutti gli articoli hanno dati completi
                $bonusArticoli = min(15, ($articoliCompleti / count($structuredData['articoli'])) * 15);
                $totalScore += $bonusArticoli;
                $maxScore += 15;
            }
        }
        
        // Calcola percentuale finale
        $finalConfidence = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        
        return round($finalConfidence, 2);
    }

    /**
     * Cleanup immagini temporanee
     */
    protected function cleanupImages(array $imagePaths): void
    {
        foreach ($imagePaths as $imagePath) {
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
    }

    /**
     * Riprocessa un documento OCR esistente
     */
    public function reprocess(OcrDocument $document): OcrDocument
    {
        $pdfPath = $document->getPdfFullPath();
        
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        $document->update(['status' => 'processing']);

        try {
            $imagePaths = $this->convertPdfToImages($document->pdf_path);
            $rawText = $this->extractTextFromImages($imagePaths);
            $structuredData = $this->parseExtractedText($rawText, $document->tipo);
            $fornitoreId = $this->findFornitore($structuredData, $rawText);
            $confidenceScore = $this->calculateConfidence($structuredData);
            
            $document->update([
                'ocr_raw_data' => ['text' => $rawText],
                'ocr_structured_data' => $structuredData,
                'confidence_score' => $confidenceScore,
                'status' => 'completed',
                'fornitore_id' => $fornitoreId,
            ]);
            
            $this->cleanupImages($imagePaths);
            
        } catch (\Exception $e) {
            $document->update([
                'status' => 'rejected',
                'notes' => 'Errore riprocessamento: ' . $e->getMessage(),
            ]);
            
            throw $e;
        }

        return $document->fresh();
    }

    /**
     * Valida e salva correzioni utente
     */
    public function validateAndSave(OcrDocument $document, array $correctedData, int $userId): void
    {
        $document->update([
            'ocr_structured_data' => array_merge(
                $document->ocr_structured_data ?? [],
                $correctedData
            ),
            'status' => 'validated',
            'validated_by' => $userId,
            'validated_at' => now(),
        ]);

        // Salva correzioni per machine learning
        foreach ($correctedData as $campo => $valore) {
            $originalValue = $document->ocr_structured_data[$campo] ?? null;
            
            if ($originalValue !== $valore) {
                $document->corrections()->create([
                    'campo' => $campo,
                    'ocr_value' => $originalValue,
                    'corrected_value' => $valore,
                    'original_confidence' => $document->ocr_structured_data["{$campo}_confidence"] ?? 0,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    /**
     * Trova fornitore automaticamente da P.IVA o Ragione Sociale
     */
    protected function findFornitore(array $structuredData, string $rawText): ?int
    {
        // 1. Prova con P.IVA (più affidabile)
        if (!empty($structuredData['partita_iva'])) {
            $fornitore = Fornitore::where('partita_iva', $structuredData['partita_iva'])->first();
            if ($fornitore) {
                Log::info('Fornitore trovato tramite P.IVA', ['fornitore_id' => $fornitore->id, 'piva' => $structuredData['partita_iva']]);
                return $fornitore->id;
            }
        }

        // 2. Prova con Ragione Sociale estratta
        if (!empty($structuredData['ragione_sociale_estratta'])) {
            $ragioneSociale = $structuredData['ragione_sociale_estratta'];
            
            // Cerca match esatto
            $fornitore = Fornitore::where('ragione_sociale', $ragioneSociale)->first();
            if ($fornitore) {
                Log::info('Fornitore trovato tramite Ragione Sociale esatta', ['fornitore_id' => $fornitore->id]);
                return $fornitore->id;
            }
            
            // Cerca match parziale (LIKE)
            $fornitore = Fornitore::where('ragione_sociale', 'LIKE', "%{$ragioneSociale}%")->first();
            if ($fornitore) {
                Log::info('Fornitore trovato tramite Ragione Sociale parziale', ['fornitore_id' => $fornitore->id]);
                return $fornitore->id;
            }
        }

        // 3. Cerca pattern comuni nel testo grezzo (fallback)
        $commonSuppliers = [
            'SWATCH GROUP' => ['SWATCH GROUP', 'THE SWATCH GROUP'],
            'ROLEX' => ['ROLEX'],
            'IWC' => ['IWC INTERNATIONAL WATCH'],
            'OMEGA' => ['OMEGA'],
            'CARTIER' => ['CARTIER'],
            'BREITLING' => ['BREITLING'],
            'TAG HEUER' => ['TAG HEUER'],
            'LONGINES' => ['LONGINES'],
            'TISSOT' => ['TISSOT'],
        ];

        foreach ($commonSuppliers as $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($rawText, $keyword) !== false) {
                    // Cerca nel database
                    $fornitore = Fornitore::where('ragione_sociale', 'LIKE', "%{$keyword}%")->first();
                    if ($fornitore) {
                        Log::info('Fornitore trovato tramite keyword nel testo', ['fornitore_id' => $fornitore->id, 'keyword' => $keyword]);
                        return $fornitore->id;
                    }
                }
            }
        }

        Log::warning('Fornitore non trovato automaticamente', ['structured_data' => $structuredData]);
        return null;
    }
}

