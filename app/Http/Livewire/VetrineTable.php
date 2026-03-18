<?php

namespace App\Http\Livewire;

use App\Models\Articolo;
use App\Models\ArticoloVetrina;
use App\Models\Vetrina;
use App\Models\Sede;
use Illuminate\Support\Facades\DB;
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
    public $sortField = 'codice';
    public $sortDirection = 'asc';
    
    // Proprietà per modal creazione/modifica
    public $showModal = false;
    public $editingVetrina = null;
    public $codice = '';
    public $nome = '';
    public $tipologia = 'gioielleria';
    public $sede_id = '';
    public $attiva = true;
    public $note = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tipologiaFilter' => ['except' => ''],
        'attivaFilter' => ['except' => ''],
        'sedeFilter' => ['except' => ''],
        'sortField' => ['except' => 'codice'],
        'sortDirection' => ['except' => 'asc'],
    ];

    protected $rules = [
        'codice' => 'required|string|max:50',
        'nome' => 'required|string|max:255',
        'tipologia' => 'required|in:gioielleria,orologeria',
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

    public function sortBy(string $field): void
    {
        $allowedFields = $this->getAllowedSortFields();
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
            $codice = DB::transaction(function () use ($id) {
                $vetrina = Vetrina::findOrFail($id);
                $this->svuotaVetrinaById($vetrina->id);
                $codice = $vetrina->codice;
                $vetrina->delete();
                return $codice;
            });

            session()->flash('success', "Vetrina {$codice} eliminata con successo");
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    public function svuotaVetrina($id)
    {
        try {
            $vetrina = Vetrina::findOrFail($id);
            $svuotati = DB::transaction(function () use ($vetrina) {
                return $this->svuotaVetrinaById($vetrina->id);
            });

            if ($svuotati > 0) {
                session()->flash('success', "Vetrina {$vetrina->codice} svuotata: {$svuotati} articoli rimossi");
            } else {
                session()->flash('success', "Vetrina {$vetrina->codice} già vuota");
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante lo svuotamento: ' . $e->getMessage());
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
        $this->sede_id = '';
        $this->attiva = true;
        $this->note = '';
        $this->resetErrorBag();
    }

    private function svuotaVetrinaById(int $vetrinaId): int
    {
        $activeRows = ArticoloVetrina::query()
            ->where('vetrina_id', $vetrinaId)
            ->whereNull('data_rimozione')
            ->get(['id', 'articolo_id']);

        if ($activeRows->isEmpty()) {
            return 0;
        }

        $activeIds = $activeRows->pluck('id');
        $articoloIds = $activeRows->pluck('articolo_id')->filter()->unique();

        ArticoloVetrina::query()
            ->whereIn('id', $activeIds)
            ->update([
                'data_rimozione' => now()->toDateString(),
                'giorni_esposizione' => DB::raw('DATEDIFF(CURDATE(), data_inserimento)'),
                'updated_at' => now(),
            ]);

        if ($articoloIds->isNotEmpty()) {
            Articolo::query()
                ->whereIn('id', $articoloIds)
                ->update(['in_vetrina' => false]);
        }

        return $activeIds->count();
    }

    public function render()
    {
        // Gestisce querystring legacy (es. sortField=ubicazione) senza errori SQL.
        if (!in_array($this->sortField, $this->getAllowedSortFields(), true)) {
            $this->sortField = 'codice';
            $this->sortDirection = 'asc';
        }

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
                      ->orWhereHas('sede', function ($sedeQuery) use ($searchTerm) {
                          $sedeQuery->where('nome', 'like', $searchTerm);
                      })
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

        return view('livewire.vetrine-table', [
            'vetrine' => $vetrine,
            'sedi' => $sedi,
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ]);
    }

    private function getAllowedSortFields(): array
    {
        return ['codice', 'nome', 'tipologia', 'sede', 'articoli_count', 'attiva'];
    }
}
