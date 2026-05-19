<?php

namespace App\Http\Livewire;

use App\Exports\DiagnosticaExport;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.vertical', ['title' => 'Diagnostica Magazzino'])]
class DiagnosticaMagazzino extends Component
{
    use WithPagination;

    // --- Filtri base ---
    public string $search = '';
    public string $magazzino = '';         // prefisso codice: 1,2,3...
    public string $categoriaId = '';
    public string $sedeId = '';
    public string $statoArticolo = '';
    public string $tipoCarico = '';

    // --- Filtri quantità ---
    public string $qtaMin = '';
    public string $qtaMax = '';

    // --- Filtri anomalie (preset) ---
    public string $anomalia = '';
    // anomalie disponibili:
    // qta_zero_non_deleted, qta_alta (>1), senza_giacenza,
    // deleted_con_qta, cat_mag_mismatch, senza_referenza, senza_seriale

    // --- Filtri date ---
    public string $dataCaricoDa = '';
    public string $dataCaricoA = '';

    // --- Mostra/nascondi eliminati ---
    public bool $includiEliminati = false;
    public bool $soloEliminati = false;

    // --- Colonne selezionabili per export ---
    public array $colonneSelezionate = [
        'codice', 'descrizione', 'mag', 'categoria', 'sede',
        'qta_residua', 'qta', 'stato_articolo',
    ];

    public array $tutteLeColonne = [
        'codice'           => 'Codice',
        'descrizione'      => 'Descrizione',
        'mag'              => 'Magazzino',
        'categoria'        => 'Categoria',
        'sede'             => 'Sede',
        'qta'              => 'Qta originale',
        'qta_residua'      => 'Qta residua',
        'stato_articolo'   => 'Stato articolo',
        'stato'            => 'Stato',
        'materiale'        => 'Materiale',
        'colore'           => 'Colore',
        'titolo'           => 'Titolo',
        'caratura'         => 'Caratura',
        'numero_seriale'   => 'N. Seriale',
        'ean'              => 'EAN',
        'modello'          => 'Modello',
        'referenza'        => 'Referenza',
        'prezzo_acquisto'  => 'Prezzo acquisto',
        'prezzo_fornitore' => 'Prezzo fornitore',
        'costo_unitario'   => 'Costo unitario (giacenza)',
        'tipo_carico'      => 'Tipo carico',
        'data_carico'      => 'Data carico',
        'scaffale'         => 'Scaffale',
        'posizione'        => 'Posizione',
        'magazzino_logico' => 'Mag. logico',
        'deleted_at'       => 'Eliminato il',
        'created_at'       => 'Creato il',
        'note'             => 'Note',
    ];

    public int $perPage = 50;

    public array $anomalieDisponibili = [
        ''                   => '— Nessuna anomalia —',
        'qta_zero_non_deleted' => 'Qta=0 ma non eliminati',
        'qta_alta'           => 'Qta residua > 1',
        'deleted_con_qta'    => 'Eliminati con qta > 0',
        'senza_giacenza'     => 'Senza record giacenza',
        'cat_mag_mismatch'   => 'Categoria/Magazzino discordanti',
        'senza_referenza'    => 'Senza referenza (JSON)',
        'senza_seriale'      => 'Senza numero seriale',
    ];

    protected $queryString = [
        'search'         => ['except' => ''],
        'magazzino'      => ['except' => ''],
        'anomalia'       => ['except' => ''],
        'categoriaId'    => ['except' => ''],
        'qtaMin'         => ['except' => ''],
        'qtaMax'         => ['except' => ''],
        'statoArticolo'  => ['except' => ''],
        'includiEliminati' => ['except' => false],
        'soloEliminati'  => ['except' => false],
    ];

    public function updatedSearch()        { $this->resetPage(); }
    public function updatedMagazzino()     { $this->resetPage(); }
    public function updatedAnomalia()      { $this->resetPage(); }
    public function updatedCategoriaId()   { $this->resetPage(); }
    public function updatedQtaMin()        { $this->resetPage(); }
    public function updatedQtaMax()        { $this->resetPage(); }
    public function updatedStatoArticolo() { $this->resetPage(); }
    public function updatedIncludiEliminati() { $this->resetPage(); }
    public function updatedSoloEliminati() { $this->resetPage(); }

    public function resetFiltri(): void
    {
        $this->reset([
            'search', 'magazzino', 'categoriaId', 'sedeId',
            'statoArticolo', 'tipoCarico', 'qtaMin', 'qtaMax',
            'anomalia', 'dataCaricoDa', 'dataCaricoA',
            'includiEliminati', 'soloEliminati',
        ]);
        $this->resetPage();
    }

    public function toggleColonna(string $col): void
    {
        if (in_array($col, $this->colonneSelezionate)) {
            $this->colonneSelezionate = array_values(
                array_filter($this->colonneSelezionate, fn($c) => $c !== $col)
            );
        } else {
            $this->colonneSelezionate[] = $col;
        }
    }

