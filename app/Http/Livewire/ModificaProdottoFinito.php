<?php

namespace App\Http\Livewire;

use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
use App\Models\ProdottoFinito;
use App\Models\Sede;
use App\Services\ProdottoFinitoService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.vertical')]
class ModificaProdottoFinito extends Component
{
    // Dati prodotto finito
    public $prodottoId;
    public $descrizione = '';
    public $tipologia = 'prodotto_finito';
    public $categoriaId = 9; // Default: Gioielleria
    public $sedeId = '';
    public $costoLavorazione = 0;
    public $note = '';
    public $forceEdit = false;
    
    // Componenti
    public $componenti = [];
    public $searchArticoli = '';
    public $categoriaComponentiFilter = '';
    public $soloDisponibili = true;
    
    // Dati calcolati
    public $oroTotale = '';
    public $brillantiTotali = '';
    public $pietreTotali = '';
    public $costoMaterialiTotale = 0;
    public $costoTotale = 0;
    
    // Dati per dropdown
    public $categorie;
    public $sedi;
    public $articoliDisponibili;
    
    protected $rules = [
        'descrizione' => 'required|string|max:255',
        'tipologia' => 'required|string',
        'categoriaId' => 'required|exists:categorie_merceologiche,id',
        'sedeId' => 'required|exists:sedi,id',
        'costoLavorazione' => 'nullable|numeric|min:0',
    ];

    public function mount($id)
    {
        $this->prodottoId = $id;
        $this->forceEdit = (bool) request()->boolean('force');
        $this->caricaProdottoPerModifica($id);
        $this->caricaDatiDropdown();
    }

    private function caricaProdottoPerModifica($id)
    {
        $prodotto = ProdottoFinito::with(['componentiArticoli.articolo'])->findOrFail($id);
        
        // Carica dati base
        $this->descrizione = $prodotto->descrizione;
        $this->tipologia = $prodotto->tipologia;
        $this->categoriaId = $prodotto->magazzino_id;
        $this->costoLavorazione = $prodotto->costo_lavorazione ?? 0;
        $this->note = $prodotto->note ?? '';
        
        // Carica sede - prova a recuperarla dai componenti o usa la sede di default
        if (!empty($prodotto->componentiArticoli)) {
            $primoComponente = $prodotto->componentiArticoli->first();
            if ($primoComponente && $primoComponente->articolo) {
                $this->sedeId = $primoComponente->articolo->sede_id;
            }
        }
        
        // Se non trovata, usa sede di default
        if (!$this->sedeId) {
            $sedeDefault = Sede::where('attivo', true)->first();
            $this->sedeId = $sedeDefault->id ?? '';
        }
        
        // Carica componenti
        $this->componenti = [];
        foreach ($prodotto->componentiArticoli as $componente) {
            $this->componenti[$componente->articolo_id] = [
                'articolo_id' => $componente->articolo_id,
                'quantita' => $componente->quantita,
                'articolo' => $componente->articolo,
            ];
        }
        
        // Ricalcola dati
        $this->ricalcolaDati();
    }

    private function caricaDatiDropdown()
    {
        $this->categorie = CategoriaMerceologica::where('attivo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codice']);
            
