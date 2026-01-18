<?php

namespace App\Http\Livewire;

use App\Models\Vetrina;
use App\Models\Articolo;
use App\Models\ArticoloVetrina;
use App\Models\ProdottoFinito;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class VetrinaDetail extends Component
{
    use WithPagination;

    public $vetrina;
    public $search = '';
    
    // Modal aggiunta articolo
    public $showAddModal = false;
    public $selectedArticolo = null;
    public $prezzo_vetrina = '';
    public $testo_vetrina = '';
    public $posizione = '';
    public $ripiano = '';
    
    // Modal spostamento articolo
    public $showMoveModal = false;
    public $articoloToMove = null;
    public $targetVetrinaId = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    protected $rules = [
        'prezzo_vetrina' => 'required|string|max:50',
        'testo_vetrina' => 'required|string|max:500',
        'posizione' => 'nullable|integer|min:0',
        'ripiano' => 'nullable|string|max:50',
    ];

    public function mount($id)
    {
        $this->vetrina = Vetrina::findOrFail($id);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openAddModal()
    {
        $this->resetAddForm();
        $this->showAddModal = true;
    }

    public function selectArticolo($articoloId)
    {
        $this->selectedArticolo = Articolo::findOrFail($articoloId);
        
        // Se l'articolo ha già un ultimo testo vetrina salvato, lo proponiamo
        if ($this->selectedArticolo->ultimo_testo_vetrina) {
            $this->testo_vetrina = $this->selectedArticolo->ultimo_testo_vetrina;
        }

        // Precompila prezzo vetrina dal prezzo fornitore se presente e il campo è vuoto
        if ($this->prezzo_vetrina === '' && $this->selectedArticolo->prezzo_fornitore) {
            $this->prezzo_vetrina = number_format($this->selectedArticolo->prezzo_fornitore, 2, ',', '.');
        }
    }

    public function addArticoloToVetrina()
    {
        $this->validate();

        try {
            // Verifica che l'articolo non sia già in una vetrina
            $esisteInVetrina = ArticoloVetrina::where('articolo_id', $this->selectedArticolo->id)
                ->whereNull('data_rimozione')
                ->exists();

            if ($esisteInVetrina) {
                session()->flash('error', 'L\'articolo è già presente in una vetrina');
                return;
            }

            ArticoloVetrina::create([
                'vetrina_id' => $this->vetrina->id,
                'articolo_id' => $this->selectedArticolo->id,
                'prezzo_vetrina' => $this->prezzo_vetrina,
                'testo_vetrina' => $this->testo_vetrina,
                'posizione' => $this->posizione ?: 0,
                'ripiano' => $this->ripiano,
                'data_inserimento' => now()->toDateString(),
            ]);

            // Salva l'ultimo testo vetrina nell'articolo per future proposte
            $this->selectedArticolo->update([
                'ultimo_testo_vetrina' => $this->testo_vetrina
            ]);

            session()->flash('success', "Articolo {$this->selectedArticolo->codice} aggiunto alla vetrina");
            $this->closeAddModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'aggiunta: ' . $e->getMessage());
        }
    }

    public function removeArticoloFromVetrina($articoloVetrinaId)
    {
        try {
            $articoloVetrina = ArticoloVetrina::findOrFail($articoloVetrinaId);
            
            // Calcola giorni esposizione
            $dataInserimento = \Carbon\Carbon::parse($articoloVetrina->data_inserimento);
            $giorniEsposizione = $dataInserimento->diffInDays(now());
            
            $articoloVetrina->update([
                'data_rimozione' => now()->toDateString(),
                'giorni_esposizione' => $giorniEsposizione,
            ]);

            session()->flash('success', 'Articolo rimosso dalla vetrina');

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la rimozione: ' . $e->getMessage());
        }
    }

    public function openMoveModal($articoloVetrinaId)
    {
        $this->articoloToMove = ArticoloVetrina::with(['articolo'])->findOrFail($articoloVetrinaId);
        $this->targetVetrinaId = '';
        $this->showMoveModal = true;
    }

    public function moveArticolo()
    {
        $this->validate(['targetVetrinaId' => 'required|exists:vetrine,id']);

        try {
            // Verifica che la vetrina di destinazione sia diversa
            if ($this->targetVetrinaId == $this->vetrina->id) {
                session()->flash('error', 'Seleziona una vetrina diversa da quella attuale');
                return;
            }

            // Rimuovi dalla vetrina attuale
            $dataInserimento = \Carbon\Carbon::parse($this->articoloToMove->data_inserimento);
            $giorniEsposizione = $dataInserimento->diffInDays(now());
            
            $this->articoloToMove->update([
                'data_rimozione' => now()->toDateString(),
                'giorni_esposizione' => $giorniEsposizione,
            ]);

            // Aggiungi alla nuova vetrina
            ArticoloVetrina::create([
                'vetrina_id' => $this->targetVetrinaId,
                'articolo_id' => $this->articoloToMove->articolo_id,
                'prezzo_vetrina' => $this->articoloToMove->prezzo_vetrina,
                'testo_vetrina' => $this->articoloToMove->testo_vetrina,
                'posizione' => 0,
                'ripiano' => null,
                'data_inserimento' => now()->toDateString(),
            ]);

            $targetVetrina = Vetrina::find($this->targetVetrinaId);
            session()->flash('success', "Articolo spostato in vetrina {$targetVetrina->nome}");
            $this->closeMoveModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante lo spostamento: ' . $e->getMessage());
        }
    }

    public function updatePrezzo($articoloVetrinaId, $nuovoPrezzo)
    {
        try {
            $articoloVetrina = ArticoloVetrina::findOrFail($articoloVetrinaId);
            $articoloVetrina->update(['prezzo_vetrina' => $nuovoPrezzo]);
            
            session()->flash('success', 'Prezzo aggiornato');

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }

    public function closeAddModal()
    {
        $this->showAddModal = false;
        $this->resetAddForm();
    }

    public function closeMoveModal()
    {
        $this->showMoveModal = false;
        $this->articoloToMove = null;
        $this->targetVetrinaId = '';
    }

    private function resetAddForm()
    {
        $this->selectedArticolo = null;
        $this->prezzo_vetrina = '';
        $this->testo_vetrina = '';
        $this->posizione = '';
        $this->ripiano = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        // Articoli attualmente in vetrina
        $articoliInVetrina = ArticoloVetrina::with(['articolo.categoriaMerceologica', 'articolo.sede'])
            ->where('vetrina_id', $this->vetrina->id)
            ->whereNull('data_rimozione')
            ->when($this->search, function ($query) {
                $query->whereHas('articolo', function ($q) {
                    $q->where('codice', 'like', '%' . $this->search . '%')
                      ->orWhere('descrizione', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('posizione')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Articoli disponibili per aggiunta (inclusi PF, esclusi scaricati e già in vetrina)
        $articoliDisponibili = [];
        $prodottiFinitiDisponibili = [];
        
        if ($this->showAddModal) {
            // Articoli normali disponibili
            $articoliDisponibili = Articolo::with(['categoriaMerceologica', 'sede', 'giacenza'])
                ->where('stato_articolo', '!=', 'scaricato')
                ->whereHas('giacenza', function ($query) {
                    $query->where('quantita_residua', '>', 0);
                })
                ->whereNotExists(function ($query) {
                    $query->select(\DB::raw(1))
                          ->from('articoli_vetrine')
                          ->whereColumn('articoli_vetrine.articolo_id', 'articoli.id')
                          ->whereNull('articoli_vetrine.data_rimozione');
                })
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('codice', 'like', '%' . $this->search . '%')
                          ->orWhere('descrizione', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('codice')
                ->limit(25)
                ->get();

            // Prodotti finiti disponibili
            $prodottiFinitiDisponibili = ProdottoFinito::with(['categoriaMerceologica'])
                ->where('stato', 'completato')
                ->whereNotExists(function ($query) {
                    $query->select(\DB::raw(1))
                          ->from('articoli_vetrine')
                          ->whereColumn('articoli_vetrine.articolo_id', 'prodotti_finiti.id')
                          ->whereNull('articoli_vetrine.data_rimozione');
                })
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('codice', 'like', '%' . $this->search . '%')
                          ->orWhere('descrizione', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('codice')
                ->limit(25)
                ->get();
        }

        // Altre vetrine per spostamento
        $altreVetrine = Vetrina::where('id', '!=', $this->vetrina->id)
            ->where('attiva', true)
            ->orderBy('nome')
            ->get();

        return view('livewire.vetrina-detail', [
            'articoliInVetrina' => $articoliInVetrina,
            'articoliDisponibili' => $articoliDisponibili,
            'prodottiFinitiDisponibili' => $prodottiFinitiDisponibili,
            'altreVetrine' => $altreVetrine,
        ]);
    }
}
