<?php

namespace App\Http\Controllers;

use App\Models\OcrDocument;
use App\Services\OcrService;
use App\Models\Fornitore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OcrController extends Controller
{
    protected $ocrService;

    public function __construct(OcrService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    public function index()
    {
        $documents = OcrDocument::with(['fornitore', 'validator'])
            ->latest()
            ->paginate(20);

        return view('ocr.index', compact('documents'));
    }

    public function dashboard()
    {
        $pending = OcrDocument::pending()->latest()->take(10)->get();
        $recentValidated = OcrDocument::validated()->latest()->take(10)->get();

        return view('ocr.dashboard', compact('pending', 'recentValidated'));
    }

    public function create()
    {
        return view('ocr.upload');
    }

    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240',
            'tipo' => 'required|in:ddt,fattura',
        ]);

        try {
            $document = $this->ocrService->processPdf(
                $request->file('pdf'),
                $request->tipo
            );

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

            return redirect()
                ->route('ocr.validate', $document)
                ->with('success', 'PDF caricato e processato con successo!');

        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', 'Errore durante il processing: ' . $e->getMessage());
        }
    }

    public function showValidation(OcrDocument $document)
    {
        $document->load(['fornitore', 'corrections']);
        $fornitori = Fornitore::orderBy('ragione_sociale')->get();

        return view('ocr.validate-livewire', compact('document', 'fornitori'));
    }

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
            'articoli.*.prezzo_unitario' => 'nullable|numeric|min:0',
            'articoli.*.prezzo_totale' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->ocrService->validateAndSave(
                $document,
                $validatedData,
                Auth::id()
            );

            $structuredData = $document->ocr_structured_data ?? [];
            $structuredData['numero'] = $validatedData['numero'];
            $structuredData['data'] = $validatedData['data'];
            $structuredData['partita_iva'] = $validatedData['partita_iva'] ?? null;
            $structuredData['importo_totale'] = $validatedData['importo_totale'] ?? null;
            $structuredData['quantita_articoli'] = $validatedData['quantita_articoli'] ?? null;

            if (!empty($validatedData['articoli'])) {
                $structuredData['articoli'] = array_values($validatedData['articoli']);
                $structuredData['numero_articoli'] = count($validatedData['articoli']);
            }

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

    public function showPdf(OcrDocument $document)
    {
        $pdfPath = $document->getPdfFullPath();

        if (!file_exists($pdfPath)) {
            abort(404, 'PDF not found');
        }

        return response()->file($pdfPath);
    }

    public function downloadPdf(OcrDocument $document)
    {
        $pdfPath = $document->getPdfFullPath();

        if (!file_exists($pdfPath)) {
            abort(404, 'PDF not found');
        }

        return response()->download($pdfPath, $document->pdf_original_name);
    }

    public function destroy(OcrDocument $document)
    {
        $document->delete();

        return redirect()
            ->route('ocr.index')
            ->with('success', 'Documento eliminato con successo.');
    }
}
