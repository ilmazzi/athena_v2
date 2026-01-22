<?php

namespace App\Http\Livewire;

use App\Models\Ddt;
use App\Models\Fattura;
use App\Models\Fornitore;
use App\Models\CategoriaMerceologica;
use App\Models\Sede;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.vertical', ['title' => 'Documenti di Acquisto'])]
class DocumentiAcquistoTable extends Component
{
    use WithPagination;

    // Filtri
    public $search = '';
    public $tipoDocumento = ''; // 'ddt', 'fattura', ''
    public $tipoCarico = ''; // 'ocr', 'manuale', ''
    public $fornitoreFilter = '';
    public $sedeFilter = '';
    public $categoriaFilter = '';
    public $statoFilter = '';
    public $dataFrom = '';
    public $dataTo = '';
    public $nascondiVuoti = true; // Nascondi DDT senza articoli
    
    // Paginazione e ordinamento
    public $perPage = 25;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    
    // Modal edit
    public $editingDocId = null;
    public $editingDocTipo = null;
    public $editForm = [
        'numero_documento' => '',
        'data_documento' => '',
        'fornitore_id' => '',
        'partita_iva' => '',
        'importo_totale' => '',
        'note' => '',
    ];
    
    protected $queryString = [
        'search' => ['except' => ''],
        'tipoDocumento' => ['except' => ''],
        'tipoCarico' => ['except' => ''],
        'fornitoreFilter' => ['except' => ''],
        'sedeFilter' => ['except' => ''],
        'categoriaFilter' => ['except' => ''],
        'statoFilter' => ['except' => ''],
        'dataFrom' => ['except' => ''],
        'dataTo' => ['except' => ''],
        'nascondiVuoti' => ['except' => true],
    ];

    private function applyUniqueDocumentScope($query, string $table): void
    {
        $idsQuery = (clone $query)
            ->selectRaw('MIN(id) as id')
            ->groupBy('numero', 'data_documento', 'fornitore_id');

        $query->whereIn($table . '.id', $idsQuery);
    }

    private function countDistinctDocuments($query): int
    {
        $count = (clone $query)
            ->selectRaw("COUNT(DISTINCT CONCAT(numero, '|', data_documento, '|', COALESCE(fornitore_id, 0))) as aggregate")
            ->value('aggregate');

        return (int) $count;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTipoDocumento()
    {
        $this->resetPage();
    }

    public function updatingTipoCarico()
    {
        $this->resetPage();
    }

    public function updatingFornitoreFilter()
    {
        $this->resetPage();
    }

    public function updatingSedeFilter()
    {
        $this->resetPage();
    }

    public function updatingCategoriaFilter()
    {
        $this->resetPage();
    }

    public function updatingStatoFilter()
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
        $this->tipoDocumento = '';
        $this->tipoCarico = '';
        $this->fornitoreFilter = '';
        $this->sedeFilter = '';
        $this->categoriaFilter = '';
        $this->statoFilter = '';
        $this->dataFrom = '';
        $this->dataTo = '';
        $this->nascondiVuoti = true;
        $this->resetPage();
    }

    public function editDocument($tipo, $id)
    {
        $this->editingDocTipo = $tipo;
        $this->editingDocId = $id;
        
        if ($tipo === 'ddt') {
            $doc = Ddt::findOrFail($id);
        } else {
            $doc = Fattura::findOrFail($id);
        }
        
        $this->editForm = [
            'numero_documento' => $doc->numero,
            'data_documento' => $doc->data_documento,
            'fornitore_id' => $doc->fornitore_id ?? '',
            'partita_iva' => $doc->partita_iva ?? '',
            'importo_totale' => $tipo === 'fattura' ? $doc->totale : '',
            'note' => $doc->note ?? '',
        ];
        
        $this->dispatch('open-edit-modal');
    }

    public function updateDocument()
    {
        $this->validate([
            'editForm.numero_documento' => 'required|string|max:50',
            'editForm.data_documento' => 'required|date',
            'editForm.fornitore_id' => 'nullable|exists:fornitori,id',
            'editForm.partita_iva' => 'nullable|string|max:20',
            'editForm.importo_totale' => 'nullable|numeric|min:0',
            'editForm.note' => 'nullable|string',
        ]);
        
        DB::beginTransaction();
        try {
            if ($this->editingDocTipo === 'ddt') {
                $documento = Ddt::findOrFail($this->editingDocId);
                $documento->update([
                    'numero' => $this->editForm['numero_documento'],
                    'data_documento' => $this->editForm['data_documento'],
                    'anno' => date('Y', strtotime($this->editForm['data_documento'])),
                    'fornitore_id' => $this->editForm['fornitore_id'] ?: null,
                    'note' => $this->editForm['note'],
                ]);
            } else {
                $documento = Fattura::findOrFail($this->editingDocId);
                $documento->update([
                    'numero' => $this->editForm['numero_documento'],
                    'data_documento' => $this->editForm['data_documento'],
                    'anno' => date('Y', strtotime($this->editForm['data_documento'])),
                    'fornitore_id' => $this->editForm['fornitore_id'] ?: null,
                    'totale' => $this->editForm['importo_totale'],
                    'partita_iva' => $this->editForm['partita_iva'],
                    'note' => $this->editForm['note'],
                ]);
            }
            
            DB::commit();
            
            $this->dispatch('close-edit-modal');
            $this->dispatch('show-toast',
                type: 'success',
                message: 'Documento aggiornato con successo!'
            );
            
            $this->editingDocId = null;
            $this->editingDocTipo = null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('show-toast',
                type: 'error',
                message: 'Errore durante l\'aggiornamento: ' . $e->getMessage()
            );
        }
    }

