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
        // Linux/macOS: usa il binario nel PATH
        $output = [];
        $returnCode = 1;
        exec('command -v gs 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

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

        // Fallback: se fattura non trova numero, prova pattern DDT
        if (!isset($data['numero']) && $tipo === 'fattura' && isset($patterns['numero_ddt'])) {
            $ddtPatterns = is_array($patterns['numero_ddt']) ? $patterns['numero_ddt'] : [$patterns['numero_ddt']];
            foreach ($ddtPatterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $data['numero'] = trim($matches[1]);
                    $data['numero_confidence'] = 70;
                    break;
                }
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

        // Fallback: se fattura non trova data, prova pattern DDT specifici
        if (!isset($data['data']) && $tipo === 'fattura') {
            foreach ($dataPatterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $parsedDate = $this->parseDate($matches[1]);
                    if ($parsedDate) {
                        $data['data'] = $parsedDate;
                        $data['data_confidence'] = 70;
                        break;
                    }
                }
            }
        }

        // Partita IVA - prova pattern multipli
        $pivaPatterns = is_array($patterns['partita_iva']) ? $patterns['partita_iva'] : [$patterns['partita_iva']];
        
        foreach ($pivaPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Estrai solo i numeri (ultimi 11 caratteri se c'è prefisso paese)
                $piva = isset($matches[2]) ? $matches[2] : $matches[1];
                $piva = strtr($piva, ['I' => '1', 'O' => '0', 'l' => '1']);
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
                    $rawQta = str_replace(['.', ','], ['.', '.'], $matches[1]);
                    $data['quantita_articoli'] = (int) round((float) $rawQta);
                    $data['quantita_confidence'] = 70;
                    break;
                }
            }
        }

        // Fallback dedicato per "Totale quantità" su riga successiva
        if (!isset($data['quantita_articoli'])) {
            if (preg_match('/Totale\s+quantit.\s*[\r\n]+\s*([0-9]{1,5}[,\.]\d{2})/i', $text, $matches)) {
                $rawQta = str_replace(['.', ','], ['.', '.'], $matches[1]);
                $data['quantita_articoli'] = (int) round((float) $rawQta);
                $data['quantita_confidence'] = 65;
            }
        }

        // Fallback ultra-robusto: cerca "Totale quantit" e legge la riga successiva
        if (!isset($data['quantita_articoli'])) {
            $posTotale = stripos($text, 'Totale quantit');
            if ($posTotale !== false) {
                $snippet = substr($text, $posTotale, 200);
                $lines = preg_split('/\R/', $snippet);
                if (!empty($lines[1]) && preg_match('/([0-9]{1,5}[,\.]\d{2})/', $lines[1], $matches)) {
                    $rawQta = str_replace(['.', ','], ['.', '.'], $matches[1]);
                    $data['quantita_articoli'] = (int) round((float) $rawQta);
                    $data['quantita_confidence'] = 60;
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

        // Fallback quantità: somma delle quantità articolo se non presente
        if (!isset($data['quantita_articoli']) && !empty($data['articoli'])) {
            $data['quantita_articoli'] = array_sum(array_map(
                static fn ($articolo) => (int) ($articolo['quantita'] ?? 0),
                $data['articoli']
            ));
            $data['quantita_confidence'] = 55;
        }

        // Fallback importo totale: somma totali riga se non presente
        if (!isset($data['importo_totale']) && !empty($data['articoli'])) {
            $totale = 0.0;
            $found = false;
            foreach ($data['articoli'] as $articolo) {
                if (isset($articolo['prezzo_totale']) && is_numeric($articolo['prezzo_totale'])) {
                    $totale += (float) $articolo['prezzo_totale'];
                    $found = true;
                } elseif (isset($articolo['prezzo_unitario'], $articolo['quantita']) && is_numeric($articolo['prezzo_unitario'])) {
                    $totale += (float) $articolo['prezzo_unitario'] * (int) $articolo['quantita'];
                    $found = true;
                }
            }

            if ($found) {
                $data['importo_totale'] = $totale;
                $data['importo_confidence'] = 60;
            }
        }

        return $data;
    }

    /**
     * Parsing data in vari formati
     */
    protected function parseDate(string $dateString): ?string
    {
        $dateString = trim($dateString);

        // Gestione mesi in lettere (italiano)
        if (preg_match('/(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})/i', $dateString, $match)) {
            $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
            $monthText = strtoupper($match[2]);
            $year = $match[3];
            $monthMap = [
                'GEN' => '01', 'GENNAIO' => '01',
                'FEB' => '02', 'FEBBRAIO' => '02',
                'MAR' => '03', 'MARZO' => '03',
                'APR' => '04', 'APRILE' => '04',
                'MAG' => '05', 'MAGGIO' => '05',
                'GIU' => '06', 'GIUGNO' => '06',
                'LUG' => '07', 'LUGLIO' => '07',
                'AGO' => '08', 'AGOSTO' => '08',
                'SET' => '09', 'SETTEMBRE' => '09',
                'OTT' => '10', 'OTTOBRE' => '10',
                'NOV' => '11', 'NOVEMBRE' => '11',
                'DIC' => '12', 'DICEMBRE' => '12',
            ];

            if (isset($monthMap[$monthText])) {
                return "{$year}-{$monthMap[$monthText]}-{$day}";
            }
        }
        
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
        $lines = preg_split('/\R/', $text);
        $skipLineIndexes = [];
        
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

        // Parsing specifico ROLEX: blocchi "Referenza" con codici Mxxxx-0000
        $rolexArticoli = $this->parseRolexArticoli($text);
        if (!empty($rolexArticoli)) {
            return $rolexArticoli;
        }

        // Parsing specifico POMELLATO: riga tabellare con codice + variante + quantità + NR
        foreach ($lines as $idx => $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') {
                continue;
            }

            if (preg_match('/^\s*\d{1,3}\s+([A-Z0-9]{4,10})\s+([A-Z0-9]{3,6})\s+[A-Z0-9]{3,6}\s+\d{1,3}\s+(?:[\d.,]+\s+)?([\d.,]+)\s+(\d{1,3}[,\.]\d{2})\s+(?:NR|PZ|PC)\b/i', $lineTrim, $m)) {
                $codiceBase = strtoupper(trim($m[1]));
                $codiceVariante = strtoupper(trim($m[2]));
                $caratiRaw = $m[3] ?? '';
                $quantita = (float) str_replace(',', '.', $m[4]);
                $caratura = $caratiRaw !== '' ? str_replace(',', '.', $caratiRaw) : null;

                $prezzoUnitario = null;
                $prezzoTotale = null;
                if (preg_match('/\bEUR\s+([0-9\.\,]+)\s+([0-9\.\,]+)/i', $lineTrim, $priceMatch)) {
                    $prezzoUnitario = $this->parsePriceToFloat($priceMatch[1]);
                    $prezzoTotale = $this->parsePriceToFloat($priceMatch[2]);
                } elseif (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\s+([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\s*$/', $lineTrim, $priceMatch)) {
                    $prezzoUnitario = $this->parsePriceToFloat($priceMatch[1]);
                    $prezzoTotale = $this->parsePriceToFloat($priceMatch[2]);
                }

                $descrizione = '';
                $nextLine = isset($lines[$idx + 1]) ? trim($lines[$idx + 1]) : '';
                if ($nextLine !== '' && !preg_match('/^(MADE\s+IN|LOTTO:|CAT\.?DOG\.?:?|TOTALE\b)/i', $nextLine)) {
                    $descrizione = $nextLine;
                }

                $articoli[$codiceBase . '-' . $codiceVariante] = [
                    'codice' => $codiceBase . '-' . $codiceVariante,
                    'descrizione' => $descrizione,
                    'quantita' => max(1, (int) round($quantita)),
                    'caratura' => $caratura,
                    'prezzo_unitario' => $prezzoUnitario,
                    'prezzo_totale' => $prezzoTotale,
                ];
                $skipLineIndexes[$idx] = true;
                if ($nextLine !== '') {
                    $skipLineIndexes[$idx + 1] = true;
                }
            }
        }

        // Parsing specifico IDANDI: righe tabellari con € e codici tipici
        foreach ($lines as $idx => $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '' || (stripos($lineTrim, '€') === false && !preg_match('/\bE\s*[0-9]{1,6}[.,][0-9]{2}\b/i', $lineTrim))) {
                continue;
            }

            if (!preg_match('/(IDAN|DANDI|ANDI|ROM|SATC|SAT|SATO)/i', $lineTrim)) {
                continue;
            }

            $code = null;
            $description = null;
            $qtyRaw = null;

            // Variante: due token di codice (es: "BA IDANDICO7I-17 ...")
            if (preg_match('/^([A-Z0-9\-=\\-]{1,6})\s+([A-Z0-9\-=\\-]{6,25})\s+(.+?)\s+(?:PZ|PC|Pcs?|PE|P£|PÉ)?\s*([0-9I]{1,5})\s+€?/i', $lineTrim, $m)) {
                $code = $m[1] . '-' . $m[2];
                $description = $m[3];
                $qtyRaw = $m[4];
            } elseif (preg_match('/^([A-Z0-9\-=\\-]{6,25})\s+(.+?)\s+(?:PZ|PC|Pcs?|PE|P£|PÉ)?\s*([0-9I]{1,5})\s+€?/i', $lineTrim, $m)) {
                $code = $m[1];
                $description = $m[2];
                $qtyRaw = $m[3];
            } elseif (preg_match('/^([A-Z0-9\-=\\-]{6,25})\s+(.+?)\s+([0-9I]{1,5})\s+€?/i', $lineTrim, $m)) {
                $code = $m[1];
                $description = $m[2];
                $qtyRaw = $m[3];
            }

            if ($code) {
                $normalizedCode = $this->normalizeIdandiCode($code);
                $qtyRaw = str_replace(['I', 'l', '|'], '1', strtoupper((string) $qtyRaw));
                $qty = (int) $qtyRaw;
                if ($qty <= 0) {
                    $qty = 1;
                }
                $prices = $this->extractEuroAmounts($lineTrim);

                $articoli[$normalizedCode] = [
                    'codice' => $normalizedCode,
                    'descrizione' => trim($description ?? ''),
                    'quantita' => $qty,
                    'prezzo_unitario' => $prices['unitario'] ?? null,
                    'prezzo_totale' => $prices['totale'] ?? null,
                ];
                $skipLineIndexes[$idx] = true;
            }
        }

        // Parsing specifico BERING: "Item no ... Delivered Ordered Remaining"
        foreach ($lines as $idx => $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') {
                continue;
            }

            if (!preg_match('/\bBERING\b/i', $lineTrim) && !preg_match('/^\d{4,6}[\-A-Z0-9]*/', $lineTrim)) {
                continue;
            }

            if (preg_match('/^([0-9]{4,6}(?:-[A-Z0-9]{2,})?)\s+(.+?)\s+(\d{1,5})\s+(\d{1,5})\s+(\d{1,5})$/i', $lineTrim, $m)) {
                $codice = strtoupper(trim($m[1]));
                $descrizione = trim($m[2]);
                $qty = (int) $m[3];

                $articoli[$codice] = [
                    'codice' => $codice,
                    'descrizione' => $descrizione,
                    'quantita' => max(1, $qty),
                ];
                $skipLineIndexes[$idx] = true;
            }
        }

        // Rimuovi righe già parse per evitare match generici (es. IVA 22)
        if (!empty($skipLineIndexes)) {
            $filteredLines = [];
            foreach ($lines as $idx => $line) {
                if (!isset($skipLineIndexes[$idx])) {
                    $filteredLines[] = $line;
                }
            }
            $text = implode("\n", $filteredLines);
        }

        // Pattern specifico Marco Bicego: riga articoli con EAN + codice + misura + collezione + quantità
        if (preg_match_all(
            '/^(\d{8,14})\s+([A-Z0-9_\/\-]{4,20})\s+(\d{1,3})\s+([A-Za-z][A-Za-z0-9\s\.\-]{2,40})\s+(\d{1,3}[.,]\d{2})\s*(?:PZ|PC|Pcs?)\s+[\d.,]{1,6}(?:\s*\r?\n\s*([^\r\n]{5,120}))?/im',
            $text,
            $mbMatches,
            PREG_SET_ORDER
        )) {
            foreach ($mbMatches as $match) {
                $codice = strtoupper(trim($match[2]));
                $quantita = (int) str_replace(',', '.', $match[5]);

                $descrizione = '';
                if (!empty($match[6])) {
                    $descrizione = trim($match[6]);
                } else {
                    $descrizione = trim($match[4] . ' ' . $match[3]);
                }

                $articoli[$codice] = [
                    'codice' => $codice,
                    'descrizione' => $descrizione,
                    'quantita' => max(1, $quantita),
                    'ean' => preg_replace('/\D/', '', $match[1]),
                ];
            }
        }
        
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
     * Parsing specifico ROLEX per documenti con blocchi "Referenza".
     */
    protected function parseRolexArticoli(string $text): array
    {
        if (!preg_match('/\bROLEX\b/i', $text) || !preg_match('/\bReferenza\b/i', $text)) {
            return [];
        }

        $tableRows = $this->parseRolexTableRows($text);
        if (!empty($tableRows)) {
            return $tableRows;
        }

        $referenzeLines = $this->extractAllSectionLines(
            $text,
            '/\bReferenza\b/i',
            ['/N\.\s*serie/i', '/Descrizione/i', '/Quantit/i']
        );

        if (empty($referenzeLines)) {
            return [];
        }

        $codici = [];
        $lineCount = count($referenzeLines);
        for ($i = 0; $i < $lineCount; $i++) {
            $line = trim($referenzeLines[$i]);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^M[0-9A-Z]{5,12}-\d{4}$/', $line)) {
                $codici[] = $line;
                continue;
            }

            if (preg_match('/^M[0-9A-Z]{5,12}-$/', $line) && isset($referenzeLines[$i + 1])) {
                $next = trim($referenzeLines[$i + 1]);
                if (preg_match('/^\d{4}$/', $next)) {
                    $codici[] = $line . $next;
                    $i++;
                    continue;
                }
            }

            if (preg_match('/^(M[0-9A-Z]{5,12})\s*-\s*(\d{4})$/', $line, $m)) {
                $codici[] = $m[1] . '-' . $m[2];
            }
        }

        if (empty($codici)) {
            $codici = $this->extractRolexCodesFromText($text);
        } else {
            $codiciFromText = $this->extractRolexCodesFromText($text);
            foreach ($codiciFromText as $code) {
                if (!in_array($code, $codici, true)) {
                    $codici[] = $code;
                }
            }
        }

        if (empty($codici)) {
            return [];
        }

        $serialLines = $this->extractAllSectionLines(
            $text,
            '/N\.\s*serie/i',
            ['/Descrizione/i', '/Quantit/i']
        );
        $serials = [];
        foreach ($serialLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^[A-Z0-9]{6,12}$/', $line)) {
                $serials[] = $line;
            }
        }

        $descrizioneLines = $this->extractAllSectionLines(
            $text,
            '/Descrizione/i',
            ['/Quantit/i', '/Prezzo/i', '/%/i', '/Importo/i']
        );
        $descrizioni = $this->buildRolexDescriptions($descrizioneLines);

        $quantitaLines = $this->extractAllSectionLines(
            $text,
            '/Quantit/i',
            ['/Prezzo/i', '/%/i', '/Importo/i']
        );
        $quantitaList = [];
        foreach ($quantitaLines as $line) {
            if (preg_match('/^\s*(\d{1,3})\b/', $line, $m)) {
                $quantitaList[] = (int) $m[1];
            }
        }

        $prezzoLines = $this->extractAllSectionLines(
            $text,
            '/Prezzo/i',
            ['/Sconto/i', '/%/i', '/Importo/i']
        );
        $prezzi = $this->buildRolexAmounts($prezzoLines);

        $importoLines = $this->extractAllSectionLines(
            $text,
            '/Importo/i',
            ['/Totale/i', '/IVA/i']
        );
        $importi = $this->buildRolexAmounts($importoLines);

        $articoli = [];
        foreach ($codici as $idx => $codice) {
            $qty = $quantitaList[$idx] ?? 1;
            $articolo = [
                'codice' => $codice,
                'descrizione' => $descrizioni[$idx] ?? ('ROLEX ' . $codice),
                'quantita' => max(1, $qty),
            ];

            if (isset($serials[$idx])) {
                $articolo['numero_seriale'] = $serials[$idx];
            }

            if (isset($prezzi[$idx])) {
                $articolo['prezzo_unitario'] = $prezzi[$idx];
            }

            if (isset($importi[$idx])) {
                $articolo['prezzo_totale'] = $importi[$idx];
            }

            $articoli[$codice] = $articolo;
        }

        return $articoli;
    }

    /**
     * Parsing tabellare Rolex con colonne Referenza | N. serie | Descrizione | Quantità | Prezzo | % Sconto.
     */
    protected function parseRolexTableRows(string $text): array
    {
        $rows = [];
        $pattern = '/\|\s*(M[0-9A-Z]{5,12})\s*-\s*([0-9]{4})\s*\|\s*([A-Z0-9]{6,12})\s*\|\s*([\s\S]*?)\s*\|\s*(\d{1,3})\s*\|\s*([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\s*\|\s*[\d,\.%]+\s*\|/i';

        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $codice = strtoupper(trim($match[1] . '-' . $match[2]));
            $seriale = strtoupper(trim($match[3]));
            $descrizioneRaw = trim($match[4]);
            $descrizione = preg_replace('/\s+/', ' ', $descrizioneRaw);
            $quantita = (int) $match[5];
            $prezzoUnitario = $this->parsePriceToFloat($match[6]);

            $rows[$codice] = [
                'codice' => $codice,
                'descrizione' => $descrizione,
                'quantita' => max(1, $quantita),
                'numero_seriale' => $seriale,
                'prezzo_unitario' => $prezzoUnitario,
                'prezzo_totale' => $prezzoUnitario !== null ? $prezzoUnitario * max(1, $quantita) : null,
            ];
        }

        return $rows;
    }

    /**
     * Costruisce descrizioni Rolex da righe di sezione.
     */
    protected function buildRolexDescriptions(array $lines): array
    {
        $text = implode("\n", $lines);
        $text = preg_replace('/^\s*Descrizione\s*$/im', '', $text);
        $text = preg_replace('/^\s*Rolex Italia S\.p\.A\..*$/im', '', $text);
        $text = preg_replace('/^\s*REA:.*$/im', '', $text);
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        // In molti PDF Rolex la descrizione di un singolo articolo ha una riga vuota interna.
        // Usiamo 2+ righe vuote come separatore tra articoli.
        $chunks = preg_split('/\R{2,}/', $text);
        $descrizioni = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $normalized = preg_replace('/\s+/', ' ', $chunk);
            $descrizioni[] = $normalized;
        }

        return $descrizioni;
    }

    /**
     * Estrae importi numerici da una colonna Rolex.
     */
    protected function buildRolexAmounts(array $lines): array
    {
        $values = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/\b(TOTALE|IVA|IMPOSTA)\b/i', $line)) {
                continue;
            }

            if (preg_match('/(\d{1,3}(?:\.\d{3})*,\d{2})/', $line, $m)) {
                $values[] = $this->parsePriceToFloat($m[1]);
            }
        }

        return $values;
    }

    /**
     * Estrae codici Rolex dal testo completo (fallback multi-pagina).
     */
    protected function extractRolexCodesFromText(string $text): array
    {
        $lines = preg_split('/\R/', $text);
        $codes = [];
        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            if (preg_match('/\b(M[0-9A-Z]{5,12}-\d{4})\b/', $line, $m)) {
                $codes[] = $m[1];
                continue;
            }

            if (preg_match('/\b(M[0-9A-Z]{5,12})-\b/', $line, $m) && isset($lines[$i + 1])) {
                $next = trim($lines[$i + 1]);
                if (preg_match('/^\d{4}$/', $next)) {
                    $codes[] = $m[1] . '-' . $next;
                    $i++;
                }
            }
        }

        $unique = [];
        foreach ($codes as $code) {
            if (!in_array($code, $unique, true)) {
                $unique[] = $code;
            }
        }

        return $unique;
    }

    /**
     * Estrae le righe comprese tra un header e il successivo.
     */
    protected function extractSectionLines(string $text, string $startPattern, array $endPatterns): array
    {
        $lines = preg_split('/\R/', $text);
        $collect = false;
        $section = [];

        foreach ($lines as $line) {
            if (!$collect && preg_match($startPattern, $line)) {
                $collect = true;
                continue;
            }

            if ($collect) {
                foreach ($endPatterns as $endPattern) {
                    if (preg_match($endPattern, $line)) {
                        return $section;
                    }
                }
                $section[] = $line;
            }
        }

        return $section;
    }

    /**
     * Estrae tutte le righe di sezione ripetute nel documento.
     */
    protected function extractAllSectionLines(string $text, string $startPattern, array $endPatterns): array
    {
        $lines = preg_split('/\R/', $text);
        $collect = false;
        $section = [];

        foreach ($lines as $line) {
            if (preg_match($startPattern, $line)) {
                $collect = true;
                continue;
            }

            if ($collect) {
                $isEnd = false;
                foreach ($endPatterns as $endPattern) {
                    if (preg_match($endPattern, $line)) {
                        $isEnd = true;
                        break;
                    }
                }

                if ($isEnd) {
                    $collect = false;
                    continue;
                }

                $section[] = $line;
            }
        }

        return $section;
    }

    /**
     * Normalizza codici IDANDI (correzioni OCR comuni)
     */
    protected function normalizeIdandiCode(string $code): string
    {
        $normalized = strtoupper($code);
        $normalized = str_replace([' ', '_', '|'], '', $normalized);
        $normalized = str_replace(['=', '—', '–'], '-', $normalized);
        $normalized = preg_replace('/[^A-Z0-9\-]/', '', $normalized);

        $chars = str_split($normalized);
        $len = count($chars);
        for ($i = 0; $i < $len; $i++) {
            $prevIsDigit = $i > 0 && ctype_digit($chars[$i - 1]);
            $nextIsDigit = $i + 1 < $len && ctype_digit($chars[$i + 1]);

            if (($prevIsDigit || $nextIsDigit) && $chars[$i] === 'O') {
                $chars[$i] = '0';
            }
            if (($prevIsDigit || $nextIsDigit) && $chars[$i] === 'I') {
                $chars[$i] = '1';
            }
            if (($prevIsDigit || $nextIsDigit) && $chars[$i] === 'S') {
                $chars[$i] = '5';
            }
        }

        return implode('', $chars);
    }

    /**
     * Estrae importi € dalla riga (unitario e totale)
     */
    protected function extractEuroAmounts(string $line): array
    {
        $amounts = [];
        if (preg_match_all('/€\s*([0-9]{1,6}[.,][0-9]{2})/u', $line, $matches)) {
            foreach ($matches[1] as $value) {
                $amounts[] = $this->parsePriceToFloat($value);
            }
        }

        if (empty($amounts) && preg_match_all('/\bE\s*([0-9]{1,6}[.,][0-9]{2})\b/i', $line, $matches)) {
            foreach ($matches[1] as $value) {
                $amounts[] = $this->parsePriceToFloat($value);
            }
        }

        $amounts = array_values(array_filter($amounts, static fn ($val) => $val !== null));

        if (count($amounts) >= 2) {
            return ['unitario' => $amounts[0], 'totale' => $amounts[1]];
        }
        if (count($amounts) === 1) {
            return ['unitario' => $amounts[0]];
        }

        return [];
    }

    /**
     * Converte importo con virgola/punto in float
     */
    protected function parsePriceToFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(['.', ','], ['', '.'], $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
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
            $piva = preg_replace('/\D/', '', (string) $structuredData['partita_iva']);
            $fornitore = Fornitore::where('partita_iva', $structuredData['partita_iva'])
                ->orWhere('partita_iva', 'LIKE', "%{$piva}%")
                ->first();
            if ($fornitore) {
                Log::info('Fornitore trovato tramite P.IVA', ['fornitore_id' => $fornitore->id, 'piva' => $structuredData['partita_iva']]);
                return $fornitore->id;
            }
        }

        // 2. Prova con Ragione Sociale estratta
        if (!empty($structuredData['ragione_sociale_estratta'])) {
            $ragioneSociale = $structuredData['ragione_sociale_estratta'];
            $ragioneSocialeNormalized = $this->normalizeSupplierName($ragioneSociale);
            
            // Cerca match esatto
            $fornitore = Fornitore::where('ragione_sociale', $ragioneSociale)->first();
            if ($fornitore) {
                $this->maybeBackfillFornitorePiva($fornitore, $structuredData['partita_iva'] ?? null);
                Log::info('Fornitore trovato tramite Ragione Sociale esatta', ['fornitore_id' => $fornitore->id]);
                return $fornitore->id;
            }
            
            // Cerca match parziale (LIKE)
            $fornitore = Fornitore::where('ragione_sociale', 'LIKE', "%{$ragioneSociale}%")->first();
            if ($fornitore) {
                $this->maybeBackfillFornitorePiva($fornitore, $structuredData['partita_iva'] ?? null);
                Log::info('Fornitore trovato tramite Ragione Sociale parziale', ['fornitore_id' => $fornitore->id]);
                return $fornitore->id;
            }

            // Match normalizzato (rimuove punteggiatura e OCR noise)
            $fornitori = Fornitore::select(['id', 'ragione_sociale'])->get();
            foreach ($fornitori as $candidate) {
                $candidateNormalized = $this->normalizeSupplierName($candidate->ragione_sociale ?? '');
                if ($candidateNormalized === '' || $ragioneSocialeNormalized === '') {
                    continue;
                }
                if ($candidateNormalized === $ragioneSocialeNormalized ||
                    str_contains($candidateNormalized, $ragioneSocialeNormalized) ||
                    str_contains($ragioneSocialeNormalized, $candidateNormalized)
                ) {
                    $this->maybeBackfillFornitorePiva($candidate, $structuredData['partita_iva'] ?? null);
                    Log::info('Fornitore trovato tramite Ragione Sociale normalizzata', [
                        'fornitore_id' => $candidate->id,
                        'ragione_sociale' => $candidate->ragione_sociale,
                        'estratta' => $ragioneSociale,
                    ]);
                    return $candidate->id;
                }
            }
        }

        // 3. Cerca ragione sociale nel testo grezzo per riga (esclude "Spett." / destinatario)
        $fornitoreId = $this->findFornitoreFromRawText($rawText, $structuredData['partita_iva'] ?? null);
        if ($fornitoreId) {
            return $fornitoreId;
        }

        // 4. Cerca pattern comuni nel testo grezzo (fallback)
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
            'BERING' => ['BERING', 'BERING TIME APS'],
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

        // 5. Fallback: cerca ragione sociale nel testo grezzo (normalizzato)
        $rawNormalized = $this->normalizeSupplierName($rawText);
        if ($rawNormalized !== '') {
            $fornitori = Fornitore::select(['id', 'ragione_sociale'])->get();
            foreach ($fornitori as $candidate) {
                $candidateNormalized = $this->normalizeSupplierName($candidate->ragione_sociale ?? '');
                if ($candidateNormalized && str_contains($rawNormalized, $candidateNormalized)) {
                    $this->maybeBackfillFornitorePiva($candidate, $structuredData['partita_iva'] ?? null);
                    Log::info('Fornitore trovato tramite testo grezzo normalizzato', [
                        'fornitore_id' => $candidate->id,
                        'ragione_sociale' => $candidate->ragione_sociale,
                    ]);
                    return $candidate->id;
                }
            }
        }

        Log::warning('Fornitore non trovato automaticamente', ['structured_data' => $structuredData]);
        return null;
    }

    /**
     * Normalizza ragione sociale per matching fuzzy
     */
    protected function normalizeSupplierName(string $value): string
    {
        $ascii = Str::ascii($value);
        $ascii = strtoupper($ascii);
        $ascii = preg_replace('/[^A-Z0-9]+/', '', $ascii);
        return $ascii ?? '';
    }

    /**
     * Cerca fornitore scorrendo le righe e ignorando destinatario ("Spett.")
     */
    protected function findFornitoreFromRawText(string $rawText, ?string $partitaIva): ?int
    {
        $lines = preg_split('/\R/', $rawText);
        if (empty($lines)) {
            return null;
        }

        $fornitori = Fornitore::select(['id', 'ragione_sociale'])->get();
        if ($fornitori->isEmpty()) {
            return null;
        }

        $scores = [];
        $inRecipientBlock = false;
        $beforeDestinazione = true;

        foreach ($lines as $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') {
                if ($inRecipientBlock) {
                    $inRecipientBlock = false;
                }
                continue;
            }

            if (stripos($lineTrim, 'idandi') !== false) {
                $fornitore = Fornitore::where('ragione_sociale', 'LIKE', '%IDANDI%')->first();
                if ($fornitore) {
                    $this->maybeBackfillFornitorePiva($fornitore, $partitaIva);
                    Log::info('Fornitore trovato tramite keyword IDANDI', [
                        'fornitore_id' => $fornitore->id,
                        'ragione_sociale' => $fornitore->ragione_sociale,
                        'line' => $lineTrim,
                    ]);
                    return $fornitore->id;
                }
            }

            $lineNormalized = $this->normalizeSupplierName($lineTrim);
            if ($lineNormalized === '') {
                continue;
            }

            if (str_contains($lineNormalized, 'SPETT')) {
                $inRecipientBlock = true;
                continue;
            }

            if (str_contains($lineNormalized, 'DESTINAZIONE')) {
                $inRecipientBlock = false;
                $beforeDestinazione = false;
                continue;
            }

            if ($inRecipientBlock && (str_contains($lineNormalized, 'IDANDI') || str_contains($lineNormalized, 'SASDI'))) {
                $inRecipientBlock = false;
            }

            if ($inRecipientBlock) {
                continue;
            }

            foreach ($fornitori as $candidate) {
                $candidateNormalized = $this->normalizeSupplierName($candidate->ragione_sociale ?? '');
                if ($candidateNormalized === '') {
                    continue;
                }

                if (str_contains($lineNormalized, $candidateNormalized)) {
                    $score = $beforeDestinazione ? 3 : 1;
                    $scores[$candidate->id] = max($scores[$candidate->id] ?? 0, $score);
                    Log::info('Fornitore candidato da riga testo grezzo', [
                        'fornitore_id' => $candidate->id,
                        'ragione_sociale' => $candidate->ragione_sociale,
                        'line' => $lineTrim,
                        'score' => $score,
                    ]);
                }
            }
        }

        if (!empty($scores)) {
            arsort($scores);
            $bestId = array_key_first($scores);
            if ($bestId && !empty($structuredData['partita_iva'])) {
                $best = Fornitore::find($bestId);
                if ($best && empty($best->partita_iva)) {
                    $best->update(['partita_iva' => $structuredData['partita_iva']]);
                }
            }
            return $bestId ?: null;
        }

        return null;
    }

    /**
     * Aggiorna P.IVA del fornitore se mancante
     */
    protected function maybeBackfillFornitorePiva(Fornitore $fornitore, ?string $partitaIva): void
    {
        if (!$partitaIva || !empty($fornitore->partita_iva)) {
            return;
        }

        $normalized = preg_replace('/\D/', '', $partitaIva);
        if (strlen($normalized) !== 11) {
            return;
        }

        $fornitore->update(['partita_iva' => $normalized]);
    }
}

