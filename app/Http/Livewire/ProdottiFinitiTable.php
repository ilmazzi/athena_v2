<?php

namespace App\Http\Livewire;

use App\Models\ProdottoFinito;
use App\Models\CategoriaMerceologica;
use App\Services\ProdottoFinitoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.vertical', ['title' => 'Prodotti Finiti'])]
class ProdottiFinitiTable extends Component
{
    use WithPagination;

    // Filtri
    public $search = '';
    public $tipologiaFilter = '';
    public $statoFilter = '';
    public $categoriaFilter = '';
    public $dataFrom = '';
    public $dataTo = '';
    
    // Paginazione e ordinamento
    public $perPage = 25;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Smontaggio / disfacimento
    public $showSmontaModal = false;
    public $prodottoDaSmontare = null;
    
    protected $queryString = [
        'search' => ['except' => ''],
        'tipologiaFilter' => ['except' => ''],
        'statoFilter' => ['except' => ''],
        'categoriaFilter' => ['except' => ''],
        'dataFrom' => ['except' => ''],
        'dataTo' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTipologiaFilter()
    {
        $this->resetPage();
    }

    public function updatingStatoFilter()
    {
        $this->resetPage();
    }

    public function updatingCategoriaFilter()
    {
        $this->resetPage();
    }

    public function updatingDataFrom()
    {
        $this->resetPage();
    }

    public function updatingDataTo()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->tipologiaFilter = '';
        $this->statoFilter = '';
        $this->categoriaFilter = '';
        $this->dataFrom = '';
        $this->dataTo = '';
        $this->resetPage();
    }

    public function apriSmontaModal($prodottoId)
    {
        $prodotto = ProdottoFinito::with(['componentiArticoli.articolo', 'articoloRisultante.giacenza'])
            ->findOrFail($prodottoId);

        if (in_array($prodotto->stato, ['venduto', 'scartato', 'annullato'])) {
            session()->flash('error', 'Il prodotto finito non è smontabile nello stato attuale.');
            return;
        }

        if ($prodotto->isInContoDeposito()) {
            session()->flash('error', 'Il prodotto finito è in conto deposito e non può essere smontato.');
            return;
        }

        if ($prodotto->articoloRisultante && $prodotto->articoloRisultante->giacenza) {
            if ($prodotto->articoloRisultante->giacenza->quantita_residua <= 0) {
                session()->flash('error', 'Il prodotto finito risulta già scaricato/venduto.');
                return;
            }
        }

        $this->prodottoDaSmontare = $prodotto;
        $this->showSmontaModal = true;
    }

    public function chiudiSmontaModal()
    {
        $this->showSmontaModal = false;
        $this->prodottoDaSmontare = null;
    }

    public function confermaSmonta()
    {
        if (!$this->prodottoDaSmontare) {
            return;
        }

        try {
            $service = app(ProdottoFinitoService::class);
            $service->annullaAssemblaggio($this->prodottoDaSmontare->id);

            session()->flash('success', 'Prodotto finito smontato e componenti ripristinati.');
            $this->chiudiSmontaModal();
        } catch (\Exception $e) {
            Log::error('Errore smontaggio prodotto finito', [
                'prodotto_id' => $this->prodottoDaSmontare->id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Errore durante lo smontaggio: ' . $e->getMessage());
        }
    }

    public function render()
    {
        try {
            Log::info('📊 Rendering ProdottiFinitiTable');
            
            // Query prodotti finiti con relazioni
            $query = ProdottoFinito::with([
                'categoria',
                'componentiArticoli.articolo',
                'creatoDa',
                'assemblatoDa'
            ]);

        // Applica filtri
        if ($this->search) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('codice', 'like', $searchTerm)
                  ->orWhere('descrizione', 'like', $searchTerm);
            });
        }

        if ($this->tipologiaFilter) {
            $query->where('tipologia', $this->tipologiaFilter);
        }

        if ($this->statoFilter) {
            $query->where('stato', $this->statoFilter);
        }

        if ($this->categoriaFilter) {
            $query->where('magazzino_id', $this->categoriaFilter);
        }

        if ($this->dataFrom) {
            $query->where('data_completamento', '>=', $this->dataFrom);
        }

        if ($this->dataTo) {
            $query->where('data_completamento', '<=', $this->dataTo);
        }

        // Ordinamento
        $query->orderBy($this->sortField, $this->sortDirection);

        $prodotti = $query->paginate($this->perPage);

        // Statistiche
        $stats = [
            'totali' => ProdottoFinito::count(),
            'completati' => ProdottoFinito::where('stato', 'completato')->count(),
            'in_lavorazione' => ProdottoFinito::where('stato', 'in_lavorazione')->count(),
            'venduti' => ProdottoFinito::where('stato', 'venduto')->count(),
            'valore_totale' => ProdottoFinito::where('stato', 'completato')->sum('costo_totale'),
        ];

        // Opzioni filtri
        $categorie = CategoriaMerceologica::where('attivo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codice']);

            Log::info('✅ ProdottiFinitiTable render completato', [
                'prodotti_count' => $prodotti->total(),
            ]);
            
            return view('livewire.prodotti-finiti-table', compact('prodotti', 'stats', 'categorie'));
        } catch (\Exception $e) {
            Log::error('❌ Errore CRITICO in ProdottiFinitiTable render', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Mostra l'errore all'utente
            session()->flash('error', 'Errore nel caricamento: ' . $e->getMessage());
            
            // Fallback con dati minimi
            $prodotti = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);
            $stats = [
                'totali' => 0,
                'completati' => 0,
                'in_lavorazione' => 0,
                'venduti' => 0,
                'valore_totale' => 0,
            ];
            $categorie = collect();
            
            return view('livewire.prodotti-finiti-table', compact('prodotti', 'stats', 'categorie'));
        }
    }
}

