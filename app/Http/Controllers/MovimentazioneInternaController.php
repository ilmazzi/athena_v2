<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Models\Articolo;
use App\Models\ProdottoFinito;
use App\Models\Movimentazione;
use Illuminate\Http\Request;

/**
 * Controller per gestione movimentazioni interne tra sedi
 */
class MovimentazioneInternaController extends Controller
{
    /**
     * Mostra pagina creazione movimentazione
     */
    public function index()
    {
        $sedi = Sede::attive()->get();
        
        return view('movimentazioni-interne.index', compact('sedi'));
    }

    /**
     * Elenco movimentazioni interne
     */
    public function elenco(Request $request)
    {
        $sedi = Sede::attive()->orderBy('nome')->get();
        $stati = ['bozza', 'confermata', 'completata', 'annullata'];

        $query = Movimentazione::query()
            ->with([
                'magazzinoPartenza' => fn($q) => $q->withoutGlobalScope('user_sede')->with('sede'),
                'magazzinoDestinazione' => fn($q) => $q->withoutGlobalScope('user_sede')->with('sede'),
                'creataDa',
            ])
            ->withCount('dettagli')
            ->orderBy('data_movimentazione', 'desc');

        if ($request->filled('stato')) {
            $query->where('stato', $request->stato);
        }

        if ($request->filled('da')) {
            $query->whereDate('data_movimentazione', '>=', $request->da);
        }

        if ($request->filled('a')) {
            $query->whereDate('data_movimentazione', '<=', $request->a);
        }

        if ($request->filled('sede_id')) {
            $sedeId = (int) $request->sede_id;
            $query->where(function ($q) use ($sedeId) {
                $q->whereHas('magazzinoPartenza', function ($q) use ($sedeId) {
                    $q->withoutGlobalScope('user_sede')
                        ->where('sede_id', $sedeId);
                })->orWhereHas('magazzinoDestinazione', function ($q) use ($sedeId) {
                    $q->withoutGlobalScope('user_sede')
                        ->where('sede_id', $sedeId);
                });
            });
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('numero_documento', 'like', "%{$term}%")
                    ->orWhere('note', 'like', "%{$term}%")
                    ->orWhere('causale', 'like', "%{$term}%")
                    ->orWhereHas('dettagli.articolo', function ($q) use ($term) {
                        $q->withoutGlobalScope('user_sede')
                            ->where('codice', 'like', "%{$term}%")
                            ->orWhere('descrizione', 'like', "%{$term}%");
                    })
                    ->orWhereHas('magazzinoPartenza', function ($q) use ($term) {
                        $q->withoutGlobalScope('user_sede')
                            ->where('nome', 'like', "%{$term}%");
                    })
                    ->orWhereHas('magazzinoDestinazione', function ($q) use ($term) {
                        $q->withoutGlobalScope('user_sede')
                            ->where('nome', 'like', "%{$term}%");
                    });
            });
        }

        $movimentazioni = $query->paginate(20)->withQueryString();

        return view('movimentazioni-interne.elenco', compact('movimentazioni', 'sedi', 'stati'));
    }
    
    /**
     * Mostra DDT di movimentazione per stampa
     */
    public function stampaDdt($movimentazioneId)
    {
        $movimentazione = \App\Models\Movimentazione::with([
            'dettagli.articolo',
            'magazzinoPartenza.sede',
            'magazzinoDestinazione.sede',
            'creataDa'
        ])->findOrFail($movimentazioneId);
        
        return view('movimentazioni-interne.stampa-ddt', compact('movimentazione'));
    }
    
    /**
     * Download PDF DDT movimentazione
     */
    public function downloadDdt($movimentazioneId)
    {
        $movimentazione = \App\Models\Movimentazione::with([
            'dettagli.articolo',
            'magazzinoPartenza.sede', 
            'magazzinoDestinazione.sede',
            'creataDa'
        ])->findOrFail($movimentazioneId);
        
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('movimentazioni-interne.stampa-ddt', compact('movimentazione'));
        
        $filename = "DDT-MOV-{$movimentazione->numero_ddt}-" . now()->format('Y-m-d') . ".pdf";
        
        return $pdf->download($filename);
    }
}
