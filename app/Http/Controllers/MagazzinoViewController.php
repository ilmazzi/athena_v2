<?php

namespace App\Http\Controllers;

use App\Models\CategoriaMerceologica;
use App\Models\Articolo;
use App\Models\Fornitore;
use App\Models\ProdottoFinito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MagazzinoViewController extends Controller
{
    /**
     * Display elenco articoli magazzino
     */
    public function articoli(Request $request)
    {
        // Query Eloquent semplice per articoli con relazioni
        $articoliQuery = Articolo::with(['categoria', 'sede', 'giacenza'])
            ->select('articoli.*');
        
        // Applica filtri da request
        if ($request->has('magazzino')) {
            $articoliQuery->where('categoria_merceologica_id', $request->magazzino);
        }
        
        if ($request->has('stato')) {
            $articoliQuery->where('stato', $request->stato);
        }
        
        if ($request->has('fornitore')) {
            $fornitoreId = $request->fornitore;
            $articoliQuery->where(function ($q) use ($fornitoreId) {
                $q->whereHas('ddtDettaglio.ddt', function ($subQ) use ($fornitoreId) {
                    $subQ->where('fornitore_id', $fornitoreId);
                })->orWhereHas('fatturaDettaglio.fattura', function ($subQ) use ($fornitoreId) {
                    $subQ->where('fornitore_id', $fornitoreId);
                });
            });
        }
        
        // Ordinamento
        $articoliQuery->orderBy('created_at', 'desc');
        
        // Paginazione
        $articoli = $articoliQuery->paginate(50);
        
        // Carica categorie merceologiche e fornitori per filtri
        $magazzini = CategoriaMerceologica::where('attivo', true)->get();
        $fornitori = Fornitore::where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get();
        
        // Stats
        $stats = [
            'totali' => Articolo::count(),
            'disponibili' => Articolo::where('stato', 'disponibile')->count(),
            'esauriti' => Articolo::join('giacenze', 'articoli.id', '=', 'giacenze.articolo_id')
                ->where('giacenze.quantita', 0)
                ->count(),
            'valore_totale' => DB::table('articoli')
                ->join('giacenze', 'articoli.id', '=', 'giacenze.articolo_id')
                ->sum(DB::raw('articoli.prezzo_acquisto * giacenze.quantita')),
        ];
        
        return view('magazzino.articoli', [
            'title' => 'Elenco Articoli',
            'pageTitle' => 'Elenco Articoli Magazzino',
            'breadcrumbs' => [
                ['title' => 'Magazzino', 'url' => '#'],
                ['title' => 'Articoli', 'url' => route('articoli.index')],
            ],
            'articoli' => $articoli,
            'magazzini' => $magazzini,
            'fornitori' => $fornitori,
            'stats' => $stats,
        ]);
    }
    
    /**
     * Display dettaglio articolo
     */
    public function show($id)
    {
        $articolo = Articolo::with(['categoriaMerceologica', 'ddtDettaglio.ddt.fornitore', 'giacenza', 'movimentazioni'])
            ->findOrFail($id);
        
        return view('magazzino.articolo-detail', [
            'title' => 'Dettaglio Articolo',
            'pageTitle' => 'Dettaglio Articolo: ' . $articolo->codice,
            'breadcrumbs' => [
                ['title' => 'Magazzino', 'url' => '#'],
                ['title' => 'Articoli', 'url' => route('articoli.index')],
                ['title' => $articolo->codice, 'url' => ''],
            ],
            'articolo' => $articolo,
        ]);
    }
    
    /**
     * Display gestione categorie merceologiche
     */
    public function index()
    {
        $magazzini = CategoriaMerceologica::withCount('articoli')
            ->orderBy('id')
            ->get();
        
        return view('magazzino.index', [
            'title' => 'Categorie Merceologiche',
            'pageTitle' => 'Categorie Merceologiche',
            'breadcrumbs' => [
                ['title' => 'Magazzino', 'url' => '#'],
                ['title' => 'Categorie', 'url' => route('magazzino.index')],
            ],
            'magazzini' => $magazzini,
        ]);
    }
    
    /**
     * Display dettaglio prodotto finito
     */
    public function dettaglioProdottoFinito($id)
    {
        $prodottoFinito = ProdottoFinito::with([
            'categoria',
            'componentiArticoli.articolo',
            'articoloRisultante.giacenza',
            'creatoDa',
            'assemblatoDa'
        ])->findOrFail($id);
        
        return view('magazzino.prodotto-finito-detail', [
            'title' => 'Dettaglio Prodotto Finito',
            'pageTitle' => 'Dettaglio Prodotto Finito: ' . $prodottoFinito->codice,
            'breadcrumbs' => [
                ['title' => 'Magazzino', 'url' => '#'],
                ['title' => 'Prodotti Finiti', 'url' => route('prodotti-finiti.index')],
                ['title' => $prodottoFinito->codice, 'url' => ''],
            ],
            'prodottoFinito' => $prodottoFinito,
        ]);
    }
}