    public function exportExcel()
    {
        $rows = $this->buildQuery()->get();
        return Excel::download(
            new DiagnosticaExport($rows, $this->colonneSelezionate, $this->tutteLeColonne),
            'diagnostica_magazzino_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    private function buildQuery()
    {
        $query = DB::table('articoli as a')
            ->leftJoin('giacenze as g', 'g.articolo_id', '=', 'a.id')
            ->leftJoin('categorie_merceologiche as cm', 'cm.id', '=', 'a.categoria_merceologica_id')
            ->leftJoin('sedi as s', 's.id', '=', 'a.sede_id')
            ->select(
                'a.id',
                'a.codice',
                DB::raw("SUBSTRING_INDEX(a.codice, '-', 1) as mag"),
                'a.descrizione',
                'a.categoria_merceologica_id',
                'cm.nome as categoria_nome',
                'cm.codice as categoria_codice',
                'a.magazzino_logico',
                's.nome as sede_nome',
                'a.stato_articolo',
                'a.stato',
                'a.materiale',
                'a.colore',
                'a.titolo',
                'a.caratura',
                'a.numero_seriale',
                'a.ean',
                'a.modello',
                'a.prezzo_acquisto',
                'a.prezzo_fornitore',
                'a.tipo_carico',
                'a.data_carico',
                'a.caratteristiche',
                'a.note',
                'a.created_at',
                'a.deleted_at',
                'g.quantita',
                'g.quantita_residua',
                'g.costo_unitario',
                'g.scaffale',
                'g.posizione',
            );

        // Soft delete handling
        if ($this->soloEliminati) {
            $query->whereNotNull('a.deleted_at');
        } elseif ($this->includiEliminati) {
            // mostra tutto
        } else {
            $query->whereNull('a.deleted_at');
        }

        // Filtro magazzino (prefisso codice)
        if ($this->magazzino !== '') {
            $query->where(DB::raw("SUBSTRING_INDEX(a.codice, '-', 1)"), $this->magazzino);
        }

        // Filtro categoria
        if ($this->categoriaId !== '') {
            $query->where('a.categoria_merceologica_id', $this->categoriaId);
        }

        // Filtro sede
        if ($this->sedeId !== '') {
            $query->where('a.sede_id', $this->sedeId);
        }

        // Filtro stato articolo
        if ($this->statoArticolo !== '') {
            $query->where('a.stato_articolo', $this->statoArticolo);
        }

        // Filtro tipo carico
        if ($this->tipoCarico !== '') {
            $query->where('a.tipo_carico', $this->tipoCarico);
        }

        // Filtro qta
        if ($this->qtaMin !== '') {
            $query->where('g.quantita_residua', '>=', (int) $this->qtaMin);
        }
        if ($this->qtaMax !== '') {
            $query->where('g.quantita_residua', '<=', (int) $this->qtaMax);
        }

        // Filtro date carico
        if ($this->dataCaricoDa !== '') {
            $query->where('a.data_carico', '>=', $this->dataCaricoDa);
        }
        if ($this->dataCaricoA !== '') {
            $query->where('a.data_carico', '<=', $this->dataCaricoA);
        }

        // Ricerca
        if ($this->search !== '') {
            $q = '%' . $this->search . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('a.codice', 'like', $q)
                    ->orWhere('a.descrizione', 'like', $q)
                    ->orWhere('a.numero_seriale', 'like', $q)
                    ->orWhere('a.ean', 'like', $q)
                    ->orWhere('a.modello', 'like', $q);
            });
        }

        // Anomalie
        match ($this->anomalia) {
            'qta_zero_non_deleted' => $query
                ->whereNull('a.deleted_at')
                ->where(fn($q) => $q->whereNull('g.quantita_residua')->orWhere('g.quantita_residua', '<=', 0)),
            'qta_alta' => $query->where('g.quantita_residua', '>', 1),
            'deleted_con_qta' => $query
                ->whereNotNull('a.deleted_at')
                ->where('g.quantita_residua', '>', 0),
            'senza_giacenza' => $query->whereNull('g.id'),
            'cat_mag_mismatch' => $query->whereRaw(
                "SUBSTRING_INDEX(a.codice, '-', 1) != REGEXP_REPLACE(cm.codice, '[^0-9]', '')"
            ),
            'senza_referenza' => $query->where(function ($q) {
                $q->whereNull('a.caratteristiche')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(a.caratteristiche, '$.referenza')) IS NULL")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(a.caratteristiche, '$.referenza')) = ''");
            }),
            'senza_seriale' => $query->where(function ($q) {
                $q->whereNull('a.numero_seriale')->orWhere('a.numero_seriale', '');
            }),
            default => null,
        };

        return $query->orderBy('a.codice');
    }

    public function getCountProperty(): int
    {
        return $this->buildQuery()->count();
    }

    public function getCategorie()
    {
        return DB::table('categorie_merceologiche')->orderBy('id')->get(['id', 'nome', 'codice']);
    }

    public function getSedi()
    {
        return DB::table('sedi')->orderBy('nome')->get(['id', 'nome']);
    }

    public function render()
    {
        $articoli = $this->buildQuery()->paginate($this->perPage);
        $categorie = $this->getCategorie();
        $sedi = $this->getSedi();
        $count = $this->count;

        return view('livewire.diagnostica-magazzino', compact(
            'articoli', 'categorie', 'sedi', 'count'
        ));
    }
}
