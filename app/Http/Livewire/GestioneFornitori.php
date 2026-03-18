<?php

namespace App\Http\Livewire;

use App\Models\Fornitore;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * GestioneFornitori - CRUD completo fornitori
 */
class GestioneFornitori extends Component
{
    use WithPagination;

    // Filtri e ricerca
    public $search = '';
    public $filtroAttivo = '';

    // Modali
    public $showModal = false;
    public $showDeleteModal = false;
    public $modalMode = 'create';
    public $fornitoreSelezionatoId = null;

    // Form fields
    public $codice = '';
    public $ragione_sociale = '';
    public $partita_iva = '';
    public $codice_fiscale = '';
    public $indirizzo = '';
    public $citta = '';
    public $provincia = '';
    public $cap = '';
    public $nazione = 'Italia';
    public $telefono = '';
    public $email = '';
    public $pec = '';
    public $note = '';
    public $attivo = true;

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroAttivo' => ['except' => ''],
    ];

    protected $rules = [
        'codice' => 'required|string|max:50|unique:fornitori,codice',
        'ragione_sociale' => 'required|string|max:255',
        'partita_iva' => 'nullable|string|max:30',
        'codice_fiscale' => 'nullable|string|max:30',
        'indirizzo' => 'nullable|string|max:255',
        'citta' => 'nullable|string|max:100',
        'provincia' => 'nullable|string|max:2',
        'cap' => 'nullable|string|max:10',
        'nazione' => 'nullable|string|max:100',
        'telefono' => 'nullable|string|max:50',
        'email' => 'nullable|email|max:255',
        'pec' => 'nullable|email|max:255',
        'note' => 'nullable|string',
        'attivo' => 'boolean',
    ];

    protected $messages = [
        'codice.unique' => 'Il codice fornitore e gia esistente',
        'ragione_sociale.required' => 'La ragione sociale e obbligatoria',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroAttivo(): void
    {
        $this->resetPage();
    }

    public function getFornitoriProperty()
    {
        $query = Fornitore::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('codice', 'like', '%' . $this->search . '%')
                    ->orWhere('ragione_sociale', 'like', '%' . $this->search . '%')
                    ->orWhere('partita_iva', 'like', '%' . $this->search . '%')
                    ->orWhere('codice_fiscale', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filtroAttivo !== '') {
            $query->where('attivo', $this->filtroAttivo === 'si');
        }

        return $query->orderBy('ragione_sociale')->paginate(15);
    }

    public function apriModalCreazione(): void
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function apriModalModifica(int $fornitoreId): void
    {
        $fornitore = Fornitore::findOrFail($fornitoreId);

        $this->fornitoreSelezionatoId = $fornitore->id;
        $this->codice = $fornitore->codice ?? '';
        $this->ragione_sociale = $fornitore->ragione_sociale ?? '';
        $this->partita_iva = $fornitore->partita_iva ?? '';
        $this->codice_fiscale = $fornitore->codice_fiscale ?? '';
        $this->indirizzo = $fornitore->indirizzo ?? '';
        $this->citta = $fornitore->citta ?? '';
        $this->provincia = $fornitore->provincia ?? '';
        $this->cap = $fornitore->cap ?? '';
        $this->nazione = $fornitore->nazione ?? 'Italia';
        $this->telefono = $fornitore->telefono ?? '';
        $this->email = $fornitore->email ?? '';
        $this->pec = $fornitore->pec ?? '';
        $this->note = $fornitore->note ?? '';
        $this->attivo = (bool) $fornitore->attivo;

        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function salva(): void
    {
        if ($this->modalMode === 'edit') {
            $this->rules['codice'] = 'required|string|max:50|unique:fornitori,codice,' . $this->fornitoreSelezionatoId;
        }

        $this->validate();

        $data = [
            'codice' => strtoupper(trim((string) $this->codice)),
            'ragione_sociale' => trim((string) $this->ragione_sociale),
            'partita_iva' => strtoupper(trim((string) $this->partita_iva)) ?: null,
            'codice_fiscale' => strtoupper(trim((string) $this->codice_fiscale)) ?: null,
            'indirizzo' => trim((string) $this->indirizzo) ?: null,
            'citta' => trim((string) $this->citta) ?: null,
            'provincia' => strtoupper(trim((string) $this->provincia)) ?: null,
            'cap' => trim((string) $this->cap) ?: null,
            'nazione' => trim((string) $this->nazione) ?: null,
            'telefono' => trim((string) $this->telefono) ?: null,
            'email' => trim((string) $this->email) ?: null,
            'pec' => trim((string) $this->pec) ?: null,
            'note' => trim((string) $this->note) ?: null,
            'attivo' => (bool) $this->attivo,
        ];

        if ($this->modalMode === 'create') {
            Fornitore::create($data);
            session()->flash('message', '✅ Fornitore creato con successo');
        } else {
            Fornitore::findOrFail($this->fornitoreSelezionatoId)->update($data);
            session()->flash('message', '✅ Fornitore aggiornato con successo');
        }

        $this->chiudiModal();
        $this->resetForm();
    }

    public function apriModalEliminazione(int $fornitoreId): void
    {
        $this->fornitoreSelezionatoId = $fornitoreId;
        $this->showDeleteModal = true;
    }

    public function elimina(): void
    {
        $fornitore = Fornitore::findOrFail($this->fornitoreSelezionatoId);

        $hasRelazioni = $fornitore->articoli()->exists()
            || $fornitore->prezzi()->exists()
            || $fornitore->ddt()->exists()
            || $fornitore->fatture()->exists();

        if ($hasRelazioni) {
            session()->flash('error', '❌ Impossibile eliminare: il fornitore ha documenti, articoli o prezzi associati');
            $this->chiudiModalEliminazione();
            return;
        }

        $fornitore->delete();
        session()->flash('message', '✅ Fornitore eliminato con successo');
        $this->chiudiModalEliminazione();
    }

    public function chiudiModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function chiudiModalEliminazione(): void
    {
        $this->showDeleteModal = false;
        $this->fornitoreSelezionatoId = null;
    }

    public function resetForm(): void
    {
        $this->fornitoreSelezionatoId = null;
        $this->codice = '';
        $this->ragione_sociale = '';
        $this->partita_iva = '';
        $this->codice_fiscale = '';
        $this->indirizzo = '';
        $this->citta = '';
        $this->provincia = '';
        $this->cap = '';
        $this->nazione = 'Italia';
        $this->telefono = '';
        $this->email = '';
        $this->pec = '';
        $this->note = '';
        $this->attivo = true;
        $this->modalMode = 'create';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.gestione-fornitori', [
            'fornitori' => $this->fornitori,
        ]);
    }
}

