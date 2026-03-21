<?php

namespace App\Http\Livewire;

use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
use App\Services\MagazzinoLogicoService;
use App\Models\Sede;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.vertical', ['title' => 'Scarico Magazzino'])]
class ScaricoMagazzino extends Component
{
    use WithPagination;

    // Modalità di scarico (solo multiplo)
    public $modalitaScarico = 'multiplo';
    
    // Filtri per ricerca articoli
    public $search = '';
    public $categoriaFilter = '';
    public $sedeFilter = '';
    public $soloDisponibili = true;
    
    // Articoli selezionati per scarico multiplo
    public $articoliSelezionati = [];
    public $selezionaTutti = false;
    
    // Filtri per scarico per ubicazione/categoria
    public $ubicazioneSelezionata = '';
    public $categoriaSelezionata = '';
    
    // Modalità scarico parziale
    public $showModalScarico = false;
    public $articoloDaScaricare = null;
    public $quantitaDaScaricare = 1;
    public $giacenzaDisponibile = 0;
    
    // Modal conferma scarico multiplo
    public $showModalConferma = false;
    public $quantitaArticoli = []; // [articolo_id => quantita]
    
    // Paginazione
    public $perPage = 25;
    
    protected $queryString = [
        'search' => ['except' => ''],
        'categoriaFilter' => ['except' => ''],
        'sedeFilter' => ['except' => ''],
        'modalitaScarico' => ['except' => 'singolo'],
    ];

    public function updatedSearch()
    {
        $this->resetPage();
        // NON resettare le selezioni per mantenere la persistenza
    }

    public function updatedCategoriaFilter()
    {
        $this->resetPage();
        // NON resettare le selezioni per mantenere la persistenza
    }

    public function updatedSedeFilter()
    {
        $this->resetPage();
        // NON resettare le selezioni per mantenere la persistenza
    }

    public function toggleSelezionaTutti()
    {
        // Seleziona solo gli articoli della pagina corrente
        $articoliPaginaCorrente = $this->getArticoliQuery()->paginate($this->perPage);
        $articoliPaginaIds = $articoliPaginaCorrente->pluck('id')->toArray();
        $selezionatiInPagina = array_intersect($this->articoliSelezionati, $articoliPaginaIds);
        
        if (count($selezionatiInPagina) === count($articoliPaginaIds) && count($articoliPaginaIds) > 0) {
            // Deseleziona tutti gli articoli della pagina corrente
            $this->articoliSelezionati = array_diff($this->articoliSelezionati, $articoliPaginaIds);
            
            // Rimuovi quantità per articoli deselezionati
            foreach ($articoliPaginaIds as $articoloId) {
                unset($this->quantitaArticoli[$articoloId]);
            }
            
            $this->selezionaTutti = false;
        } else {
            // Seleziona tutti gli articoli della pagina corrente
            $this->articoliSelezionati = array_unique(array_merge($this->articoliSelezionati, $articoliPaginaIds));
            
            // Imposta quantità solo per articoli con giacenza > 1
            foreach ($articoliPaginaIds as $articoloId) {
                $articolo = Articolo::find($articoloId);
                if ($articolo && ($articolo->giacenza->quantita_residua ?? 0) > 1) {
                    $this->quantitaArticoli[$articoloId] = 1;
                }
            }
            
            $this->selezionaTutti = true;
        }
    }

    public function updatedModalitaScarico()
    {
        $this->reset(['articoliSelezionati', 'selezionaTutti', 'quantitaArticoli']);
        $this->resetPage();
    }
    
    /**
     * Gestisce il cambio di pagina
     */
    public function gotoPage($page)
    {
        $this->setPage($page);
        // NON resettare le selezioni per mantenere la persistenza
    }

    public function updatedPerPage()
    {
        $this->resetPage();
        // NON resettare le selezioni per mantenere la persistenza
    }

    /**
     * Seleziona/deseleziona singolo articolo
     */
    public function toggleArticolo($articoloId)
    {
        if (in_array($articoloId, $this->articoliSelezionati)) {
            $this->articoliSelezionati = array_diff($this->articoliSelezionati, [$articoloId]);
            // Rimuovi anche dalle quantità se esiste
            unset($this->quantitaArticoli[$articoloId]);
        } else {
            $this->articoliSelezionati[] = $articoloId;
            
            // Imposta quantità solo se giacenza > 1
            $articolo = Articolo::find($articoloId);
            if ($articolo && ($articolo->giacenza->quantita_residua ?? 0) > 1) {
                $this->quantitaArticoli[$articoloId] = 1;
            }
        }
        
        // Aggiorna stato "seleziona tutti" basandosi sulla pagina corrente
        $this->updateSelezionaTuttiState();
    }
    
    /**
     * Aggiorna stato "seleziona tutti" basandosi sulla pagina corrente
     */
    private function updateSelezionaTuttiState()
    {
        $articoliPaginaCorrente = $this->getArticoliQuery()->paginate($this->perPage);
        $articoliPaginaIds = $articoliPaginaCorrente->pluck('id')->toArray();
        $selezionatiInPagina = array_intersect($this->articoliSelezionati, $articoliPaginaIds);
        $this->selezionaTutti = count($selezionatiInPagina) === count($articoliPaginaIds) && count($articoliPaginaIds) > 0;
    }
    
