<?php

namespace App\Http\Livewire;

use App\Models\Vetrina;
use App\Models\Articolo;
use App\Models\ArticoloVetrina;
use App\Models\CategoriaMerceologica;
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
    public $selectedArticoloVetrina = null;
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

    // Modal modifica articolo
    public $showEditModal = false;
    public $editingArticoloVetrina = null;
    public $edit_articolo_vetrina_id = null;
    public $edit_tipo_articolo = 'interno';
    public $edit_prezzo_vetrina = '';
    public $edit_testo_vetrina = '';
    public $edit_posizione = '';
    public $edit_ripiano = '';
    public $edit_descrizione_esterno = '';
    public $edit_categoria_merceologica_id_esterno = '';
    public $edit_sede_id_esterno = '';
    public $edit_foto_principale_esterno = '';
    public $edit_materiale_esterno = '';
    public $edit_titolo_esterno = '';
    public $edit_caratura_esterno = '';
    public $edit_colore_esterno = '';
    public $edit_peso_lordo_esterno = '';
    public $edit_peso_netto_esterno = '';
    public $edit_prezzo_acquisto_esterno = '';
    public $edit_prezzo_fornitore_esterno = '';
    public $edit_note_esterno = '';

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
        $this->selectedArticoloVetrina = null;
        $this->resetValidation();
    }

    public function selectArticolo($articoloId)
    {
        $this->addMode = 'interno';
        $this->selectedArticolo = Articolo::findOrFail($articoloId);
        $this->selectedArticoloVetrina = ArticoloVetrina::with('vetrina')
            ->where('articolo_id', $articoloId)
            ->whereNull('data_rimozione')
            ->first();
        // Pulisci ricerca per non filtrare la tabella dopo la selezione
        $this->search = '';
        
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
                $pesoLordo = $this->normalizeNumericInput($this->peso_lordo_esterno);
                $pesoNetto = $this->normalizeNumericInput($this->peso_netto_esterno);
                $prezzoAcquisto = $this->normalizeNumericInput($this->prezzo_acquisto_esterno);
                $prezzoFornitore = $this->normalizeNumericInput($this->prezzo_fornitore_esterno);

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
                    'peso_lordo_esterno' => $pesoLordo,
                    'peso_netto_esterno' => $pesoNetto,
                    'prezzo_acquisto_esterno' => $prezzoAcquisto,
                    'prezzo_fornitore_esterno' => $prezzoFornitore,
                    'note_esterno' => $this->note_esterno,
                    'prezzo_vetrina' => $this->prezzo_vetrina,
                    'testo_vetrina' => $this->testo_vetrina,
                    'posizione' => $this->posizione ?: 0,
                    'ripiano' => $this->ripiano,
                    'data_inserimento' => now()->toDateString(),
                ]);

                session()->flash('success', 'Articolo NC aggiunto alla vetrina');
            $this->search = '';
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

            $vetrinaCorrente = ArticoloVetrina::where('articolo_id', $this->selectedArticolo->id)
                ->whereNull('data_rimozione')
                ->first();

            if ($vetrinaCorrente && $vetrinaCorrente->vetrina_id !== $this->vetrina->id) {
                $dataInserimento = \Carbon\Carbon::parse($vetrinaCorrente->data_inserimento);
                $giorniEsposizione = $dataInserimento->diffInDays(now());
                $vetrinaCorrente->update([
                    'data_rimozione' => now()->toDateString(),
                    'giorni_esposizione' => $giorniEsposizione,
                ]);
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

            if ($vetrinaCorrente && $vetrinaCorrente->vetrina_id !== $this->vetrina->id) {
                $nomeVetrina = $vetrinaCorrente->vetrina->nome ?? $vetrinaCorrente->vetrina_id;
                session()->flash('warning', "Articolo {$this->selectedArticolo->codice} spostato da vetrina {$nomeVetrina} a {$this->vetrina->nome}");
            } else {
                session()->flash('success', "Articolo {$this->selectedArticolo->codice} aggiunto alla vetrina");
            }
            $this->search = '';
            $this->closeAddModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'aggiunta: ' . $e->getMessage());
        }
    }

    protected function normalizeNumericInput($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $normalized = str_replace(['.', ','], ['', '.'], $value);
        if (!is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
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

    public function openEditModal($articoloVetrinaId)
    {
        $articoloVetrina = ArticoloVetrina::with(['articolo', 'prodottoFinito', 'categoriaMerceologica', 'sede'])
            ->where('vetrina_id', $this->vetrina->id)
            ->whereNull('data_rimozione')
            ->findOrFail($articoloVetrinaId);

        $this->editingArticoloVetrina = $articoloVetrina;
        $this->fillEditForm($articoloVetrina);
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function updateArticoloVetrina()
    {
        $this->validate($this->getEditRules());

        try {
            $articoloVetrina = ArticoloVetrina::with('articolo')
                ->where('vetrina_id', $this->vetrina->id)
                ->whereNull('data_rimozione')
                ->findOrFail($this->edit_articolo_vetrina_id);

            $payload = [
                'prezzo_vetrina' => $this->edit_prezzo_vetrina,
                'testo_vetrina' => $this->edit_testo_vetrina,
                'posizione' => $this->edit_posizione === '' ? null : (int) $this->edit_posizione,
                'ripiano' => $this->edit_ripiano ?: null,
            ];

            if ($articoloVetrina->is_esterno) {
                $payload = array_merge($payload, [
                    'descrizione_esterno' => $this->edit_descrizione_esterno,
                    'categoria_merceologica_id' => $this->edit_categoria_merceologica_id_esterno ?: null,
                    'sede_id' => $this->edit_sede_id_esterno ?: null,
                    'foto_principale_esterno' => $this->edit_foto_principale_esterno ?: null,
                    'materiale_esterno' => $this->edit_materiale_esterno ?: null,
                    'titolo_esterno' => $this->edit_titolo_esterno ?: null,
                    'caratura_esterno' => $this->edit_caratura_esterno ?: null,
                    'colore_esterno' => $this->edit_colore_esterno ?: null,
                    'peso_lordo_esterno' => $this->normalizeNumericInput($this->edit_peso_lordo_esterno),
                    'peso_netto_esterno' => $this->normalizeNumericInput($this->edit_peso_netto_esterno),
                    'prezzo_acquisto_esterno' => $this->normalizeNumericInput($this->edit_prezzo_acquisto_esterno),
                    'prezzo_fornitore_esterno' => $this->normalizeNumericInput($this->edit_prezzo_fornitore_esterno),
                    'note_esterno' => $this->edit_note_esterno ?: null,
                ]);
            }

            $articoloVetrina->update($payload);

            if ($articoloVetrina->articolo) {
                $articoloVetrina->articolo->update([
                    'ultimo_testo_vetrina' => $this->edit_testo_vetrina,
                ]);
            }

            session()->flash('success', 'Articolo in vetrina aggiornato');
            $this->closeEditModal();
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

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingArticoloVetrina = null;
        $this->resetEditForm();
    }

    private function resetAddForm()
    {
        $this->selectedArticolo = null;
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

    private function resetEditForm(): void
    {
        $this->edit_articolo_vetrina_id = null;
        $this->edit_tipo_articolo = 'interno';
        $this->edit_prezzo_vetrina = '';
        $this->edit_testo_vetrina = '';
        $this->edit_posizione = '';
        $this->edit_ripiano = '';
        $this->edit_descrizione_esterno = '';
        $this->edit_categoria_merceologica_id_esterno = '';
        $this->edit_sede_id_esterno = '';
        $this->edit_foto_principale_esterno = '';
        $this->edit_materiale_esterno = '';
        $this->edit_titolo_esterno = '';
        $this->edit_caratura_esterno = '';
        $this->edit_colore_esterno = '';
        $this->edit_peso_lordo_esterno = '';
        $this->edit_peso_netto_esterno = '';
        $this->edit_prezzo_acquisto_esterno = '';
        $this->edit_prezzo_fornitore_esterno = '';
        $this->edit_note_esterno = '';
        $this->resetErrorBag();
    }

    private function fillEditForm(ArticoloVetrina $articoloVetrina): void
    {
        $this->resetEditForm();

        $this->edit_articolo_vetrina_id = $articoloVetrina->id;
        $this->edit_tipo_articolo = $articoloVetrina->tipo_articolo ?? 'interno';
        $this->edit_prezzo_vetrina = (string) ($articoloVetrina->prezzo_vetrina ?? '');
        $this->edit_testo_vetrina = (string) ($articoloVetrina->testo_vetrina ?? '');
        $this->edit_posizione = $articoloVetrina->posizione ?? '';
        $this->edit_ripiano = (string) ($articoloVetrina->ripiano ?? '');
        $this->edit_descrizione_esterno = (string) ($articoloVetrina->descrizione_esterno ?? '');
        $this->edit_categoria_merceologica_id_esterno = $articoloVetrina->categoria_merceologica_id ?? '';
        $this->edit_sede_id_esterno = $articoloVetrina->sede_id ?? '';
        $this->edit_foto_principale_esterno = (string) ($articoloVetrina->foto_principale_esterno ?? '');
        $this->edit_materiale_esterno = (string) ($articoloVetrina->materiale_esterno ?? '');
        $this->edit_titolo_esterno = (string) ($articoloVetrina->titolo_esterno ?? '');
        $this->edit_caratura_esterno = (string) ($articoloVetrina->caratura_esterno ?? '');
        $this->edit_colore_esterno = (string) ($articoloVetrina->colore_esterno ?? '');
        $this->edit_peso_lordo_esterno = $articoloVetrina->peso_lordo_esterno !== null ? (string) $articoloVetrina->peso_lordo_esterno : '';
        $this->edit_peso_netto_esterno = $articoloVetrina->peso_netto_esterno !== null ? (string) $articoloVetrina->peso_netto_esterno : '';
        $this->edit_prezzo_acquisto_esterno = $articoloVetrina->prezzo_acquisto_esterno !== null ? (string) $articoloVetrina->prezzo_acquisto_esterno : '';
        $this->edit_prezzo_fornitore_esterno = $articoloVetrina->prezzo_fornitore_esterno !== null ? (string) $articoloVetrina->prezzo_fornitore_esterno : '';
        $this->edit_note_esterno = (string) ($articoloVetrina->note_esterno ?? '');
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
            'selectedArticolo' => 'required',
            'prezzo_vetrina' => 'required|string|max:50',
            'testo_vetrina' => 'required|string|max:500',
            'posizione' => 'nullable|integer|min:0',
            'ripiano' => 'nullable|string|max:50',
        ];
    }

    private function getEditRules(): array
    {
        $baseRules = [
            'edit_articolo_vetrina_id' => 'required|exists:articoli_vetrine,id',
            'edit_prezzo_vetrina' => 'required|string|max:50',
            'edit_testo_vetrina' => 'required|string|max:500',
            'edit_posizione' => 'nullable|integer|min:0',
            'edit_ripiano' => 'nullable|string|max:50',
        ];

        if ($this->edit_tipo_articolo !== 'esterno') {
            return $baseRules;
        }

        return array_merge($baseRules, [
            'edit_descrizione_esterno' => 'required|string|max:255',
            'edit_categoria_merceologica_id_esterno' => 'nullable|exists:categorie_merceologiche,id',
            'edit_sede_id_esterno' => 'nullable|exists:sedi,id',
            'edit_foto_principale_esterno' => 'nullable|string|max:255',
            'edit_materiale_esterno' => 'nullable|string|max:100',
            'edit_titolo_esterno' => 'nullable|string|max:50',
            'edit_caratura_esterno' => 'nullable|string|max:50',
            'edit_colore_esterno' => 'nullable|string|max:50',
            'edit_peso_lordo_esterno' => 'nullable|numeric|min:0',
            'edit_peso_netto_esterno' => 'nullable|numeric|min:0',
            'edit_prezzo_acquisto_esterno' => 'nullable|numeric|min:0',
            'edit_prezzo_fornitore_esterno' => 'nullable|numeric|min:0',
            'edit_note_esterno' => 'nullable|string|max:1000',
        ]);
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
            'articolo.prodottoFinito' => function ($query) {
                $query->withTrashed()->with([
                    'componentiArticoli' => function ($componentiQuery) {
                        $componentiQuery->with([
                            'articolo' => function ($articoloQuery) {
                                $articoloQuery->withoutGlobalScopes();
                            },
                        ]);
                    },
                ]);
            },
            'prodottoFinito.categoriaMerceologica.sede',
            'prodottoFinito' => function ($query) {
                $query->withTrashed();
            },
            'prodottoFinito.componentiArticoli' => function ($componentiQuery) {
                $componentiQuery->with([
                    'articolo' => function ($articoloQuery) {
                        $articoloQuery->withoutGlobalScopes();
                    },
                ]);
            },
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

        $pfIds = $articoliInVetrina->getCollection()
            ->map(function ($item) {
                if ($item->prodotto_finito_id) {
                    return $item->prodotto_finito_id;
                }
                return $item->articolo?->prodotto_finito_id;
            })
            ->filter()
            ->unique()
            ->values();

        $componentiByPfId = collect();
        if ($pfIds->isNotEmpty()) {
            $componentiByPfId = \DB::table('componenti_prodotto as cp')
                ->leftJoin('articoli as a', 'a.id', '=', 'cp.articolo_id')
                ->whereIn('cp.prodotto_finito_id', $pfIds)
                ->select([
                    'cp.prodotto_finito_id',
                    'cp.articolo_id',
                    'a.codice as articolo_codice',
                    'a.descrizione as articolo_descrizione',
                ])
                ->get()
                ->groupBy('prodotto_finito_id');
        }

        // Articoli disponibili per aggiunta (inclusi PF, esclusi scaricati e già in vetrina)
        $articoliDisponibili = [];
        if ($this->showAddModal) {
            // Articoli normali disponibili
            $articoliDisponibili = Articolo::with(['categoriaMerceologica', 'sede', 'giacenza', 'contoDepositoCorrente'])
                ->leftJoin('articoli_vetrine as av', function ($join) {
                    $join->on('av.articolo_id', '=', 'articoli.id')
                        ->whereNull('av.data_rimozione');
                })
                ->leftJoin('vetrine as v', 'v.id', '=', 'av.vetrina_id')
                ->addSelect([
                    'articoli.*',
                    'av.vetrina_id as vetrina_corrente_id',
                    'v.nome as vetrina_corrente_nome',
                ])
                ->where('stato_articolo', '!=', 'scaricato')
                ->whereHas('giacenza', function ($query) {
                    $query->where('quantita_residua', '>', 0);
                })
                ->when($this->vetrina->sede_id, function ($query) {
                    $sedeId = $this->vetrina->sede_id;
                    $query->where(function ($q) use ($sedeId) {
                        $q->where('articoli.sede_id', $sedeId)
                          ->orWhereHas('giacenza', function ($subQ) use ($sedeId) {
                              $subQ->where('sede_id', $sedeId);
                          })
                          ->orWhereHas('contoDepositoCorrente', function ($sub) use ($sedeId) {
                              $sub->where('sede_destinataria_id', $sedeId);
                          });
                    });
                })
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('articoli.codice', 'like', '%' . $this->search . '%')
                          ->orWhere('articoli.descrizione', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('articoli.codice')
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
            'altreVetrine' => $altreVetrine,
            'categorieDisponibili' => $categorieDisponibili,
            'sediDisponibili' => $sediDisponibili,
            'componentiByPfId' => $componentiByPfId,
        ]);
    }
}
