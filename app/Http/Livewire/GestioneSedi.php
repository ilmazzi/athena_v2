<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sede;
use App\Models\Societa;

/**
 * GestioneSedi - CRUD per sedi
 */
class GestioneSedi extends Component
{
    use WithPagination;

    // Filtri e ricerca
    public $search = '';
    public $filtroAttivo = '';
    public $filtroSocieta = '';

    // Modali
    public $showModal = false;
    public $showDeleteModal = false;
    public $modalMode = 'create';
    public $sedeSelezionataId = null;

    // Form fields
    public $codice = '';
    public $nome = '';
    public $indirizzo = '';
    public $citta = '';
    public $provincia = '';
    public $cap = '';
    public $telefono = '';
    public $email = '';
    public $tipo = 'negozio';
    public $societa_id = '';
    public $attivo = true;
    public $note = '';
    public $orari = [];
    public $sede_legale = '';
    public $sede_legale_indirizzo = '';
    public $sede_legale_citta = '';
    public $sede_legale_provincia = '';
    public $sede_legale_cap = '';
    public $partita_iva = '';
    public $codice_fiscale = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroAttivo' => ['except' => ''],
        'filtroSocieta' => ['except' => ''],
    ];

    protected $rules = [
        'codice' => 'required|string|max:10|unique:sedi,codice',
        'nome' => 'required|string|max:255',
        'indirizzo' => 'nullable|string|max:255',
        'citta' => 'nullable|string|max:100',
        'provincia' => 'nullable|string|max:2',
        'cap' => 'nullable|string|max:10',
        'telefono' => 'nullable|string|max:50',
        'email' => 'nullable|email|max:255',
        'tipo' => 'required|in:negozio,deposito,ufficio',
        'societa_id' => 'required|exists:societa,id',
        'attivo' => 'boolean',
        'note' => 'nullable|string',
        'sede_legale' => 'nullable|string|max:255',
        'sede_legale_indirizzo' => 'nullable|string|max:255',
        'sede_legale_citta' => 'nullable|string|max:100',
        'sede_legale_provincia' => 'nullable|string|max:2',
        'sede_legale_cap' => 'nullable|string|max:10',
        'partita_iva' => 'nullable|string|max:30',
        'codice_fiscale' => 'nullable|string|max:30',
    ];

    protected $messages = [
        'codice.unique' => 'Il codice sede è già esistente',
        'societa_id.required' => 'La società è obbligatoria',
    ];

    public function mount()
    {
        // 
    }

    // ==========================================
    // COMPUTED PROPERTIES
    // ==========================================

    public function getSediProperty()
    {
        $query = Sede::with('societa');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('codice', 'like', "%{$this->search}%")
                  ->orWhere('nome', 'like', "%{$this->search}%")
                  ->orWhere('citta', 'like', "%{$this->search}%");
            });
        }

        if ($this->filtroAttivo !== '') {
            $query->where('attivo', $this->filtroAttivo === 'si');
        }

        if ($this->filtroSocieta) {
            $query->where('societa_id', $this->filtroSocieta);
        }

        return $query->orderBy('nome')->paginate(15);
    }

    public function getSocietaProperty()
    {
        return Societa::where('attivo', true)->orderBy('codice')->get();
    }

    // ==========================================
    // ACTIONS
    // ==========================================

    public function apriModalCreazione()
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function apriModalModifica($sedeId)
    {
        $sede = Sede::findOrFail($sedeId);
        
        $this->sedeSelezionataId = $sede->id;
        $this->codice = $sede->codice;
        $this->nome = $sede->nome;
        $this->indirizzo = $sede->indirizzo ?? '';
        $this->citta = $sede->citta ?? '';
        $this->provincia = $sede->provincia ?? '';
        $this->cap = $sede->cap ?? '';
        $this->telefono = $sede->telefono ?? '';
        $this->email = $sede->email ?? '';
        $this->tipo = $sede->tipo;
        $this->societa_id = $sede->societa_id;
        $this->attivo = $sede->attivo;
        $this->note = $sede->note ?? '';
        $this->orari = $sede->orari ?? [];
        $this->sede_legale = $sede->sede_legale ?? '';
        $this->sede_legale_indirizzo = $sede->sede_legale_indirizzo ?? '';
        $this->sede_legale_citta = $sede->sede_legale_citta ?? '';
        $this->sede_legale_provincia = $sede->sede_legale_provincia ?? '';
        $this->sede_legale_cap = $sede->sede_legale_cap ?? '';
        $this->partita_iva = $sede->partita_iva ?? '';
        $this->codice_fiscale = $sede->codice_fiscale ?? '';
        
        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function salva()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['codice'] = 'required|string|max:10|unique:sedi,codice,' . $this->sedeSelezionataId;
        }

        $this->validate();

        $data = [
            'codice' => strtoupper($this->codice),
            'nome' => $this->nome,
            'indirizzo' => $this->indirizzo ?: null,
            'citta' => $this->citta ?: null,
            'provincia' => strtoupper($this->provincia ?: ''),
            'cap' => $this->cap ?: null,
            'telefono' => $this->telefono ?: null,
            'email' => $this->email ?: null,
            'tipo' => $this->tipo,
            'societa_id' => $this->societa_id,
            'attivo' => $this->attivo,
            'note' => $this->note ?: null,
            'orari' => !empty($this->orari) ? $this->orari : null,
            'sede_legale' => $this->sede_legale ?: null,
            'sede_legale_indirizzo' => $this->sede_legale_indirizzo ?: null,
            'sede_legale_citta' => $this->sede_legale_citta ?: null,
            'sede_legale_provincia' => strtoupper($this->sede_legale_provincia ?: ''),
            'sede_legale_cap' => $this->sede_legale_cap ?: null,
            'partita_iva' => $this->partita_iva ?: null,
            'codice_fiscale' => $this->codice_fiscale ?: null,
        ];

        if ($this->modalMode === 'create') {
            Sede::create($data);
            session()->flash('message', '✅ Sede creata con successo');
        } else {
            Sede::find($this->sedeSelezionataId)->update($data);
            session()->flash('message', '✅ Sede aggiornata con successo');
        }

        $this->chiudiModal();
        $this->resetForm();
    }

    public function apriModalEliminazione($sedeId)
    {
        $this->sedeSelezionataId = $sedeId;
        $this->showDeleteModal = true;
    }

    public function elimina()
    {
        $sede = Sede::findOrFail($this->sedeSelezionataId);
        
        // Verifica se ha articoli o categorie associate
        if ($sede->articoli()->count() > 0 || $sede->categorieMerceologiche()->count() > 0) {
            session()->flash('error', '❌ Impossibile eliminare: ci sono articoli o magazzini associati');
            $this->chiudiModalEliminazione();
            return;
        }

        $sede->delete();
        session()->flash('message', '✅ Sede eliminata con successo');
        $this->chiudiModalEliminazione();
    }

    public function chiudiModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function chiudiModalEliminazione()
    {
        $this->showDeleteModal = false;
        $this->sedeSelezionataId = null;
    }

    public function resetForm()
    {
        $this->sedeSelezionataId = null;
        $this->codice = '';
        $this->nome = '';
        $this->indirizzo = '';
        $this->citta = '';
        $this->provincia = '';
        $this->cap = '';
        $this->telefono = '';
        $this->email = '';
        $this->tipo = 'negozio';
        $this->societa_id = '';
        $this->attivo = true;
        $this->note = '';
        $this->orari = [];
        $this->sede_legale = '';
        $this->sede_legale_indirizzo = '';
        $this->sede_legale_citta = '';
        $this->sede_legale_provincia = '';
        $this->sede_legale_cap = '';
        $this->partita_iva = '';
        $this->codice_fiscale = '';
        $this->modalMode = 'create';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.gestione-sedi', [
            'sedi' => $this->sedi,
            'societaList' => $this->societa,
        ]);
    }
}