        $this->sedi = Sede::where('attivo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    public function updatedSedeId()
    {
        // Quando cambia la sede, resetta la ricerca
        $this->searchArticoli = '';
        $this->categoriaComponentiFilter = '';
    }
    
    public function aggiungiComponente($articoloId)
    {
        // Verifica se già aggiunto
        if (isset($this->componenti[$articoloId])) {
            $this->componenti[$articoloId]['quantita']++;
        } else {
            $articolo = Articolo::with('categoria', 'giacenza')->find($articoloId);
            if ($articolo) {
                $this->componenti[$articoloId] = [
                    'articolo_id' => $articoloId,
                    'quantita' => 1,
                    'articolo' => $articolo,
                ];
            }
        }
        
        $this->ricalcolaDati();
    }

    public function rimuoviComponente($articoloId)
    {
        unset($this->componenti[$articoloId]);
        $this->ricalcolaDati();
    }

    public function aggiornaQuantita($articoloId, $quantita)
    {
        if (isset($this->componenti[$articoloId])) {
            $this->componenti[$articoloId]['quantita'] = max(1, (int)$quantita);
            $this->ricalcolaDati();
        }
    }

    private function ricalcolaDati()
    {
        $oro = [];
        $brillanti = [];
        $pietre = [];
        $costoMateriali = 0;
        
        foreach ($this->componenti as $comp) {
            $articolo = $comp['articolo'];
            $quantita = $comp['quantita'];
            
            // Calcola costo
            $costoUnitario = $articolo->prezzo_acquisto ?? 0;
            $costoMateriali += $costoUnitario * $quantita;
            
            // Estrai caratteristiche gioielleria
            $caratteristiche = is_string($articolo->caratteristiche)
                ? json_decode($articolo->caratteristiche, true)
                : $articolo->caratteristiche;
            
            if (!empty($caratteristiche['oro'])) {
                $oro[] = $caratteristiche['oro'];
            }
            if (!empty($caratteristiche['brill'])) {
                $brillanti[] = $caratteristiche['brill'];
            }
            if (!empty($caratteristiche['pietre'])) {
                $pietre[] = $caratteristiche['pietre'];
            }
        }
        
        $this->oroTotale = !empty($oro) ? implode(' + ', array_unique($oro)) : '';
        $this->brillantiTotali = !empty($brillanti) ? implode(' + ', array_unique($brillanti)) : '';
        $this->pietreTotali = !empty($pietre) ? implode(' + ', array_unique($pietre)) : '';
        $this->costoMaterialiTotale = $costoMateriali;
        $this->costoTotale = $costoMateriali + $this->costoLavorazione;
    }

    public function salva()
    {
        try {
            Log::info('🚀 Inizio modifica prodotto finito', [
                'prodotto_id' => $this->prodottoId,
                'descrizione' => $this->descrizione,
                'componenti_count' => count($this->componenti),
                'sedeId' => $this->sedeId,
                'categoriaId' => $this->categoriaId,
            ]);
            
            $this->validate();
            
            if (empty($this->componenti)) {
                Log::warning('❌ Nessun componente selezionato');
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Aggiungi almeno un componente'
                ]);
                return;
            }
            
            $service = app(ProdottoFinitoService::class);
            
            // Prepara dati
            $dati = [
                'descrizione' => $this->descrizione,
                'tipologia' => $this->tipologia,
                'costo_lavorazione' => $this->costoLavorazione ?? 0,
                'note' => $this->note,
            ];
            
            $componentiData = array_map(fn($c) => [
                'articolo_id' => $c['articolo_id'],
                'quantita' => $c['quantita'],
            ], $this->componenti);
            
            Log::info('📦 Dati preparati per aggiornamento', [
                'dati' => $dati,
                'componenti' => $componentiData,
            ]);
            
            // Aggiorna prodotto
            $prodottoFinito = $service->aggiornaProdotto(
                $this->prodottoId,
                $dati,
                $componentiData,
                $this->sedeId,
                $this->categoriaId,
                $this->forceEdit
            );
            
            Log::info('✅ Prodotto finito aggiornato con successo', [
                'id' => $prodottoFinito->id,
                'codice' => $prodottoFinito->codice,
            ]);
            
            // Mostra messaggio di successo e redirect
            session()->flash('success', 'Prodotto finito aggiornato con successo! Codice: ' . $prodottoFinito->codice);
            
            // Redirect all'elenco
            return redirect()->route('prodotti-finiti.index');
            
        } catch (\Exception $e) {
            Log::error('❌ Errore durante aggiornamento prodotto finito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Errore: ' . $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        // Articoli disponibili per componenti
        if ($this->sedeId || $this->forceEdit) {
            $query = Articolo::with(['categoria', 'giacenza']);
            if ($this->forceEdit) {
                $query->withoutGlobalScopes();
            } else {
                $query->whereHas('giacenza', function($q) {
                    $q->where('sede_id', $this->sedeId);
                });
            }
            
            Log::info('🔍 Ricerca articoli per modifica', [
                'sede_id' => $this->sedeId,
                'search' => $this->searchArticoli,
                'categoria_filter' => $this->categoriaComponentiFilter,
            ]);
            
            if ($this->searchArticoli) {
                $query->where(function($q) {
                    $q->where('codice', 'like', '%' . $this->searchArticoli . '%')
                      ->orWhere('descrizione', 'like', '%' . $this->searchArticoli . '%');
                });
            }
            
            if ($this->categoriaComponentiFilter) {
                $query->where('categoria_merceologica_id', $this->categoriaComponentiFilter);
            }
            
            // Filtra solo disponibili se checkbox attivo
            if ($this->soloDisponibili) {
                $query->whereHas('giacenza', function($q) {
                    if (!$this->forceEdit) {
                        $q->where('sede_id', $this->sedeId);
                    }
                    $q->where('quantita_residua', '>', 0);
                });
            }
            
            $articoli = $query->orderBy('codice')->limit(50)->get();
            
            Log::info('📦 Articoli trovati', [
                'count' => $articoli->count(),
            ]);
            
            $this->articoliDisponibili = $articoli;
        } else {
            Log::warning('⚠️ Nessuna sede selezionata per ricerca articoli');
            $this->articoliDisponibili = collect();
        }
        
        return view('livewire.modifica-prodotto-finito');
    }
}
