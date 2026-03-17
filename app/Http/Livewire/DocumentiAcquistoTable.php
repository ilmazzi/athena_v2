<?php

namespace App\Http\Livewire;

use App\Models\Ddt;
use App\Models\Fattura;
use App\Models\Fornitore;
use App\Models\CategoriaMerceologica;
use App\Models\Sede;
use App\Models\Stampante;
use App\Models\CaricoDettaglio;
use App\Services\EtichettaService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    // Modal stampa etichette
    public $showPrintModal = false;
    public $printDocId = null;
    public $printDocTipo = null;
    public $printRows = [];
    public $stampantiDisponibili = [];
    public $stampanteId = '';
    public $codicePrezzoTipo = 'G';
    public $codicePrezzoSuffix = '';
    public $etichetteTotali = 0;
    
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

    public function openPrintModal(string $tipo, int $id): void
    {
        $this->printDocTipo = $tipo;
        $this->printDocId = $id;
        $this->stampanteId = '';
        $this->codicePrezzoTipo = 'G';
        $this->codicePrezzoSuffix = '';

        $righe = CaricoDettaglio::with([
            'articolo' => function ($query) {
                $query->withoutGlobalScopes()->withTrashed();
            }
        ])
            ->when($tipo === 'ddt', function ($query) use ($id) {
                $query->where('ddt_id', $id);
            }, function ($query) use ($id) {
                $query->where('fattura_id', $id);
            })
            ->orderBy('id')
            ->get();

        $this->printRows = $righe->map(function ($riga) {
            $prezzoUnitario = $riga->prezzo_unitario
                ?? ($riga->articolo->prezzo_fornitore ?? $riga->articolo->prezzo_acquisto ?? null);

            return [
                'articolo_id' => $riga->articolo_id,
                'referenza' => $riga->referenza_fornitore ?? ($riga->articolo?->caratteristiche['referenza'] ?? ''),
                'codice' => $riga->articolo->codice ?? '',
                'descrizione' => $riga->descrizione ?? ($riga->articolo->descrizione ?? ''),
                'quantita' => (int) ($riga->quantita ?? 1),
                'prezzo_unitario' => $prezzoUnitario,
                'prezzo_etichetta' => $this->formatEuroPrezzoEtichetta($prezzoUnitario),
            ];
        })->toArray();

        $this->etichetteTotali = 0;
        $this->showPrintModal = true;
    }

    public function closePrintModal(): void
    {
        $this->showPrintModal = false;
        $this->printDocId = null;
        $this->printDocTipo = null;
        $this->printRows = [];
        $this->etichetteTotali = 0;
    }

    public function applicaCodicePrezzoTutti(): void
    {
        foreach ($this->printRows as $index => $row) {
            $this->printRows[$index]['prezzo_etichetta'] = $this->buildCodicePrezzo(
                $row['prezzo_unitario'] ?? null,
                $this->codicePrezzoTipo,
                $this->codicePrezzoSuffix
            );
        }
        $this->etichetteTotali = $this->calcolaEtichetteTotali();
    }

    public function applicaCodicePrezzoRiga(int $index): void
    {
        if (!isset($this->printRows[$index])) {
            return;
        }
        $row = $this->printRows[$index];
        $this->printRows[$index]['prezzo_etichetta'] = $this->buildCodicePrezzo(
            $row['prezzo_unitario'] ?? null,
            $this->codicePrezzoTipo,
            $this->codicePrezzoSuffix
        );
        $this->etichetteTotali = $this->calcolaEtichetteTotali();
    }

    public function ricalcolaEtichetteTotali(): void
    {
        $this->etichetteTotali = $this->calcolaEtichetteTotali();
    }

    public function stampaEtichetteDocumento(): void
    {
        $service = app(EtichettaService::class);
        $success = 0;
        $errors = 0;

        foreach ($this->printRows as $row) {
            $prezzoEtichetta = trim((string) ($row['prezzo_etichetta'] ?? ''));
            if ($prezzoEtichetta === '') {
                continue;
            }

            $articolo = \App\Models\Articolo::find($row['articolo_id']);
            if (!$articolo) {
                $errors++;
                continue;
            }

            $quantita = max(1, (int) ($row['quantita'] ?? 1));
            $formatoPrezzo = $this->guessFormatoPrezzo($prezzoEtichetta);
            $stampante = $this->stampanteId
                ? Stampante::find($this->stampanteId)
                : $service->getStampanteDefault($articolo);

            if (!$stampante) {
                $errors++;
                continue;
            }

            for ($i = 0; $i < $quantita; $i++) {
                try {
                    $zpl = $service->generaEtichettaZPLConPrezzo(
                        $articolo,
                        $prezzoEtichetta,
                        $formatoPrezzo,
                        $stampante->id,
                        'standard'
                    );
                    $ok = $service->inviaAllaStampante($stampante->ip_address, $stampante->port, $zpl);
                    $ok ? $success++ : $errors++;
                } catch (\Exception $e) {
                    Log::warning('Errore stampa etichetta documento', [
                        'articolo_id' => $articolo->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }
        }

        $this->etichetteTotali = $this->calcolaEtichetteTotali();

        if ($errors > 0) {
            $this->dispatch('show-toast',
                type: 'warning',
                message: "Etichette stampate: {$success}, errori: {$errors}"
            );
        } else {
            $this->dispatch('show-toast',
                type: 'success',
                message: "Etichette stampate: {$success}"
            );
        }
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

        $this->stampantiDisponibili = Stampante::where('attiva', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'modello']);
        
        return view('livewire.documenti-acquisto-table', compact('documenti', 'stats', 'fornitori', 'categorie', 'sedi'));
    }

    protected function buildCodicePrezzo($costoUnitario, string $tipo, string $suffix): string
    {
        $prezzo = $this->normalizePrice($costoUnitario);
        if ($prezzo === null) {
            return '';
        }
        $valore = rtrim(rtrim(number_format($prezzo, 2, '.', ''), '0'), '.');
        $tipo = strtoupper(trim($tipo));
        $suffix = trim((string) $suffix);

        return 'X' . $valore . ($tipo === 'P' ? 'P' : 'G') . $suffix;
    }

    protected function normalizePrice($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(['.', ','], ['', '.'], (string) $value);
        if (!is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }

    protected function guessFormatoPrezzo(string $prezzo): string
    {
        $numeric = preg_replace('/[^\d,.]/', '', $prezzo);
        $numeric = str_replace(',', '.', $numeric);
        return is_numeric($numeric) ? 'euro' : 'codificato';
    }

    protected function calcolaEtichetteTotali(): int
    {
        $totale = 0;
        foreach ($this->printRows as $row) {
            $prezzoEtichetta = trim((string) ($row['prezzo_etichetta'] ?? ''));
            if ($prezzoEtichetta === '') {
                continue;
            }
            $totale += max(1, (int) ($row['quantita'] ?? 1));
        }
        return $totale;
    }

    protected function formatEuroPrezzoEtichetta($value): string
    {
        $prezzo = $this->normalizePrice($value);
        if ($prezzo === null) {
            return '';
        }

        return number_format($prezzo, 2, ',', '');
    }
}

