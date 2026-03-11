<?php

namespace App\Http\Livewire;

use App\Models\Vetrina;
use App\Models\Articolo;
use App\Models\ArticoloVetrina;
use App\Models\CategoriaMerceologica;
use App\Models\ProdottoFinito;
use App\Models\Sede;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class VetrinaDetail extends Component
{
    use WithPagination;

    public $vetrina;
    public $search = '';
    
    // Modal aggiunta articolo
    public $showAddModal = false;
    public $addMode = 'interno'; // interno|esterno
    public $selectedArticolo = null;
    public $selectedProdottoFinito = null;
    public $selectedItemType = null; // articolo|pf
    public $prezzo_vetrina = '';
    public $testo_vetrina = '';
    public $posizione = '';
    public $ripiano = '';

    // Articolo NC (esterno)
    public $descrizione_esterno = '';
    public $categoria_merceologica_id_esterno = '';
    public $sede_id_esterno = '';
    public $foto_principale_esterno = '';
    public $materiale_esterno = '';
    public $titolo_esterno = '';
    public $caratura_esterno = '';
    public $colore_esterno = '';
    public $peso_lordo_esterno = '';
    public $peso_netto_esterno = '';
    public $prezzo_acquisto_esterno = '';
    public $prezzo_fornitore_esterno = '';
    public $note_esterno = '';
    
    // Modal spostamento articolo
    public $showMoveModal = false;
    public $articoloToMove = null;
    public $targetVetrinaId = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    protected $rules = [];

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
        $this->addMode = 'interno';
        $this->showAddModal = true;
    }

    public function setAddMode(string $mode)
    {
        $this->addMode = $mode;
        $this->selectedArticolo = null;
        $this->resetValidation();
    }

    public function selectItem(string $type, $itemId)
    {
        $this->addMode = 'interno';
        $this->selectedItemType = $type;
        $this->selectedArticolo = null;
        $this->selectedProdottoFinito = null;

        if ($type === 'pf') {
            $this->selectedProdottoFinito = ProdottoFinito::with(['categoriaMerceologica'])->findOrFail($itemId);
            return;
        }

        $this->selectedArticolo = Articolo::findOrFail($itemId);
        
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
        $this->validate($this->getAddRules());

        try {
            if ($this->addMode === 'esterno') {
                ArticoloVetrina::create([
                    'vetrina_id' => $this->vetrina->id,
                    'articolo_id' => null,
                    'tipo_articolo' => 'esterno',
                    'descrizione_esterno' => $this->descrizione_esterno,
                    'categoria_merceologica_id' => $this->categoria_merceologica_id_esterno ?: null,
                    'sede_id' => $this->sede_id_esterno ?: null,
                    'foto_principale_esterno' => $this->foto_principale_esterno,
                    'materiale_esterno' => $this->materiale_esterno,
                    'titolo_esterno' => $this->titolo_esterno,
                    'caratura_esterno' => $this->caratura_esterno,
                    'colore_esterno' => $this->colore_esterno,
                    'peso_lordo_esterno' => $this->peso_lordo_esterno,
                    'peso_netto_esterno' => $this->peso_netto_esterno,
                    'prezzo_acquisto_esterno' => $this->prezzo_acquisto_esterno,
                    'prezzo_fornitore_esterno' => $this->prezzo_fornitore_esterno,
                    'note_esterno' => $this->note_esterno,
                    'prezzo_vetrina' => $this->prezzo_vetrina,
                    'testo_vetrina' => $this->testo_vetrina,
                    'posizione' => $this->posizione ?: 0,
                    'ripiano' => $this->ripiano,
                    'data_inserimento' => now()->toDateString(),
                ]);

                session()->flash('success', 'Articolo NC aggiunto alla vetrina');
                $this->closeAddModal();
                return;
            }

            if ($this->selectedItemType === 'pf') {
                if (!$this->selectedProdottoFinito) {
                    session()->flash('error', 'Seleziona un prodotto finito');
                    return;
                }
                if ($this->vetrina->sede_id) {
                    $sedeId = $this->vetrina->sede_id;
                    $pfSedeId = $this->selectedProdottoFinito->categoriaMerceologica?->sede_id;
                    $depositoSedeId = $this->selectedProdottoFinito->contoDepositoCorrente?->sede_destinataria_id;
                    if ($pfSedeId !== $sedeId && $depositoSedeId !== $sedeId) {
                        session()->flash('error', 'Il PF non appartiene alla sede della vetrina');
                        return;
                    }
                }

                $esisteInVetrina = ArticoloVetrina::where('prodotto_finito_id', $this->selectedProdottoFinito->id)
                    ->whereNull('data_rimozione')
                    ->exists();

                if ($esisteInVetrina) {
                    session()->flash('error', 'Il prodotto finito è già presente in una vetrina');
                    return;
                }

                ArticoloVetrina::updateOrCreate(
                    [
                        'vetrina_id' => $this->vetrina->id,
                        'prodotto_finito_id' => $this->selectedProdottoFinito->id,
                    ],
                    [
                        'articolo_id' => null,
                        'tipo_articolo' => 'prodotto_finito',
                        'prezzo_vetrina' => $this->prezzo_vetrina,
                        'testo_vetrina' => $this->testo_vetrina,
                        'posizione' => $this->posizione ?: 0,
                        'ripiano' => $this->ripiano,
                        'data_inserimento' => now()->toDateString(),
                        'data_rimozione' => null,
                        'giorni_esposizione' => null,
                    ]
                );

                session()->flash('success', "PF {$this->selectedProdottoFinito->codice} aggiunto alla vetrina");
                $this->closeAddModal();
                return;
            }

            if (!$this->selectedArticolo) {
                session()->flash('error', 'Seleziona un articolo');
                return;
            }
            if ($this->vetrina->sede_id) {
                $sedeId = $this->vetrina->sede_id;
                $depositoSedeId = $this->selectedArticolo->contoDepositoCorrente?->sede_destinataria_id;
                if ($this->selectedArticolo->sede_id !== $sedeId && $depositoSedeId !== $sedeId) {
                    session()->flash('error', 'L\'articolo non appartiene alla sede della vetrina');
                    return;
                }
            }

            $esisteInVetrina = ArticoloVetrina::where('articolo_id', $this->selectedArticolo->id)
                ->whereNull('data_rimozione')
                ->exists();

            if ($esisteInVetrina) {
                session()->flash('error', 'L\'articolo è già presente in una vetrina');
                return;
            }

            ArticoloVetrina::updateOrCreate(
                [
                    'vetrina_id' => $this->vetrina->id,
                    'articolo_id' => $this->selectedArticolo->id,
                ],
                [
                    'tipo_articolo' => 'interno',
                    'prezzo_vetrina' => $this->prezzo_vetrina,
                    'testo_vetrina' => $this->testo_vetrina,
                    'posizione' => $this->posizione ?: 0,
                    'ripiano' => $this->ripiano,
                    'data_inserimento' => now()->toDateString(),
                    'data_rimozione' => null,
                    'giorni_esposizione' => null,
                ]
            );

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
        $this->articoloToMove = ArticoloVetrina::with(['articolo', 'prodottoFinito'])->findOrFail($articoloVetrinaId);
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
            ArticoloVetrina::updateOrCreate(
                [
                    'vetrina_id' => $this->targetVetrinaId,
                    'articolo_id' => $this->articoloToMove->articolo_id,
                    'prodotto_finito_id' => $this->articoloToMove->prodotto_finito_id,
                ],
                [
                    'tipo_articolo' => $this->articoloToMove->tipo_articolo,
                    'prezzo_vetrina' => $this->articoloToMove->prezzo_vetrina,
                    'testo_vetrina' => $this->articoloToMove->testo_vetrina,
                    'posizione' => 0,
                    'ripiano' => null,
                    'data_inserimento' => now()->toDateString(),
                    'data_rimozione' => null,
                    'giorni_esposizione' => null,
                ]
            );

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
        $this->selectedProdottoFinito = null;
        $this->selectedItemType = null;
        $this->prezzo_vetrina = '';
        $this->testo_vetrina = '';
        $this->posizione = '';
        $this->ripiano = '';
        $this->descrizione_esterno = '';
        $this->categoria_merceologica_id_esterno = '';
        $this->sede_id_esterno = '';
        $this->foto_principale_esterno = '';
        $this->materiale_esterno = '';
        $this->titolo_esterno = '';
        $this->caratura_esterno = '';
        $this->colore_esterno = '';
        $this->peso_lordo_esterno = '';
        $this->peso_netto_esterno = '';
        $this->prezzo_acquisto_esterno = '';
        $this->prezzo_fornitore_esterno = '';
        $this->note_esterno = '';
        $this->resetErrorBag();
    }

    private function getAddRules(): array
    {
        if ($this->addMode === 'esterno') {
            return [
                'descrizione_esterno' => 'required|string|max:255',
                'categoria_merceologica_id_esterno' => 'nullable|exists:categorie_merceologiche,id',
                'sede_id_esterno' => 'nullable|exists:sedi,id',
                'foto_principale_esterno' => 'nullable|string|max:255',
                'materiale_esterno' => 'nullable|string|max:100',
                'titolo_esterno' => 'nullable|string|max:50',
                'caratura_esterno' => 'nullable|string|max:50',
                'colore_esterno' => 'nullable|string|max:50',
                'peso_lordo_esterno' => 'nullable|numeric|min:0',
                'peso_netto_esterno' => 'nullable|numeric|min:0',
                'prezzo_acquisto_esterno' => 'nullable|numeric|min:0',
                'prezzo_fornitore_esterno' => 'nullable|numeric|min:0',
                'note_esterno' => 'nullable|string|max:1000',
                'prezzo_vetrina' => 'required|string|max:50',
                'testo_vetrina' => 'required|string|max:500',
                'posizione' => 'nullable|integer|min:0',
                'ripiano' => 'nullable|string|max:50',
            ];
        }

        return [
            'selectedItemType' => 'required|in:articolo,pf',
            'selectedArticolo' => 'required_if:selectedItemType,articolo',
            'selectedProdottoFinito' => 'required_if:selectedItemType,pf',
            'prezzo_vetrina' => 'required|string|max:50',
            'testo_vetrina' => 'required|string|max:500',
            'posizione' => 'nullable|integer|min:0',
            'ripiano' => 'nullable|string|max:50',
        ];
    }

    public function updateOrdine(array $orderedIds): void
    {
        if (empty($orderedIds)) {
            return;
        }

        \DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                ArticoloVetrina::where('id', $id)
                    ->where('vetrina_id', $this->vetrina->id)
                    ->update(['posizione' => $index + 1]);
            }
        });
    }

    public function render()
    {
        // Articoli attualmente in vetrina
        $hasDescrizioneEsterno = Schema::hasColumn('articoli_vetrine', 'descrizione_esterno');
        $hasTestoVetrina = Schema::hasColumn('articoli_vetrine', 'testo_vetrina');

        $articoliInVetrina = ArticoloVetrina::with([
            'articolo.categoriaMerceologica',
            'articolo.sede',
            'prodottoFinito.categoriaMerceologica.sede',
            'prodottoFinito.componentiArticoli.articolo',
            'categoriaMerceologica',
            'sede',
        ])
            ->where('vetrina_id', $this->vetrina->id)
            ->whereNull('data_rimozione')
            ->when($this->search, function ($query) use ($hasDescrizioneEsterno, $hasTestoVetrina) {
                $term = $this->search;
                $query->where(function ($q) use ($term, $hasDescrizioneEsterno, $hasTestoVetrina) {
                    $q->whereHas('articolo', function ($sub) use ($term) {
                        $sub->where('codice', 'like', '%' . $term . '%')
                            ->orWhere('descrizione', 'like', '%' . $term . '%');
                    })
                    ->orWhereHas('prodottoFinito', function ($sub) use ($term) {
                        $sub->where('codice', 'like', '%' . $term . '%')
                            ->orWhere('descrizione', 'like', '%' . $term . '%');
                    })
                    ->when($hasDescrizioneEsterno, function ($q) use ($term) {
                        $q->orWhere('descrizione_esterno', 'like', '%' . $term . '%');
                    })
                    ->when($hasTestoVetrina, function ($q) use ($term) {
                        $q->orWhere('testo_vetrina', 'like', '%' . $term . '%');
                    });
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
            $articoliDisponibili = Articolo::with(['categoriaMerceologica', 'sede', 'giacenza', 'contoDepositoCorrente'])
                ->where('stato_articolo', '!=', 'scaricato')
                ->whereHas('giacenza', function ($query) {
                    $query->where('quantita_residua', '>', 0);
                })
                ->when($this->vetrina->sede_id, function ($query) {
                    $sedeId = $this->vetrina->sede_id;
                    $query->where(function ($q) use ($sedeId) {
                        $q->where('sede_id', $sedeId)
                          ->orWhereHas('contoDepositoCorrente', function ($sub) use ($sedeId) {
                              $sub->where('sede_destinataria_id', $sedeId);
                          });
                    });
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
            $prodottiFinitiDisponibili = ProdottoFinito::with(['categoriaMerceologica.sede', 'componentiArticoli.articolo', 'contoDepositoCorrente'])
                ->where('stato', 'completato')
                ->when($this->vetrina->sede_id, function ($query) {
                    $sedeId = $this->vetrina->sede_id;
                    $query->where(function ($q) use ($sedeId) {
                        $q->whereHas('categoriaMerceologica', function ($sub) use ($sedeId) {
                            $sub->where('sede_id', $sedeId);
                        })
                        ->orWhereHas('contoDepositoCorrente', function ($sub) use ($sedeId) {
                            $sub->where('sede_destinataria_id', $sedeId);
                        });
                    });
                })
                ->whereNotExists(function ($query) {
                    $query->select(\DB::raw(1))
                          ->from('articoli_vetrine')
                          ->whereColumn('articoli_vetrine.prodotto_finito_id', 'prodotti_finiti.id')
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

        $categorieDisponibili = CategoriaMerceologica::withoutGlobalScope('user_sede')
            ->orderBy('nome')
            ->get();

        $sediDisponibili = Sede::orderBy('nome')->get();

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
            'categorieDisponibili' => $categorieDisponibili,
            'sediDisponibili' => $sediDisponibili,
        ]);
    }
}
