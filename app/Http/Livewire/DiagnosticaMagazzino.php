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

    // --- Filtro fornitore ---
    public string $fornitoreSearch = '';

    // --- Colonne selezionabili per export ---
    public array $colonneSelezionate = [
        'codice', 'descrizione', 'mag', 'categoria', 'sede',
        'qta_residua', 'qta', 'stato_articolo',
        'fornitore', 'referenza_doc', 'prezzo_carico',
        'num_documento', 'data_documento',
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
        'fornitore'        => 'Fornitore',
        'referenza_doc'    => 'Referenza doc. carico',
        'referenza'        => 'Referenza (JSON)',
        'prezzo_carico'    => 'Prezzo carico (doc)',
        'num_documento'    => 'N. Documento carico',
        'data_documento'   => 'Data documento carico',
        'materiale'        => 'Materiale',
        'colore'           => 'Colore',
        'titolo'           => 'Titolo',
        'caratura'         => 'Caratura',
        'numero_seriale'   => 'N. Seriale',
        'ean'              => 'EAN',
        'modello'          => 'Modello',
        'prezzo_acquisto'  => 'Prezzo acquisto',
        'prezzo_fornitore' => 'Prezzo fornitore',
        'costo_unitario'   => 'Costo unitario (giacenza)',
        'tipo_carico'      => 'Tipo carico',
        'data_carico'      => 'Data carico (articolo)',
        'scaffale'         => 'Scaffale',
        'posizione'        => 'Posizione',
        'magazzino_logico' => 'Mag. logico',
        'deleted_at'       => 'Eliminato il',
        'created_at'       => 'Creato il',
        'note'             => 'Note',
    ];

    public int $perPage = 50;

    public string $sortBy = 'a.codice';
    public string $sortDir = 'asc';

    public array $anomalieDisponibili = [
        ''                     => '— Nessuna anomalia —',
        'qta_zero_non_deleted' => 'Qta=0 ma non eliminati',
        'qta_alta'             => 'Qta residua > 1',
        'deleted_con_qta'      => 'Eliminati con qta > 0',
        'senza_giacenza'       => 'Senza record giacenza',
        'senza_fornitore'      => 'Senza fornitore (nessun documento)',
        'senza_carico_dettagli' => 'Senza carico_dettagli',
        'cat_mag_mismatch'     => 'Categoria/Mag discordanti',
        'senza_referenza'      => 'Senza referenza (JSON)',
        'senza_seriale'        => 'Senza numero seriale',
    ];

    // Colonne ordinabili: chiave UI => colonna SQL (whitelist per sicurezza)
    private const SORT_COLUMNS = [
        'codice'           => 'a.codice',
        'mag'              => 'mag',
        'descrizione'      => 'a.descrizione',
        'categoria'        => 'cm.codice',
        'sede'             => 's.nome',
        'qta'              => 'g.quantita',
        'qta_residua'      => 'g.quantita_residua',
        'stato_articolo'   => 'a.stato_articolo',
        'stato'            => 'a.stato',
        'materiale'        => 'a.materiale',
        'colore'           => 'a.colore',
        'numero_seriale'   => 'a.numero_seriale',
        'ean'              => 'a.ean',
        'modello'          => 'a.modello',
        'prezzo_acquisto'  => 'a.prezzo_acquisto',
        'prezzo_fornitore' => 'a.prezzo_fornitore',
        'costo_unitario'   => 'g.costo_unitario',
        'tipo_carico'      => 'a.tipo_carico',
        'data_carico'      => 'a.data_carico',
        'scaffale'         => 'g.scaffale',
        'magazzino_logico' => 'a.magazzino_logico',
        'deleted_at'       => 'a.deleted_at',
        'created_at'       => 'a.created_at',
        'fornitore'        => 'fornitore_nome',
        'referenza_doc'    => 'referenza_doc',
        'prezzo_carico'    => 'prezzo_carico',
        'num_documento'    => 'num_documento',
        'data_documento'   => 'data_documento',
    ];

    protected $queryString = [
        'search'           => ['except' => ''],
        'magazzino'        => ['except' => ''],
        'anomalia'         => ['except' => ''],
        'categoriaId'      => ['except' => ''],
        'qtaMin'           => ['except' => ''],
        'qtaMax'           => ['except' => ''],
        'statoArticolo'    => ['except' => ''],
        'includiEliminati' => ['except' => false],
        'soloEliminati'    => ['except' => false],
        'sortBy'           => ['except' => 'a.codice'],
        'sortDir'          => ['except' => 'asc'],
        'fornitoreSearch'  => ['except' => ''],
    ];

    public function updatedSearch()           { $this->resetPage(); }
    public function updatedMagazzino()        { $this->resetPage(); }
    public function updatedAnomalia()         { $this->resetPage(); }
    public function updatedCategoriaId()      { $this->resetPage(); }
    public function updatedQtaMin()           { $this->resetPage(); }
    public function updatedQtaMax()           { $this->resetPage(); }
    public function updatedStatoArticolo()    { $this->resetPage(); }
    public function updatedIncludiEliminati() { $this->resetPage(); }
    public function updatedSoloEliminati()    { $this->resetPage(); }
    public function updatedFornitoreSearch()  { $this->resetPage(); }

    public static function colSql(string $col): string
    {
        return self::SORT_COLUMNS[$col] ?? 'a.codice';
    }

    public function ordinaPer(string $col): void
    {
        if (!array_key_exists($col, self::SORT_COLUMNS)) {
            return;
        }
        if ($this->sortBy === (self::SORT_COLUMNS[$col] ?? '')) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = self::SORT_COLUMNS[$col];
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

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
        // Subquery: prende UN solo carico_dettaglio per articolo (il più recente)
        // e risolve subito ddt+fattura per avere fornitore_id, num doc, data doc, prezzo
        $cdSub = DB::table('carico_dettagli as cd')
            ->leftJoin('ddt as d', 'd.id', '=', 'cd.ddt_id')
            ->leftJoin('fatture as fat', 'fat.id', '=', 'cd.fattura_id')
            ->select(
                'cd.articolo_id',
                DB::raw('MAX(cd.id) as cd_id_max'),
            )
            ->whereNotNull('cd.articolo_id')
            ->groupBy('cd.articolo_id');

        $query = DB::table('articoli as a')
            ->leftJoin('giacenze as g', 'g.articolo_id', '=', 'a.id')
            ->leftJoin('categorie_merceologiche as cm', 'cm.id', '=', 'a.categoria_merceologica_id')
            ->leftJoin('sedi as s', 's.id', '=', 'a.sede_id')
            // Join al carico_dettaglio più recente per questo articolo
            ->leftJoinSub($cdSub, 'cd_last', 'cd_last.articolo_id', '=', 'a.id')
            ->leftJoin('carico_dettagli as cd', 'cd.id', '=', 'cd_last.cd_id_max')
            ->leftJoin('ddt as d', 'd.id', '=', 'cd.ddt_id')
            ->leftJoin('fatture as fat', 'fat.id', '=', 'cd.fattura_id')
            // Fallback: documento da articoli.numero_documento_carico (migrazione senza carico_dettagli)
            ->leftJoin(DB::raw('(SELECT numero, MIN(id) AS id FROM ddt WHERE numero IS NOT NULL AND TRIM(numero) != \'\' GROUP BY numero) d_pick'), function ($join) {
                $join->on('d_pick.numero', '=', DB::raw('TRIM(a.numero_documento_carico)'));
            })
            ->leftJoin('ddt as d_art', 'd_art.id', '=', 'd_pick.id')
            ->leftJoin(DB::raw('(SELECT numero, MIN(id) AS id FROM fatture WHERE numero IS NOT NULL AND TRIM(numero) != \'\' GROUP BY numero) fat_pick'), function ($join) {
                $join->on('fat_pick.numero', '=', DB::raw('TRIM(a.numero_documento_carico)'));
            })
            ->leftJoin('fatture as fat_art', 'fat_art.id', '=', 'fat_pick.id')
            ->leftJoin('fornitori as f_cd', 'f_cd.id', '=', DB::raw('COALESCE(d.fornitore_id, fat.fornitore_id)'))
            ->leftJoin('fornitori as f_ddt_art', 'f_ddt_art.id', '=', 'd_art.fornitore_id')
            ->leftJoin('fornitori as f_fat_art', 'f_fat_art.id', '=', 'fat_art.fornitore_id')
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
                // Dati documento: prima carico_dettagli, poi match su numero_documento_carico
                DB::raw("COALESCE(
                    f_cd.ragione_sociale,
                    CASE WHEN a.tipo_carico = 'fattura' THEN f_fat_art.ragione_sociale ELSE f_ddt_art.ragione_sociale END,
                    f_ddt_art.ragione_sociale,
                    f_fat_art.ragione_sociale
                ) as fornitore_nome"),
                DB::raw('cd.id IS NULL as senza_carico_dettagli'),
                'cd.referenza_fornitore as referenza_doc',
                'cd.prezzo_unitario as prezzo_carico',
                DB::raw("COALESCE(
                    d.numero, fat.numero,
                    CASE WHEN a.tipo_carico = 'fattura' THEN fat_art.numero ELSE d_art.numero END,
                    d_art.numero, fat_art.numero,
                    a.numero_documento_carico
                ) as num_documento"),
                DB::raw("COALESCE(
                    d.data_documento, fat.data_documento,
                    CASE WHEN a.tipo_carico = 'fattura' THEN fat_art.data_documento ELSE d_art.data_documento END,
                    d_art.data_documento, fat_art.data_documento,
                    a.data_carico
                ) as data_documento"),
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

        // Ricerca generale
        if ($this->search !== '') {
            $q = '%' . $this->search . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('a.codice', 'like', $q)
                    ->orWhere('a.descrizione', 'like', $q)
                    ->orWhere('a.numero_seriale', 'like', $q)
                    ->orWhere('a.ean', 'like', $q)
                    ->orWhere('a.modello', 'like', $q)
                    ->orWhereRaw("COALESCE(f_cd.ragione_sociale, f_ddt_art.ragione_sociale, f_fat_art.ragione_sociale) LIKE ?", [$q])
                    ->orWhere('cd.referenza_fornitore', 'like', $q);
            });
        }

        // Filtro fornitore dedicato
        if ($this->fornitoreSearch !== '') {
            $q = '%' . $this->fornitoreSearch . '%';
            $query->whereRaw(
                "COALESCE(f_cd.ragione_sociale, CASE WHEN a.tipo_carico = 'fattura' THEN f_fat_art.ragione_sociale ELSE f_ddt_art.ragione_sociale END, f_ddt_art.ragione_sociale, f_fat_art.ragione_sociale) LIKE ?",
                [$q]
            );
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
            'senza_fornitore' => $query->whereRaw(
                "COALESCE(f_cd.ragione_sociale, f_ddt_art.ragione_sociale, f_fat_art.ragione_sociale) IS NULL"
            ),
            'senza_carico_dettagli' => $query->whereNull('cd.id'),
            default => null,
        };

        // Ordina: 'mag' è una colonna calcolata, usa la raw expression
        if ($this->sortBy === 'mag') {
            $query->orderByRaw("CAST(SUBSTRING_INDEX(a.codice, '-', 1) AS UNSIGNED) " . ($this->sortDir === 'asc' ? 'ASC' : 'DESC'));
        } else {
            $col = in_array($this->sortBy, self::SORT_COLUMNS)
                ? $this->sortBy
                : 'a.codice';
            $query->orderBy($col, $this->sortDir);
        }

        return $query;
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
        ) + ['sortBy' => $this->sortBy, 'sortDir' => $this->sortDir]);
    }
}
