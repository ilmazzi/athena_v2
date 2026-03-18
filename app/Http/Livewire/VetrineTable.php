<?php

namespace App\Http\Livewire;

use App\Models\Vetrina;
use App\Models\Sede;
use Livewire\Component;
use Livewire\WithPagination;

class VetrineTable extends Component
{
    use WithPagination;

    // Proprietà per filtri e ricerca
    public $search = '';
    public $tipologiaFilter = '';
    public $attivaFilter = '';
    public $sedeFilter = '';
    public $ubicazioneFilter = '';
    public $sortField = 'codice';
    public $sortDirection = 'asc';
    
    // Proprietà per modal creazione/modifica
    public $showModal = false;
    public $editingVetrina = null;
    public $codice = '';
    public $nome = '';
    public $tipologia = 'gioielleria';
    public $ubicazione = '';
    public $sede_id = '';
    public $attiva = true;
    public $note = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tipologiaFilter' => ['except' => ''],
        'attivaFilter' => ['except' => ''],
        'sedeFilter' => ['except' => ''],
        'ubicazioneFilter' => ['except' => ''],
        'sortField' => ['except' => 'codice'],
        'sortDirection' => ['except' => 'asc'],
    ];

    protected $rules = [
        'codice' => 'required|string|max:50',
        'nome' => 'required|string|max:255',
        'tipologia' => 'required|in:gioielleria,orologeria',
        'ubicazione' => 'nullable|string|max:255',
        'sede_id' => 'nullable|exists:sedi,id',
        'attiva' => 'boolean',
        'note' => 'nullable|string',
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTipologiaFilter()
    {
        $this->resetPage();
    }

    public function updatedAttivaFilter()
    {
        $this->resetPage();
    }

    public function updatedSedeFilter()
    {
        $this->resetPage();
    }

    public function updatedUbicazioneFilter()
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['codice', 'nome', 'tipologia', 'sede', 'ubicazione', 'articoli_count', 'attiva'];
        if (!in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function createVetrina()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editVetrina($id)
    {
        $vetrina = Vetrina::findOrFail($id);
        
        $this->editingVetrina = $vetrina;
        $this->codice = $vetrina->codice;
        $this->nome = $vetrina->nome;
        $this->tipologia = $vetrina->tipologia;
        $this->ubicazione = $vetrina->ubicazione;
        $this->sede_id = $vetrina->sede_id;
        $this->attiva = $vetrina->attiva;
        $this->note = $vetrina->note;
        
        $this->showModal = true;
    }

    public function saveVetrina()
    {
        $this->validate();

        try {
            if ($this->editingVetrina) {
                // Aggiorna vetrina esistente
                $this->editingVetrina->update([
                    'codice' => $this->codice,
                    'nome' => $this->nome,
                    'tipologia' => $this->tipologia,
                    'ubicazione' => $this->ubicazione,
                    'sede_id' => $this->sede_id ?: null,
                    'attiva' => $this->attiva,
                    'note' => $this->note,
                ]);
                
                session()->flash('success', "Vetrina {$this->codice} aggiornata con successo");
            } else {
                // Crea nuova vetrina
                Vetrina::create([
                    'codice' => $this->codice,
                    'nome' => $this->nome,
                    'tipologia' => $this->tipologia,
                    'ubicazione' => $this->ubicazione,
                    'sede_id' => $this->sede_id ?: null,
                    'attiva' => $this->attiva,
                    'note' => $this->note,
                ]);
                
                session()->flash('success', "Vetrina {$this->codice} creata con successo");
            }

            $this->closeModal();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il salvataggio: ' . $e->getMessage());
        }
    }

    public function deleteVetrina($id)
    {
        try {
            $vetrina = Vetrina::findOrFail($id);
            
            // Verifica se ha articoli
            if ($vetrina->articoli()->count() > 0) {
                session()->flash('error', "Impossibile eliminare la vetrina {$vetrina->codice}: contiene ancora articoli");
                return;
            }
            
            $codice = $vetrina->codice;
            $vetrina->delete();
            
            session()->flash('success', "Vetrina {$codice} eliminata con successo");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->editingVetrina = null;
        $this->codice = '';
        $this->nome = '';
        $this->tipologia = 'gioielleria';
        $this->ubicazione = '';
        $this->sede_id = '';
        $this->attiva = true;
        $this->note = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $search = trim((string) $this->search);

        $vetrine = Vetrina::query()
            ->with('sede')
            ->withCount([
                'articoli as articoli_count' => function ($query) {
                    $query->whereNull('data_rimozione');
                },
            ])
            ->when($search !== '', function ($query) use ($search) {
                $searchTerm = '%' . $search . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('codice', 'like', $searchTerm)
                      ->orWhere('nome', 'like', $searchTerm)
                      ->orWhere('ubicazione', 'like', $searchTerm)
                      ->orWhereHas('articoli', function ($articoliQuery) use ($searchTerm) {
                          $articoliQuery->whereNull('data_rimozione')
                              ->whereHas('articolo', function ($articoloQuery) use ($searchTerm) {
                                  $articoloQuery->where('codice', 'like', $searchTerm)
                                      ->orWhere('descrizione', 'like', $searchTerm)
                                      ->orWhereRaw(
                                          "(CASE WHEN JSON_VALID(articoli.caratteristiche) " .
                                          "THEN JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.referenza')) " .
                                          "ELSE articoli.caratteristiche END) LIKE ?",
                                          [$searchTerm]
                                      );
                              });
                      });
                });
            })
            ->when($this->tipologiaFilter, function ($query) {
                $query->where('tipologia', $this->tipologiaFilter);
            })
            ->when($this->attivaFilter !== '', function ($query) {
                $query->where('attiva', $this->attivaFilter);
            })
            ->when($this->sedeFilter !== '', function ($query) {
                $query->where('sede_id', $this->sedeFilter);
            })
            ->when($this->ubicazioneFilter !== '', function ($query) {
                $query->where('ubicazione', $this->ubicazioneFilter);
            })
            ->when($this->sortField === 'sede', function ($query) {
                $query->orderBy(
                    Sede::query()
                        ->select('nome')
                        ->whereColumn('sedi.id', 'vetrine.sede_id')
                        ->limit(1),
                    $this->sortDirection
                );
            })
            ->when($this->sortField !== 'sede', function ($query) {
                $query->orderBy($this->sortField, $this->sortDirection);
            })
            ->orderBy('id')
            ->paginate(20);

        $sedi = Sede::query()
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $ubicazioni = Vetrina::query()
            ->whereNotNull('ubicazione')
            ->whereRaw("TRIM(ubicazione) <> ''")
            ->select('ubicazione')
            ->distinct()
            ->orderBy('ubicazione')
            ->pluck('ubicazione');

        return view('livewire.vetrine-table', [
            'vetrine' => $vetrine,
            'sedi' => $sedi,
            'ubicazioni' => $ubicazioni,
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ]);
    }
}