    public function render()
    {
        $ddtHasAllegatoPath = Schema::hasColumn('ddt', 'allegato_path');
        $fattureHasAllegatoPath = Schema::hasColumn('fatture', 'allegato_path');

        // Recupera DDT (includi anche legacy con allegato_path se esiste)
        $ddtQuery = Ddt::with(['fornitore', 'userCarico', 'categoria', 'sede', 'ocrDocument'])
            ->where(function ($q) use ($ddtHasAllegatoPath) {
                $q->whereNotNull('tipo_carico');
                if ($ddtHasAllegatoPath) {
                    $q->orWhereNotNull('allegato_path');
                }
            });
        
        // Recupera Fatture (includi anche legacy con allegato_path se esiste)
        $fattureQuery = Fattura::with(['fornitore', 'categoria', 'sede', 'ocrDocument'])
            ->where(function ($q) use ($fattureHasAllegatoPath) {
                $q->whereNotNull('tipo_carico');
                if ($fattureHasAllegatoPath) {
                    $q->orWhereNotNull('allegato_path');
                }
            });
        
        // Applica filtri comuni
        if ($this->search) {
            $searchRaw = trim($this->search);
            $searchLike = '%' . $searchRaw . '%';
            $searchNormalized = preg_replace('/[\s\/-]+/', '', $searchRaw);
            $searchNormalizedLike = '%' . $searchNormalized . '%';
            $searchLastSegment = null;
            if (str_contains($searchRaw, '/') || str_contains($searchRaw, '\\')) {
                $segments = preg_split('/[\/\\\\]+/', $searchRaw);
                $searchLastSegment = trim(end($segments)) ?: null;
            }
            $searchYear = null;
            if (preg_match('/\b(20\d{2})\b/', $searchRaw, $matches)) {
                $searchYear = (int) $matches[1];
            }

            $ddtQuery->where(function($q) use ($searchLike, $searchNormalizedLike, $searchLastSegment, $searchYear) {
                $q->where('numero', 'like', $searchLike)
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(numero, ' ', ''), '/', ''), '-', '') like ?", [$searchNormalizedLike])
                  ->orWhereHas('fornitore', function($subQ) use ($searchLike) {
                      $subQ->where('ragione_sociale', 'like', $searchLike);
                  });
                if ($searchLastSegment) {
                    $q->orWhere('numero', 'like', '%' . $searchLastSegment . '%');
                }
                if ($searchYear && $searchLastSegment) {
                    $q->orWhere(function ($sub) use ($searchYear, $searchLastSegment) {
                        $sub->where('anno', $searchYear)
                            ->where('numero', 'like', '%' . $searchLastSegment . '%');
                    });
                }
            });
            $fattureQuery->where(function($q) use ($searchLike, $searchNormalizedLike, $searchLastSegment, $searchYear) {
                $q->where('numero', 'like', $searchLike)
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(numero, ' ', ''), '/', ''), '-', '') like ?", [$searchNormalizedLike])
                  ->orWhereHas('fornitore', function($subQ) use ($searchLike) {
                      $subQ->where('ragione_sociale', 'like', $searchLike);
                  });
                if ($searchLastSegment) {
                    $q->orWhere('numero', 'like', '%' . $searchLastSegment . '%');
                }
                if ($searchYear && $searchLastSegment) {
                    $q->orWhere(function ($sub) use ($searchYear, $searchLastSegment) {
                        $sub->where('anno', $searchYear)
                            ->where('numero', 'like', '%' . $searchLastSegment . '%');
                    });
                }
            });
        }
        
        if ($this->tipoCarico) {
            $ddtQuery->where('tipo_carico', $this->tipoCarico);
            $fattureQuery->where('tipo_carico', $this->tipoCarico);
        }
        
        if ($this->fornitoreFilter) {
            $ddtQuery->where('fornitore_id', $this->fornitoreFilter);
            $fattureQuery->where('fornitore_id', $this->fornitoreFilter);
        }
        
        if ($this->sedeFilter) {
            $ddtQuery->where('sede_id', $this->sedeFilter);
            $fattureQuery->where('sede_id', $this->sedeFilter);
        }
        
        if ($this->categoriaFilter) {
            $ddtQuery->where('categoria_merceologica_id', $this->categoriaFilter);
            $fattureQuery->where('categoria_merceologica_id', $this->categoriaFilter);
        }
        
        if ($this->statoFilter) {
            $ddtQuery->where('stato', $this->statoFilter);
            $fattureQuery->where('stato', $this->statoFilter);
        }
        
        if ($this->dataFrom) {
            $ddtQuery->where('data_documento', '>=', $this->dataFrom);
            $fattureQuery->where('data_documento', '>=', $this->dataFrom);
        }
        
        if ($this->dataTo) {
            $ddtQuery->where('data_documento', '<=', $this->dataTo);
            $fattureQuery->where('data_documento', '<=', $this->dataTo);
        }
        
        // Nascondi documenti vuoti (senza articoli)
        if ($this->nascondiVuoti) {
            $ddtQuery->where(function ($q) use ($ddtHasAllegatoPath) {
                $q->where('numero_articoli', '>', 0);
                // Per legacy: includi documenti con allegato o senza tipo_carico
                if ($ddtHasAllegatoPath) {
                    $q->orWhereNotNull('allegato_path');
                } else {
                    $q->orWhereNull('tipo_carico');
                }
            });
            $fattureQuery->where(function ($q) use ($fattureHasAllegatoPath) {
                $q->where('numero_articoli', '>', 0);
                if ($fattureHasAllegatoPath) {
                    $q->orWhereNotNull('allegato_path');
                } else {
                    $q->orWhereNull('tipo_carico');
                }
            });
        }

        // Rimuovi duplicati (stesso numero/data/fornitore)
        $this->applyUniqueDocumentScope($ddtQuery, 'ddt');
        $this->applyUniqueDocumentScope($fattureQuery, 'fatture');
        
        // Se filtra per tipo documento, carica solo quello
        if ($this->tipoDocumento === 'ddt') {
            $ddt = $ddtQuery->orderBy($this->sortField, $this->sortDirection)
                ->paginate($this->perPage);
            $ddt->getCollection()->transform(function($doc) {
                $doc->tipo_documento = 'ddt';
                return $doc;
            });
            $documenti = $ddt;
        } elseif ($this->tipoDocumento === 'fattura') {
            $fatture = $fattureQuery->orderBy($this->sortField, $this->sortDirection)
                ->paginate($this->perPage);
            $fatture->getCollection()->transform(function($doc) {
                $doc->tipo_documento = 'fattura';
                return $doc;
            });
            $documenti = $fatture;
        } else {
            // Unisci entrambi
            $ddt = $ddtQuery->orderBy($this->sortField, $this->sortDirection)->get();
            $fatture = $fattureQuery->orderBy($this->sortField, $this->sortDirection)->get();
            
            $ddt = $ddt->map(function($doc) {
                $doc->tipo_documento = 'ddt';
                return $doc;
            });
            
            $fatture = $fatture->map(function($doc) {
                $doc->tipo_documento = 'fattura';
                return $doc;
            });
            
            $allDocumenti = $ddt->concat($fatture)
                ->unique(function ($doc) {
                    return $doc->tipo_documento . '-' . $doc->id;
                })
                ->values();
            
            // Ordina la collezione unita
            if ($this->sortDirection === 'asc') {
                $allDocumenti = $allDocumenti->sortBy($this->sortField);
            } else {
                $allDocumenti = $allDocumenti->sortByDesc($this->sortField);
            }
            
            // Paginazione manuale per Livewire 3
            $currentPage = $this->getPage();
            $documenti = new \Illuminate\Pagination\LengthAwarePaginator(
                $allDocumenti->forPage($currentPage, $this->perPage)->values(),
                $allDocumenti->count(),
                $this->perPage,
                $currentPage,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                    'pageName' => 'page',
                ]
            );
        }
        
        // Statistiche
        $statsDdtQuery = Ddt::query()->whereNotNull('tipo_carico');
        if ($ddtHasAllegatoPath) {
            $statsDdtQuery->orWhereNotNull('allegato_path');
        }
        $statsFattureQuery = Fattura::query()->whereNotNull('tipo_carico');
        if ($fattureHasAllegatoPath) {
            $statsFattureQuery->orWhereNotNull('allegato_path');
        }
        $stats = [
            'ddt' => $this->countDistinctDocuments($statsDdtQuery),
            'fatture' => $this->countDistinctDocuments($statsFattureQuery),
            'ocr' => $this->countDistinctDocuments(Ddt::where('tipo_carico', 'ocr'))
                + $this->countDistinctDocuments(Fattura::where('tipo_carico', 'ocr')),
            'manuali' => $this->countDistinctDocuments(Ddt::where('tipo_carico', 'manuale'))
                + $this->countDistinctDocuments(Fattura::where('tipo_carico', 'manuale')),
        ];
        $stats['totali'] = $stats['ddt'] + $stats['fatture'];
        
        // Opzioni per filtri
        $fornitori = Fornitore::where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale']);
        
        $categorie = CategoriaMerceologica::where('attivo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codice']);
        
        $sedi = Sede::orderBy('nome')->get(['id', 'nome']);
        
        return view('livewire.documenti-acquisto-table', compact('documenti', 'stats', 'fornitori', 'categorie', 'sedi'));
    }
}