    /**
     * Aggiorna quantità per un articolo
     */
    public function aggiornaQuantita($articoloId, $quantita)
    {
        if (in_array($articoloId, $this->articoliSelezionati)) {
            $this->quantitaArticoli[$articoloId] = max(1, (int)$quantita);
        }
    }
    
    /**
     * Apre modal di conferma per scarico multiplo
     */
    public function confermaScaricoMultiplo()
    {
        if (empty($this->articoliSelezionati)) {
            session()->flash('error', 'Nessun articolo selezionato');
            return;
        }
        
        // Verifica se ci sono articoli con giacenza > 1
        $articoliConGiacenzaMultipla = [];
        foreach ($this->articoliSelezionati as $articoloId) {
            $articolo = Articolo::find($articoloId);
            if ($articolo && $articolo->giacenza->quantita_residua > 1) {
                $articoliConGiacenzaMultipla[] = $articolo;
            }
        }
        
        if (!empty($articoliConGiacenzaMultipla)) {
            // Apri modal per quantità
            $this->showModalConferma = true;
        } else {
            // Scarico diretto se tutti hanno giacenza = 1
            $this->eseguiScaricoMultiplo();
        }
    }
    
    /**
     * Esegue lo scarico multiplo
     */
    public function eseguiScaricoMultiplo()
    {
        $scaricati = 0;
        $errori = 0;

        foreach ($this->articoliSelezionati as $articoloId) {
            try {
                $articolo = Articolo::findOrFail($articoloId);
                $quantita = $this->quantitaArticoli[$articoloId] ?? 1;
                
                if ($articolo->stato_articolo === 'disponibile') {
                    $giacenzaAttuale = $articolo->giacenza->quantita_residua ?? 0;
                    
                    if ($quantita > $giacenzaAttuale) {
                        $errori++;
                        continue;
                    }
                    
                    $nuovaGiacenza = $giacenzaAttuale - $quantita;
                    
                    // Aggiorna giacenza
                    $articolo->giacenza()->update([
                        'quantita_residua' => $nuovaGiacenza
                    ]);
                    
                    // Se giacenza diventa 0, marca come scaricato
                    if ($nuovaGiacenza == 0) {
                        $articolo->update([
                            'stato_articolo' => 'scaricato',
                            'scaricato_il' => now(),
                            'scaricato_da' => Auth::id()
                        ]);
                    }
                    
                    $scaricati++;
                } else {
                    $errori++;
                }
            } catch (\Exception $e) {
                $errori++;
            }
        }

        if ($scaricati > 0) {
            session()->flash('success', "Scaricati {$scaricati} articoli con successo");
        }
        
        if ($errori > 0) {
            session()->flash('error', "{$errori} articoli non potevano essere scaricati");
        }

        $this->reset(['articoliSelezionati', 'selezionaTutti', 'quantitaArticoli', 'showModalConferma']);
    }
    
    /**
     * Chiudi modal conferma
     */
    public function chiudiModalConferma()
    {
        $this->showModalConferma = false;
    }



    /**
     * Query per ottenere articoli
     */
    private function getArticoliQuery()
    {
        $query = Articolo::with(['categoria', 'sede', 'giacenza', 'categoriaMerceologica'])
            ->where('stato_articolo', 'disponibile')
            ->whereHas('giacenza', function($q) {
                $q->where('quantita_residua', '>', 0);
            });

        if ($this->search) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('codice', 'like', $searchTerm)
                  ->orWhere('descrizione', 'like', $searchTerm)
                  ->orWhere('materiale', 'like', $searchTerm);
            });
        }

        if ($this->categoriaFilter) {
            $query->where('magazzino_logico', (int) $this->categoriaFilter);
        }

        if ($this->sedeFilter) {
            $query->where('sede_id', $this->sedeFilter);
        }

        return $query;
    }

    public function render()
    {
        $articoli = $this->getArticoliQuery()
            ->orderBy('codice')
            ->paginate($this->perPage);

        // Aggiorna stato "seleziona tutti" per la pagina corrente
        $this->updateSelezionaTuttiState();

        // Opzioni per filtri
        $service = app(MagazzinoLogicoService::class);
        $categorie = CategoriaMerceologica::where('attivo', true)
            ->orderBy('nome')
            ->get()
            ->map(function ($categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
                return $magazzinoLogico ? (object) [
                    'id' => $magazzinoLogico,
                    'nome' => 'Magazzino ' . $magazzinoLogico,
                ] : null;
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();

        $sedi = Sede::orderBy('nome')->get();

        // Statistiche
        $stats = [
            'totali_disponibili' => Articolo::where('stato_articolo', 'disponibile')->count(),
            'selezionati' => count($this->articoliSelezionati),
            'in_pagina' => $articoli->count(),
        ];

        return view('livewire.scarico-magazzino', compact('articoli', 'categorie', 'sedi', 'stats'));
    }
}
