<?php

namespace App\Http\Livewire;

use App\Models\Articolo;
use App\Models\Fornitore;
use Illuminate\Support\Facades\DB;
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
    public $sortField = 'ragione_sociale';
    public $sortDirection = 'asc';

    // Modali
    public $showModal = false;
    public $showDeleteModal = false;
    public $showRiassegnaModal = false;
    public $modalMode = 'create';
    public $fornitoreSelezionatoId = null;
    public $fornitoreOrigineId = null;
    public $fornitoreOrigineNome = '';
    public $fornitoreDestinazioneId = '';
    public $articoliPreview = [];

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
        'sortField' => ['except' => 'ragione_sociale'],
        'sortDirection' => ['except' => 'asc'],
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

    public function sortBy(string $field): void
    {
        $allowed = ['codice', 'ragione_sociale', 'partita_iva', 'citta', 'attivo', 'updated_at', 'ddt_count', 'fatture_count', 'articoli_count', 'prezzi_count'];
        if (!in_array($field, $allowed, true)) {
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

    public function getFornitoriProperty()
    {
        $query = Fornitore::query()->withCount(['ddt', 'fatture', 'articoli', 'prezzi']);

        $search = trim((string) $this->search);
        if ($search !== '') {
            $query->where(function ($q) {
                $term = '%' . trim((string) $this->search) . '%';
                $q->where('codice', 'like', $term)
                    ->orWhere('ragione_sociale', 'like', $term)
                    ->orWhere('partita_iva', 'like', $term)
                    ->orWhere('codice_fiscale', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('pec', 'like', $term)
                    ->orWhere('telefono', 'like', $term)
                    ->orWhere('citta', 'like', $term);
            });
        }

        if ($this->filtroAttivo !== '') {
            $query->where('attivo', $this->filtroAttivo === 'si');
        }

        return $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderBy('id', 'desc')
            ->paginate(15);
    }

    public function exportCsv()
    {
        $query = Fornitore::query();

        $search = trim((string) $this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where('codice', 'like', $term)
                    ->orWhere('ragione_sociale', 'like', $term)
                    ->orWhere('partita_iva', 'like', $term)
                    ->orWhere('codice_fiscale', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('pec', 'like', $term)
                    ->orWhere('telefono', 'like', $term)
                    ->orWhere('citta', 'like', $term);
            });
        }

        if ($this->filtroAttivo !== '') {
            $query->where('attivo', $this->filtroAttivo === 'si');
        }

        $fornitori = $query
            ->withCount(['ddt', 'fatture', 'articoli', 'prezzi'])
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderBy('id', 'desc')
            ->get([
                'codice',
                'ragione_sociale',
                'partita_iva',
                'codice_fiscale',
                'indirizzo',
                'citta',
                'provincia',
                'cap',
                'nazione',
                'telefono',
                'email',
                'pec',
                'attivo',
            ]);

        $filename = 'fornitori_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ];

        return response()->streamDownload(function () use ($fornitori) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Codice',
                'Ragione sociale',
                'Partita IVA',
                'Codice fiscale',
                'Indirizzo',
                'Citta',
                'Provincia',
                'CAP',
                'Nazione',
                'Telefono',
                'Email',
                'PEC',
                'DDT collegati',
                'Fatture collegate',
                'Articoli collegati',
                'Prezzi collegati',
                'Attivo',
            ], ';');

            foreach ($fornitori as $f) {
                fputcsv($out, [
                    $f->codice,
                    $f->ragione_sociale,
                    $f->partita_iva,
                    $f->codice_fiscale,
                    $f->indirizzo,
                    $f->citta,
                    $f->provincia,
                    $f->cap,
                    $f->nazione,
                    $f->telefono,
                    $f->email,
                    $f->pec,
                    $f->ddt_count,
                    $f->fatture_count,
                    $f->articoli_count,
                    $f->prezzi_count,
                    $f->attivo ? 'SI' : 'NO',
                ], ';');
            }

            fclose($out);
        }, $filename, $headers);
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

    public function getFornitoriDestinazioneProperty()
    {
        $query = Fornitore::query()->orderBy('ragione_sociale');
        if ($this->fornitoreOrigineId) {
            $query->where('id', '!=', $this->fornitoreOrigineId);
        }
        return $query->get(['id', 'codice', 'ragione_sociale', 'attivo']);
    }

    public function apriModalRiassegnaArticoli(int $fornitoreId): void
    {
        $fornitore = Fornitore::findOrFail($fornitoreId);
        $this->fornitoreOrigineId = $fornitore->id;
        $this->fornitoreOrigineNome = $fornitore->ragione_sociale;
        $this->fornitoreDestinazioneId = '';

        $this->articoliPreview = Articolo::query()
            ->where('fornitore_id', $fornitore->id)
            ->orderBy('codice')
            ->limit(30)
            ->get(['id', 'codice', 'descrizione'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'codice' => $a->codice,
                'descrizione' => $a->descrizione,
            ])
            ->toArray();

        $this->showRiassegnaModal = true;
    }

    public function chiudiModalRiassegnaArticoli(): void
    {
        $this->showRiassegnaModal = false;
        $this->fornitoreOrigineId = null;
        $this->fornitoreOrigineNome = '';
        $this->fornitoreDestinazioneId = '';
        $this->articoliPreview = [];
        $this->resetValidation();
    }

    public function confermaRiassegnaArticoli(): void
    {
        $this->validate([
            'fornitoreDestinazioneId' => 'required|exists:fornitori,id',
        ], [
            'fornitoreDestinazioneId.required' => 'Seleziona il fornitore di destinazione.',
        ]);

        if (!$this->fornitoreOrigineId) {
            session()->flash('error', 'Fornitore di origine non valido.');
            return;
        }

        if ((int) $this->fornitoreDestinazioneId === (int) $this->fornitoreOrigineId) {
            session()->flash('error', 'Il fornitore di destinazione deve essere diverso da quello di origine.');
            return;
        }

        $spostati = 0;
        DB::transaction(function () use (&$spostati) {
            $spostati = Articolo::query()
                ->where('fornitore_id', $this->fornitoreOrigineId)
                ->update(['fornitore_id' => (int) $this->fornitoreDestinazioneId]);
        });

        if ($spostati === 0) {
            session()->flash('error', 'Nessun articolo da spostare per questo fornitore.');
            return;
        }

        $dest = Fornitore::find($this->fornitoreDestinazioneId);
        $nomeDest = $dest?->ragione_sociale ?? ('#' . $this->fornitoreDestinazioneId);
        session()->flash('message', "✅ Spostati {$spostati} articoli su fornitore {$nomeDest}");
        $this->chiudiModalRiassegnaArticoli();
    }

    public function elimina(): void
    {
        $fornitore = Fornitore::query()
            ->withCount(['articoli', 'prezzi', 'ddt', 'fatture'])
            ->findOrFail($this->fornitoreSelezionatoId);

        $hasRelazioni = ((int) $fornitore->articoli_count) > 0
            || ((int) $fornitore->prezzi_count) > 0
            || ((int) $fornitore->ddt_count) > 0
            || ((int) $fornitore->fatture_count) > 0;

        if ($hasRelazioni) {
            $msg = sprintf(
                '❌ Impossibile eliminare: DDT=%d, Fatture=%d, Articoli=%d, Prezzi=%d.',
                (int) $fornitore->ddt_count,
                (int) $fornitore->fatture_count,
                (int) $fornitore->articoli_count,
                (int) $fornitore->prezzi_count
            );
            session()->flash('error', $msg);
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

