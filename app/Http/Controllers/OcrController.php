<?php

namespace App\Http\Controllers;

use App\Models\OcrDocument;
use App\Services\OcrService;
use App\Models\Fornitore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OcrController extends Controller
{
    protected $ocrService;

    public function __construct(OcrService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    /**
     * Mostra pagina principale OCR
     */
    public function index()
    {
        $documents = OcrDocument::with(['fornitore', 'validator'])
            ->latest()
            ->paginate(20);

        return view('ocr.index', compact('documents'));
    }

    /**
     * Mostra form upload
     */
    public function create()
    {
        return view('ocr.upload');
    }

    /**
     * Upload e processo PDF
     */
    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240', // max 10MB
            'tipo' => 'required|in:ddt,fattura',
        ]);

        try {
            $document = $this->ocrService->processPdf(
                $request->file('pdf'),
                $request->tipo
            );

            // Se è una richiesta AJAX (batch upload), ritorna JSON
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'PDF processato con successo',
                    'document' => [
                        'id' => $document->id,
                        'tipo' => $document->tipo,
                        'filename' => $document->pdf_original_name,
                        'confidence_score' => $document->confidence_score,
                        'status' => $document->status,
                    ]
                ], 201);
            }

            // Altrimenti redirect normale (upload singolo)
            return redirect()
                ->route('ocr.validate', $document)
                ->with('success', 'PDF caricato e processato con successo!');
                
        } catch (\Exception $e) {
            // Se è AJAX, ritorna errore JSON
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            // Altrimenti redirect con errore
            return back()
                ->withInput()
                ->with('error', 'Errore durante il processing: ' . $e->getMessage());
        }
    }

    /**
     * Mostra interfaccia validazione
     */
    public function showValidation(OcrDocument $document)
    {
        $document->load(['fornitore', 'corrections']);
        
        // Fornitori per dropdown
        $fornitori = Fornitore::orderBy('ragione_sociale')->get();

        return view('ocr.validate-livewire', compact('document', 'fornitori'));
    }

    /**
     * Salva validazione utente
     */
    public function saveValidation(Request $request, OcrDocument $document)
    {
        $validatedData = $request->validate([
            'numero' => 'required|string|max:255',
            'data' => 'required|date',
            'fornitore_id' => 'required|exists:fornitori,id',
            'partita_iva' => 'nullable|string|max:50',
            'importo_totale' => 'nullable|numeric',
            'quantita_articoli' => 'nullable|integer',
            'note' => 'nullable|string',
            'articoli' => 'nullable|array',
            'articoli.*.codice' => 'required_with:articoli|string|max:100',
            'articoli.*.descrizione' => 'nullable|string|max:500',
            'articoli.*.quantita' => 'required_with:articoli|numeric|min:0',
        ]);

        try {
            // Salva le correzioni per i campi principali
            $this->ocrService->validateAndSave(
                $document,
                $validatedData,
                Auth::id()
            );

            // Aggiorna documento con dati validati
            $structuredData = $document->ocr_structured_data ?? [];
            
            // Aggiorna campi principali
            $structuredData['numero'] = $validatedData['numero'];
            $structuredData['data'] = $validatedData['data'];
            $structuredData['partita_iva'] = $validatedData['partita_iva'] ?? null;
            $structuredData['importo_totale'] = $validatedData['importo_totale'] ?? null;
            $structuredData['quantita_articoli'] = $validatedData['quantita_articoli'] ?? null;
            
            // Aggiorna articoli se presenti
            if (!empty($validatedData['articoli'])) {
                $structuredData['articoli'] = array_values($validatedData['articoli']);
                $structuredData['numero_articoli'] = count($validatedData['articoli']);
            }

            // Aggiorna documento
            $document->update([
                'fornitore_id' => $validatedData['fornitore_id'],
                'ocr_structured_data' => $structuredData,
                'validated_by' => Auth::id(),
                'validated_at' => now(),
                'status' => 'validated',
                'notes' => $validatedData['note'] ?? null,
            ]);

            return redirect()
                ->route('ocr.dashboard')
                ->with('success', 'Documento validato con successo! ' . 
                    (isset($structuredData['numero_articoli']) ? $structuredData['numero_articoli'] . ' articoli salvati.' : ''));
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Errore durante la validazione: ' . $e->getMessage());
        }
    }

    /**
     * Riprocessa documento
     */
    public function reprocess(OcrDocument $document)
    {
        try {
            $this->ocrService->reprocess($document);

            return redirect()
                ->route('ocr.validate', $document)
                ->with('success', 'Documento riprocessato con successo!');
                
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Errore durante il riprocessamento: ' . $e->getMessage());
        }
    }

    /**
     * Visualizza PDF
     */
    public function showPdf(OcrDocument $document)
    {
        $pdfPath = $document->getPdfFullPath();

        if (!file_exists($pdfPath)) {
            abort(404, 'PDF not found');
        }

        return response()->file($pdfPath);
    }

    /**
     * Download PDF
     */
    public function downloadPdf(OcrDocument $document)
    {
        $pdfPath = $document->getPdfFullPath();

        if (!file_exists($pdfPath)) {
            abort(404, 'PDF not found');
        }

        return response()->download($pdfPath, $document->pdf_original_name);
    }

    /**
     * Elimina documento
     */
    public function destroy(OcrDocument $document)
    {
        try {
            // Elimina PDF fisico
            if (Storage::exists($document->pdf_path)) {
                Storage::delete($document->pdf_path);
            }

            // Elimina record (cascade eliminerà anche corrections)
            $document->delete();

            return redirect()
                ->route('ocr.index')
                ->with('success', 'Documento eliminato con successo!');
                
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * API: Statistiche OCR
     */
    public function stats()
    {
        $stats = [
            'totali' => OcrDocument::count(),
            'pending' => OcrDocument::pending()->count(),
            'processing' => OcrDocument::processing()->count(),
            'completed' => OcrDocument::completed()->count(),
            'validated' => OcrDocument::validated()->count(),
            'rejected' => OcrDocument::where('status', 'rejected')->count(),
            'avg_confidence' => OcrDocument::completed()->avg('confidence_score'),
            'ddt' => OcrDocument::ddt()->count(),
            'fatture' => OcrDocument::fattura()->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Dashboard OCR
     */
    public function dashboard()
    {
        $pendingDocuments = OcrDocument::pending()
            ->orWhere('status', 'completed')
            ->whereNull('validated_by')
            ->latest()
            ->limit(10)
            ->get();

        $recentValidated = OcrDocument::validated()
            ->with(['validator', 'fornitore'])
            ->latest('validated_at')
            ->limit(5)
            ->get();

        $stats = [
            'totali' => OcrDocument::count(),
            'da_validare' => OcrDocument::completed()->whereNull('validated_by')->count(),
            'validati_oggi' => OcrDocument::validated()
                ->whereDate('validated_at', today())
                ->count(),
            'avg_confidence' => round(OcrDocument::completed()->avg('confidence_score'), 2),
        ];

        return view('ocr.dashboard', compact('pendingDocuments', 'recentValidated', 'stats'));
    }
}
