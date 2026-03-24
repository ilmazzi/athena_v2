<?php

namespace App\Http\Livewire;

use App\Models\Stampante;
use App\Models\Sede;
use App\Models\CategoriaMerceologica;
use App\Services\EtichettaService;
use Livewire\Component;
use Livewire\WithPagination;

class StampantiTable extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 10;
    public $sortField = 'nome';
    public $sortDirection = 'asc';
    
    // Form fields
    public $showModal = false;
    public $editingStampante = null;
    public $nome = '';
    public $ip_address = '';
    public $port = '9100';
    public $modello = 'ZT230';
    public $categorie_permesse = [];
    public $sedi_permesse = [];
    public $attiva = true;
    
    // Rimuoviamo le proprietà pubbliche - useremo computed properties

    protected $rules = [
        'nome' => 'required|string|max:255',
        'ip_address' => 'required|ip',
        'port' => 'required|integer|min:1|max:65535',
        'modello' => 'required|string|in:ZT230,ZT421,ZT420,ZT620',
        'categorie_permesse' => 'required|array|min:1',
        'sedi_permesse' => 'required|array|min:1',
        'attiva' => 'boolean'
    ];

    protected $listeners = ['refreshComponent' => '$refresh'];

    // Mount non necessario con computed properties

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
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

    public function createStampante()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editStampante($id)
    {
        $stampante = Stampante::findOrFail($id);
        
        $this->editingStampante = $stampante;
        $this->nome = $stampante->nome;
        $this->ip_address = $stampante->ip_address;
        $this->port = $stampante->port;
        $this->modello = $stampante->modello;
        $this->categorie_permesse = $stampante->categorie_permesse ?? [];
        $this->sedi_permesse = $stampante->sedi_permesse ?? [];
        $this->attiva = $stampante->attiva;
        
        $this->showModal = true;
    }

    public function saveStampante()
    {
        $this->validate();

        $data = [
            'nome' => $this->nome,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'modello' => $this->modello,
            'categorie_permesse' => $this->categorie_permesse,
            'sedi_permesse' => $this->sedi_permesse,
            'attiva' => $this->attiva,
        ];

        if ($this->editingStampante) {
            $this->editingStampante->update($data);
            session()->flash('success', 'Stampante aggiornata con successo!');
        } else {
            Stampante::create($data);
            session()->flash('success', 'Stampante creata con successo!');
        }

        $this->closeModal();
    }

    public function deleteStampante($id)
    {
        $stampante = Stampante::findOrFail($id);
        
        // Verifica se ci sono utenti che usano questa stampante
        if ($stampante->users()->count() > 0) {
            session()->flash('error', 'Impossibile eliminare: ci sono utenti che usano questa stampante');
            return;
        }

        $stampante->delete();
        session()->flash('success', 'Stampante eliminata con successo!');
    }

    public function toggleAttiva($id)
    {
        $stampante = Stampante::findOrFail($id);
        $stampante->update(['attiva' => !$stampante->attiva]);
        
        session()->flash('success', 'Stato stampante aggiornato!');
    }

    public function testConnessione($id)
    {
        $stampante = Stampante::findOrFail($id);
        
        try {
            // Test con etichetta di prova
            $testZpl = '^XA^FO50,50^A0N,30,30^FDTEST CONNECTION^FS^XZ';
            $sent = app(EtichettaService::class)->inviaAllaStampante(
                $stampante->ip_address,
                (int) $stampante->port,
                $testZpl
            );

            if ($sent !== false) {
                session()->flash('success', 'Test connessione riuscito!');
            } else {
                session()->flash('error', 'Errore durante l\'invio del test');
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore test connessione: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingStampante = null;
        $this->nome = '';
        $this->ip_address = '';
        $this->port = '9100';
        $this->modello = 'ZT230';
        $this->categorie_permesse = [];
        $this->sedi_permesse = [];
        $this->attiva = true;
    }

    // Computed Properties secondo la documentazione ufficiale Livewire
    public function getCategorieDisponibiliProperty()
    {
        return CategoriaMerceologica::select('id', 'nome')->get();
    }

    public function getSediDisponibiliProperty()
    {
        return Sede::select('id', 'nome')->get();
    }

    public function render()
    {
        $stampanti = Stampante::query()
            ->when($this->search, function ($query) {
                $query->where('nome', 'like', '%' . $this->search . '%')
                      ->orWhere('ip_address', 'like', '%' . $this->search . '%')
                      ->orWhere('modello', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.stampanti-table', [
            'stampanti' => $stampanti,
            'categorieDisponibili' => $this->categorieDisponibili,
            'sediDisponibili' => $this->sediDisponibili
        ])->layout('layouts.vertical', ['title' => 'Gestione Stampanti']);
    }
}
