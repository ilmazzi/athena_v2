<?php

namespace App\Http\Livewire;

use App\Models\InventarioSessione;
use App\Models\Sede;
use App\Models\CategoriaMerceologica;
use App\Services\InventarioService;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\MagazzinoLogicoService;


class SessioniInventario extends Component
{
    use WithPagination;

    public $showModal = false;
    public $showModalDettagli = false;
    public $sessioneSelezionata = null;
    public $nome = '';
    public $sedeId = '';
    public $categorieSelezionate = [];
    public $note = '';
    public $sedi = [];
    public $categorie = [];
    public $filtroSede = '';
    public $filtroStato = '';

    protected $listeners = ['sessioneCreata' => 'aggiornaLista'];

    public function mount()
    {
        $this->sedi = Sede::all();
    
        $service = app(MagazzinoLogicoService::class);
        $this->categorie = CategoriaMerceologica::query()
            ->orderBy('nome')
            ->get()
            ->map(function ($categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
    
                return $magazzinoLogico ? (object) [
                    'id' => $magazzinoLogico,
                    'nome' => $service->getLabelForCategoria($categoria),
                ] : null;
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();
    }
    

    public function apriModal()
    {
        $this->reset(['nome', 'sedeId', 'categorieSelezionate', 'note']);
        $this->showModal = true;
    }

    public function chiudiModal()
    {
        $this->showModal = false;
        $this->reset(['nome', 'sedeId', 'categorieSelezionate', 'note']);
    }

    public function creaSessione()
    {
        $this->validate([
            'nome' => 'required|string|max:255',
            'sedeId' => 'required|exists:sedi,id',
            'categorieSelezionate' => 'nullable|array',
            'note' => 'nullable|string'
        ]);

        try {
            $inventarioService = app(InventarioService::class);
            
            $sessione = $inventarioService->creaSessione(
                $this->nome,
                $this->sedeId,
                $this->categorieSelezionate,
                auth()->id()
            );

            session()->flash('success', "Sessione '{$sessione->nome}' creata con successo!");
            $this->chiudiModal();
            $this->emit('sessioneCreata');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la creazione della sessione: ' . $e->getMessage());
        }
    }

    public function selezionaSessione($sessioneId)
    {
        $this->sessioneSelezionata = InventarioSessione::with(['sede', 'utente'])->find($sessioneId);
    }

    public function visualizzaDettagli($sessioneId)
    {
        $this->sessioneSelezionata = InventarioSessione::with(['sede', 'utente'])->find($sessioneId);
        $this->showModalDettagli = true;
    }

    public function chiudiModalDettagli()
    {
        $this->showModalDettagli = false;
        $this->sessioneSelezionata = null;
    }

    public function visualizzaScansioni($sessioneId)
    {
        return redirect()->route('inventario.monitor', ['sessione' => $sessioneId]);
    }

    public function chiudiSessione($sessioneId)
    {
        try {
            $inventarioService = app(InventarioService::class);
            $sessione = $inventarioService->chiudiSessione($sessioneId);
            
            session()->flash('success', "Sessione '{$sessione->nome}' chiusa con successo!");
            $this->aggiornaLista();
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la chiusura della sessione: ' . $e->getMessage());
        }
    }

    public function annullaSessione($sessioneId)
    {
        try {
            $inventarioService = app(InventarioService::class);
            $sessione = $inventarioService->annullaSessione($sessioneId);
            
            session()->flash('success', "Sessione '{$sessione->nome}' annullata!");
            $this->aggiornaLista();
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'annullamento della sessione: ' . $e->getMessage());
        }
    }

    public function aggiornaLista()
    {
        $this->resetPage();
    }

    public function getSessioniQuery()
    {
        $query = InventarioSessione::with(['sede', 'utente']);

        if ($this->filtroSede) {
            $query->where('sede_id', $this->filtroSede);
        }

        if ($this->filtroStato) {
            $query->where('stato', $this->filtroStato);
        }

        return $query->orderBy('data_inizio', 'desc');
    }

    public function render()
    {
        $sessioni = $this->getSessioniQuery()->paginate(10);
        
        return view('livewire.sessioni-inventario', [
            'sessioni' => $sessioni
        ])->layout('layouts.vertical');
    }
}
