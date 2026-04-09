<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\Fattura;
use App\Models\FatturaDettaglio;
use App\Models\Fornitore;
use App\Models\Sede;
use App\Models\CategoriaMerceologica;
use App\Models\ArticoloStoricoCosto;
use App\Exports\StatisticheMagazzinoExport;
use App\Services\MagazzinoLogicoService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * AmministrazioneMagazzinoDashboard - Dashboard amministrativa magazzini
 * 
 * Gestisce:
 * - Visualizzazione articoli giacenti con costi
 * - Identificazione articoli senza costo
 * - Inserimento/modifica fatture acquisto e costi
 * - Valorizzazione magazzino per diverse dimensioni
 * - Statistiche amministrative
 */
class AmministrazioneMagazzinoDashboard extends Component
{
    use WithPagination;

    private const STATISTICHE_CACHE_VERSION = 'v3';

    // Filtri
    public $sedeId = '';
    public $fornitoreId = '';
    public $categoriaId = '';
    public $marcaId = ''; // Per future implementazioni marche
    public $search = '';
    public $dataDocumentoCaricoPrimaDi = '';
    public $filtroContoDeposito = 'tutti'; // 'tutti', 'solo_reale', 'solo_conto_deposito'
    public $soloSenzaCosto = false;
    public $soloGiacenti = true;

    // Modal gestione fattura
    public $showFatturaModal = false;
    public $fatturaSelezionata = null;
    public $articoloSelezionato = null;

    // Form fattura
    public $numeroFattura = '';
    public $dataFattura = '';
    public $fornitoreFatturaId = '';
    public $sedeFatturaId = '';

    // Form articolo fattura
    public $quantitaArticolo = 1;
    public $costoUnitarioArticolo = 0;
    public $articoliFattura = [];

    // Statistiche
    public $viewStatistiche = 'sede'; // 'sede', 'fornitore', 'categoria', 'marca', 'globale'
    public $sortStatisticheField = 'valore'; // Campo per sorting statistiche
    public $sortStatisticheDirection = 'desc'; // Direzione sorting
    public $sortArticoliField = 'codice';
    public $sortArticoliDirection = 'asc';
    
    // Confronto periodi
    public $dataInizio = '';
    public $dataFine = '';
    public $mostraConfronto = false;
    
    // Storico costi
    public $showStoricoCostiModal = false;
    public $articoloStorico = null;
    
    // Selezione multipla articoli
    public $articoliSelezionati = []; // Array di ID articoli selezionati
    
    // Filtro ricerca statistiche
    public $searchStatistiche = ''; // Per filtrare le statistiche
    public $mostraTuttiFornitori = false; // Per limitare i fornitori visibili
    public $limiteFornitori = 10; // Numero massimo di fornitori da mostrare di default
    public $mostraTutteMarche = false; // Per limitare le marche visibili
    public $limiteMarche = 10; // Numero massimo di marche da mostrare di default

    // Cache statistiche
    public $statisticheCachedAt = null;
    

    protected $queryString = [
        'sedeId' => ['except' => ''],
        'fornitoreId' => ['except' => ''],
        'categoriaId' => ['except' => ''],
        'search' => ['except' => ''],
        'dataDocumentoCaricoPrimaDi' => ['except' => ''],
        'filtroContoDeposito' => ['except' => 'tutti'],
        'sortArticoliField' => ['except' => 'codice'],
        'sortArticoliDirection' => ['except' => 'asc'],
        'soloSenzaCosto' => ['except' => false],
        'soloGiacenti' => ['except' => true],
    ];

    public function mount()
    {
        $this->dataFattura = now()->format('Y-m-d');
        $this->dataInizio = now()->subMonth()->format('Y-m-d');
        $this->dataFine = now()->format('Y-m-d');
    }

    // ==========================================
    // COMPUTED PROPERTIES
    // ==========================================

    public function getSediProperty()
    {
        return Sede::where('attivo', true)->orderBy('nome')->get();
    }

    public function getFornitoriProperty()
    {
        return Fornitore::where('attivo', true)->orderBy('ragione_sociale')->get();
    }

    public function getCategorieProperty()
    {
        return CategoriaMerceologica::where('attivo', true)->orderBy('nome')->get();
    }

    public function getArticoliGiacentiProperty()
    {
        $query = Articolo::with([
            'giacenza',
            'categoriaMerceologica',
            'sede',
            'fatturaDettaglio.fattura.fornitore',
            'ddtDettaglio.ddt.fornitore'
        ]);
        $this->applyFiltriArticoli($query);

        $this->applyOrdinamentoArticoli($query);

        return $query->paginate(50);
    }

    public function getStatisticheProperty()
    {
        $cacheKey = $this->getStatisticheCacheKey();
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->statisticheCachedAt = $cached['cached_at'] ?? null;
            return $cached['data'] ?? [];
        }

        $stats = $this->buildStatistichePayload();

        $this->storeStatisticheInCache($cacheKey, $stats);

        return $stats;
    }

    // ==========================================
    // ACTIONS
    // ==========================================

    public function resetFiltri()
    {
        $this->reset(['sedeId', 'fornitoreId', 'categoriaId', 'search', 'dataDocumentoCaricoPrimaDi', 'filtroContoDeposito', 'soloSenzaCosto']);
        $this->soloGiacenti = true;
    }

    public function clearSedeFilter()
    {
        $this->sedeId = '';
    }

    public function clearFornitoreFilter()
    {
        $this->fornitoreId = '';
    }

    public function clearCategoriaFilter()
    {
        $this->categoriaId = '';
        $this->filtroContoDeposito = 'tutti';
    }

    public function clearMarcaFilter()
    {
        $this->marcaId = '';
    }

    public function refreshStatistiche()
    {
        foreach ($this->getStatisticheCacheKeysForCurrentFilters() as $cacheKey) {
            Cache::forget($cacheKey);
        }

        $stats = $this->buildStatistichePayload();
        $this->storeStatisticheInCache($this->getStatisticheCacheKey(), $stats);
    }

    public function ordinaArticoli($campo)
    {
        if ($this->sortArticoliField === $campo) {
            $this->sortArticoliDirection = $this->sortArticoliDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortArticoliField = $campo;
            $this->sortArticoliDirection = in_array($campo, ['quantita', 'costo_unitario', 'valore'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    private function getStatisticheCacheKey(?string $view = null): string
    {
        return 'amministrazione_magazzino_statistiche_' . md5(json_encode([
            'version' => self::STATISTICHE_CACHE_VERSION,
            'viewStatistiche' => $view ?? $this->viewStatistiche,
            'sedeId' => $this->sedeId,
            'fornitoreId' => $this->fornitoreId,
            'categoriaId' => $this->categoriaId,
            'marcaId' => $this->marcaId,
            'search' => $this->search,
            'dataDocumentoCaricoPrimaDi' => $this->dataDocumentoCaricoPrimaDi,
            'filtroContoDeposito' => $this->filtroContoDeposito,
            'soloSenzaCosto' => (bool) $this->soloSenzaCosto,
            'soloGiacenti' => (bool) $this->soloGiacenti,
        ]));
    }

    private function getStatisticheCacheKeysForCurrentFilters(): array
    {
        return collect(['globale', 'sede', 'fornitore', 'categoria', 'marca'])
            ->map(fn ($view) => $this->getStatisticheCacheKey($view))
            ->unique()
            ->values()
            ->all();
    }

    private function storeStatisticheInCache(string $cacheKey, array $stats): void
    {
        $this->statisticheCachedAt = now()->format('d/m/Y H:i:s');

        Cache::put($cacheKey, [
            'cached_at' => $this->statisticheCachedAt,
            'data' => $stats,
        ], now()->addMinutes(15));
    }

    private function buildStatistichePayload(): array
    {
        $globali = $this->getStatisticheGlobali();

        return array_merge($globali, [
            'per_sede' => $this->viewStatistiche === 'sede' ? $this->getStatistichePerSede() : [],
            'per_fornitore' => $this->viewStatistiche === 'fornitore' ? $this->getStatistichePerFornitore() : [],
            'per_categoria' => $this->viewStatistiche === 'categoria' ? $this->getStatistichePerCategoria() : [],
            'per_marca' => $this->viewStatistiche === 'marca' ? $this->getStatistichePerMarca() : [],
        ]);
    }

    private function getStatisticheGlobali(): array
    {
        $row = $this->buildStatisticheBaseQuery($this->shouldApplySedeFilterToLogicalStats())
            ->selectRaw('COUNT(DISTINCT articoli.id) as totale_articoli')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua), 0) as totale_quantita')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(articoli.prezzo_acquisto, 0) > 0 THEN giacenze.quantita_residua * articoli.prezzo_acquisto ELSE 0 END), 0) as totale_valore')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(articoli.prezzo_acquisto, 0) <= 0 THEN 1 ELSE 0 END), 0) as articoli_senza_costo')
            ->first();

        return [
            'totale_articoli' => (int) ($row->totale_articoli ?? 0),
            'totale_valore' => (float) ($row->totale_valore ?? 0),
            'totale_quantita' => (float) ($row->totale_quantita ?? 0),
            'articoli_senza_costo' => (int) ($row->articoli_senza_costo ?? 0),
            'valore_senza_costo' => 0,
        ];
    }

    private function getStatistichePerSede(): array
    {
        $rows = $this->buildStatisticheBaseQuery(false)
            ->leftJoin('sedi', 'sedi.id', '=', 'giacenze.sede_id')
            ->selectRaw("COALESCE(CAST(giacenze.sede_id AS CHAR), 'n/a') as gruppo_id")
            ->selectRaw("COALESCE(sedi.nome, 'N/A') as nome")
            ->selectRaw('COUNT(DISTINCT articoli.id) as articoli')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua), 0) as quantita')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua * COALESCE(articoli.prezzo_acquisto, 0)), 0) as valore')
            ->groupBy('giacenze.sede_id', 'sedi.nome')
            ->get();

        return $this->mapStatisticheRows($rows);
    }

    private function getStatistichePerCategoria(): array
    {
        $gruppoIdSql = $this->getMagazzinoLogicoGroupingSql();
        $nomeSql = $this->getMagazzinoLogicoLabelSql();

        $rows = $this->buildStatisticheBaseQuery($this->shouldApplySedeFilterToLogicalStats())
            ->selectRaw($gruppoIdSql . ' as gruppo_id')
            ->selectRaw($nomeSql . ' as nome')
            ->selectRaw('COUNT(DISTINCT articoli.id) as articoli')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua), 0) as quantita')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua * COALESCE(articoli.prezzo_acquisto, 0)), 0) as valore')
            ->groupBy(DB::raw($gruppoIdSql), DB::raw($nomeSql))
            ->get();

        return $this->mapStatisticheRows($rows);
    }

    private function getStatistichePerFornitore(): array
    {
        $fornitoreIdSql = $this->getFornitoreIdSql();
        $fornitoreNomeSql = $this->getFornitoreNomeSql();

        $rows = $this->buildStatisticheBaseQuery($this->shouldApplySedeFilterToLogicalStats())
            ->selectRaw($fornitoreIdSql . ' as gruppo_id')
            ->selectRaw($fornitoreNomeSql . ' as nome')
            ->selectRaw('COUNT(DISTINCT articoli.id) as articoli')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua), 0) as quantita')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua * COALESCE(articoli.prezzo_acquisto, 0)), 0) as valore')
            ->groupBy(DB::raw($fornitoreIdSql), DB::raw($fornitoreNomeSql))
            ->get();

        return $this->mapStatisticheRows($rows);
    }

    private function getStatistichePerMarca(): array
    {
        $marcaKeySql = $this->getMarcaKeySql();
        $marcaNomeSql = $this->getMarcaNomeSql();

        $rows = $this->buildStatisticheBaseQuery($this->shouldApplySedeFilterToLogicalStats())
            ->selectRaw($marcaKeySql . ' as gruppo_id')
            ->selectRaw($marcaNomeSql . ' as nome')
            ->selectRaw('COUNT(DISTINCT articoli.id) as articoli')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua), 0) as quantita')
            ->selectRaw('COALESCE(SUM(giacenze.quantita_residua * COALESCE(articoli.prezzo_acquisto, 0)), 0) as valore')
            ->groupBy(DB::raw($marcaKeySql), DB::raw($marcaNomeSql))
            ->get();

        return $this->mapStatisticheRows($rows);
    }

    private function mapStatisticheRows($rows): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            $mapped[(string) $row->gruppo_id] = [
                'nome' => $row->nome,
                'articoli' => (int) $row->articoli,
                'quantita' => (float) $row->quantita,
                'valore' => (float) $row->valore,
            ];
        }

        return $mapped;
    }

    private function buildStatisticheBaseQuery(bool $applySedeFilter = true)
    {
        $query = DB::table('articoli')
            ->join('giacenze', 'giacenze.articolo_id', '=', 'articoli.id')
            ->whereNull('articoli.deleted_at');

        if ($this->soloGiacenti) {
            $query->where('giacenze.quantita_residua', '>', 0);
        }

        if ($applySedeFilter && $this->sedeId) {
            $query->where('giacenze.sede_id', $this->sedeId);
        }

        $this->applyCategoriaEContoDepositoFilterToQueryBuilder($query);

        if ($this->soloSenzaCosto) {
            $query->where(function ($q) {
                $q->whereNull('articoli.prezzo_acquisto')
                    ->orWhere('articoli.prezzo_acquisto', 0);
            });
        }

        if ($this->search) {
            $search = trim((string) $this->search);
            $query->where(function ($q) use ($search) {
                $q->where('articoli.codice', 'like', '%' . $search . '%')
                    ->orWhere('articoli.descrizione', 'like', '%' . $search . '%');
            });
        }

        if ($this->fornitoreId !== null && $this->fornitoreId !== '') {
            if ($this->fornitoreId === 'n/a') {
                $query->whereRaw('NOT EXISTS (
                    SELECT 1
                    FROM fatture_dettagli fd
                    INNER JOIN fatture f ON f.id = fd.fattura_id AND f.deleted_at IS NULL
                    WHERE fd.articolo_id = articoli.id
                )')
                ->whereRaw('NOT EXISTS (
                    SELECT 1
                    FROM ddt_dettagli dd
                    INNER JOIN ddt d ON d.id = dd.ddt_id AND d.deleted_at IS NULL
                    WHERE dd.articolo_id = articoli.id
                )');
            } else {
                $fornitoreId = (int) $this->fornitoreId;
                $query->where(function ($q) use ($fornitoreId) {
                    $q->whereRaw('EXISTS (
                        SELECT 1
                        FROM fatture_dettagli fd
                        INNER JOIN fatture f ON f.id = fd.fattura_id AND f.deleted_at IS NULL
                        WHERE fd.articolo_id = articoli.id
                          AND f.fornitore_id = ?
                    )', [$fornitoreId])
                    ->orWhereRaw('EXISTS (
                        SELECT 1
                        FROM ddt_dettagli dd
                        INNER JOIN ddt d ON d.id = dd.ddt_id AND d.deleted_at IS NULL
                        WHERE dd.articolo_id = articoli.id
                          AND d.fornitore_id = ?
                    )', [$fornitoreId]);
                });
            }
        }

        if ($this->marcaId !== null && $this->marcaId !== '') {
            if ($this->marcaId === 'n/a') {
                $query->whereRaw($this->getMarcaKeySql() . " = 'n/a'");
            } else {
                $query->whereRaw($this->getMarcaKeySql() . ' = ?', [strtolower(trim((string) $this->marcaId))]);
            }
        }

        if ($this->dataDocumentoCaricoPrimaDi) {
            $dataLimite = Carbon::parse($this->dataDocumentoCaricoPrimaDi)->format('Y-m-d');

            $query->where(function ($q) use ($dataLimite) {
                $q->whereRaw('EXISTS (
                    SELECT 1
                    FROM fatture_dettagli fd
                    INNER JOIN fatture f ON f.id = fd.fattura_id AND f.deleted_at IS NULL
                    WHERE fd.articolo_id = articoli.id
                      AND DATE(f.data_documento) < ?
                )', [$dataLimite])
                ->orWhereRaw('EXISTS (
                    SELECT 1
                    FROM ddt_dettagli dd
                    INNER JOIN ddt d ON d.id = dd.ddt_id AND d.deleted_at IS NULL
                    WHERE dd.articolo_id = articoli.id
                      AND DATE(d.data_documento) < ?
                )', [$dataLimite])
                ->orWhereRaw('DATE(articoli.data_carico) < ?
                    AND EXISTS (
                        SELECT 1
                        FROM ddt_dettagli dd
                        INNER JOIN ddt d ON d.id = dd.ddt_id AND d.deleted_at IS NULL
                        WHERE dd.articolo_id = articoli.id
                          AND d.numero LIKE ?
                    )', [$dataLimite, 'LEGACY-%']);
            });
        }

        return $query;
    }

    private function shouldApplySedeFilterToLogicalStats(): bool
    {
        // Quando l'utente sta analizzando un magazzino logico specifico, la sede
        // deve restare una chiave di lettura della dislocazione fisica e non
        // alterare il totale del magazzino di appartenenza.
        return !$this->categoriaId;
    }

    private function getFornitoreIdSql(): string
    {
        return "COALESCE(
            CAST((
                SELECT f.fornitore_id
                FROM fatture_dettagli fd
                INNER JOIN fatture f ON f.id = fd.fattura_id AND f.deleted_at IS NULL
                WHERE fd.articolo_id = articoli.id
                ORDER BY f.id ASC
                LIMIT 1
            ) AS CHAR),
            CAST((
                SELECT d.fornitore_id
                FROM ddt_dettagli dd
                INNER JOIN ddt d ON d.id = dd.ddt_id AND d.deleted_at IS NULL
                WHERE dd.articolo_id = articoli.id
                ORDER BY d.id ASC
                LIMIT 1
            ) AS CHAR),
            'n/a'
        )";
    }

    private function getFornitoreNomeSql(): string
    {
        return "COALESCE(
            (
                SELECT fornitori.ragione_sociale
                FROM fatture_dettagli fd
                INNER JOIN fatture f ON f.id = fd.fattura_id AND f.deleted_at IS NULL
                INNER JOIN fornitori ON fornitori.id = f.fornitore_id
                WHERE fd.articolo_id = articoli.id
                ORDER BY f.id ASC
                LIMIT 1
            ),
            (
                SELECT fornitori.ragione_sociale
                FROM ddt_dettagli dd
                INNER JOIN ddt d ON d.id = dd.ddt_id AND d.deleted_at IS NULL
                INNER JOIN fornitori ON fornitori.id = d.fornitore_id
                WHERE dd.articolo_id = articoli.id
                ORDER BY d.id ASC
                LIMIT 1
            ),
            'Senza Fornitore'
        )";
    }

    private function getMarcaKeySql(): string
    {
        return "COALESCE(
            NULLIF(LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.marca')))), ''),
            NULLIF(LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.Marca')))), ''),
            NULLIF(LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.brand')))), ''),
            NULLIF(LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.Brand')))), ''),
            'n/a'
        )";
    }

    private function getMarcaNomeSql(): string
    {
        return "COALESCE(
            NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.marca'))), ''),
            NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.Marca'))), ''),
            NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.brand'))), ''),
            NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(articoli.caratteristiche, '$.Brand'))), ''),
            'Senza Marca'
        )";
    }

    private function applyFiltriArticoli($query): void
    {
        // Deve sempre esistere almeno una giacenza; opzionalmente solo giacenze con residuo > 0
        $query->whereHas('giacenza', function ($q) {
            if ($this->soloGiacenti) {
                $q->where('quantita_residua', '>', 0);
            }
        });

        if ($this->sedeId) {
            $query->whereHas('giacenza', function ($q) {
                $q->where('sede_id', $this->sedeId);
            });
        }

        $this->applyCategoriaEContoDepositoFilterToEloquent($query);

        // Filtro marca (da caratteristiche JSON)
        if ($this->marcaId !== null && $this->marcaId !== '') {
            if ($this->marcaId === 'n/a') {
                // Senza marca: tutte le chiavi marca/brand assenti o vuote
                $query->where(function ($q) {
                    $q->whereNull('caratteristiche')
                        ->orWhere('caratteristiche', '{}')
                        ->orWhere(function ($jsonQ) {
                            $jsonQ->whereRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.marca'))), ''), NULL) IS NULL")
                                ->whereRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.Marca'))), ''), NULL) IS NULL")
                                ->whereRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.brand'))), ''), NULL) IS NULL")
                                ->whereRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.Brand'))), ''), NULL) IS NULL");
                        });
                });
            } else {
                $query->where(function ($q) {
                    $marcaLower = strtolower(trim((string) $this->marcaId));
                    $q->whereRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.marca')))) = ?", [$marcaLower])
                        ->orWhereRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.Marca')))) = ?", [$marcaLower])
                        ->orWhereRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.brand')))) = ?", [$marcaLower])
                        ->orWhereRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.Brand')))) = ?", [$marcaLower]);
                });
            }
        }

        // Filtro fornitore (fonte fattura o DDT)
        if ($this->fornitoreId !== null && $this->fornitoreId !== '') {
            if ($this->fornitoreId === 'n/a') {
                $query->whereDoesntHave('fatturaDettaglio.fattura.fornitore')
                    ->whereDoesntHave('ddtDettaglio.ddt.fornitore');
            } else {
                $query->where(function ($q) {
                    $q->whereHas('fatturaDettaglio.fattura', function ($subQ) {
                        $subQ->where('fornitore_id', $this->fornitoreId);
                    })->orWhereHas('ddtDettaglio.ddt', function ($subQ) {
                        $subQ->where('fornitore_id', $this->fornitoreId);
                    });
                });
            }
        }

        if ($this->dataDocumentoCaricoPrimaDi) {
            $dataLimite = Carbon::parse($this->dataDocumentoCaricoPrimaDi)->format('Y-m-d');

            $query->where(function ($q) use ($dataLimite) {
                $q->whereHas('fatturaDettaglio.fattura', function ($subQ) use ($dataLimite) {
                    $subQ->whereDate('data_documento', '<', $dataLimite);
                })->orWhereHas('ddtDettaglio.ddt', function ($subQ) use ($dataLimite) {
                    $subQ->whereDate('data_documento', '<', $dataLimite);
                })->orWhere(function ($subQ) use ($dataLimite) {
                    // I DDT legacy sintetici possono accorpare articoli con date storiche diverse:
                    // in quel caso il riferimento corretto resta la data_carico dell'articolo.
                    $subQ->whereDate('articoli.data_carico', '<', $dataLimite)
                        ->whereHas('ddtDettaglio.ddt', function ($ddtQ) {
                            $ddtQ->where('numero', 'like', 'LEGACY-%');
                        });
                });
            });
        }

        if ($this->soloSenzaCosto) {
            $query->where(function ($q) {
                $q->whereNull('articoli.prezzo_acquisto')
                    ->orWhere('articoli.prezzo_acquisto', 0);
            });
        }

        if ($this->search) {
            $search = trim((string) $this->search);
            $query->where(function ($q) use ($search) {
                $q->where('articoli.codice', 'like', '%' . $search . '%')
                    ->orWhere('articoli.descrizione', 'like', '%' . $search . '%');
            });
        }
    }

    private function applyCategoriaEContoDepositoFilterToQueryBuilder($query): void
    {
        if (!$this->categoriaId) {
            if ($this->filtroContoDeposito === 'solo_reale') {
                $query->where(function ($q) {
                    $q->whereNull('articoli.conto_deposito_corrente_id')
                        ->orWhere('articoli.quantita_in_deposito', '<=', 0);
                });
            } elseif ($this->filtroContoDeposito === 'solo_conto_deposito') {
                $query->whereNotNull('articoli.conto_deposito_corrente_id')
                    ->where('articoli.quantita_in_deposito', '>', 0);
            }

            return;
        }

        $magazzinoLogico = $this->resolveMagazzinoLogicoCategoriaCorrente();
        $prefix = $this->getMagazzinoLogicoPrefix($magazzinoLogico);

        $query->where('articoli.codice', 'like', $prefix);

        if ($this->filtroContoDeposito === 'solo_conto_deposito') {
            $query->whereNotNull('articoli.conto_deposito_corrente_id')
                ->where('articoli.quantita_in_deposito', '>', 0);
        } elseif ($this->filtroContoDeposito === 'solo_reale') {
            $query->where(function ($q) {
                $q->whereNull('articoli.conto_deposito_corrente_id')
                    ->orWhere('articoli.quantita_in_deposito', '<=', 0);
            });
        }
    }

    private function applyCategoriaEContoDepositoFilterToEloquent($query): void
    {
        if (!$this->categoriaId) {
            if ($this->filtroContoDeposito === 'solo_reale') {
                $query->where(function ($q) {
                    $q->whereNull('articoli.conto_deposito_corrente_id')
                        ->orWhere('articoli.quantita_in_deposito', '<=', 0);
                });
            } elseif ($this->filtroContoDeposito === 'solo_conto_deposito') {
                $query->whereNotNull('articoli.conto_deposito_corrente_id')
                    ->where('articoli.quantita_in_deposito', '>', 0);
            }

            return;
        }

        $magazzinoLogico = $this->resolveMagazzinoLogicoCategoriaCorrente();
        $prefix = $this->getMagazzinoLogicoPrefix($magazzinoLogico);

        $query->where('articoli.codice', 'like', $prefix);

        if ($this->filtroContoDeposito === 'solo_conto_deposito') {
            $query->whereNotNull('articoli.conto_deposito_corrente_id')
                ->where('articoli.quantita_in_deposito', '>', 0);
        } elseif ($this->filtroContoDeposito === 'solo_reale') {
            $query->where(function ($q) {
                $q->whereNull('articoli.conto_deposito_corrente_id')
                    ->orWhere('articoli.quantita_in_deposito', '<=', 0);
            });
        }
    }

    private function resolveMagazzinoLogicoCategoriaCorrente(): int
    {
        $resolved = app(MagazzinoLogicoService::class)->resolveFromCategoriaId((int) $this->categoriaId);

        return $resolved ?: (int) $this->categoriaId;
    }

    private function getMagazzinoLogicoPrefix(int $magazzinoLogico): string
    {
        return $magazzinoLogico . '-%';
    }

    private function getMagazzinoLogicoGroupingSql(): string
    {
        return "COALESCE(CAST(articoli.magazzino_logico AS CHAR), CAST(SUBSTRING_INDEX(articoli.codice, '-', 1) AS CHAR), 'n/a')";
    }

    private function getMagazzinoLogicoLabelSql(): string
    {
        return "CONCAT('Magazzino ', " . $this->getMagazzinoLogicoGroupingSql() . ")";
    }

    private function applyOrdinamentoArticoli(Builder $query): void
    {
        $direction = $this->sortArticoliDirection === 'desc' ? 'desc' : 'asc';
        $field = $this->sortArticoliField ?: 'codice';

        $query->select('articoli.*')
            ->leftJoin('giacenze', 'giacenze.articolo_id', '=', 'articoli.id')
            ->leftJoin('sedi', 'sedi.id', '=', 'giacenze.sede_id')
            ->leftJoin('categorie_merceologiche', 'categorie_merceologiche.id', '=', 'articoli.categoria_merceologica_id');

        $fornitoreNomeSql = "COALESCE(
            (SELECT fornitori.ragione_sociale
             FROM fatture_dettagli
             INNER JOIN fatture ON fatture.id = fatture_dettagli.fattura_id
             INNER JOIN fornitori ON fornitori.id = fatture.fornitore_id
             WHERE fatture_dettagli.articolo_id = articoli.id
             ORDER BY fatture.id ASC
             LIMIT 1),
            (SELECT fornitori.ragione_sociale
             FROM ddt_dettagli
             INNER JOIN ddts ON ddts.id = ddt_dettagli.ddt_id
             INNER JOIN fornitori ON fornitori.id = ddts.fornitore_id
             WHERE ddt_dettagli.articolo_id = articoli.id
             ORDER BY ddts.id ASC
             LIMIT 1)
        )";

        switch ($field) {
            case 'descrizione':
                $query->orderBy('articoli.descrizione', $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'sede':
                $query->orderBy('sedi.nome', $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'categoria':
                $query->orderBy('categorie_merceologiche.nome', $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'fornitore':
                $query->orderByRaw($fornitoreNomeSql . ' ' . $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'quantita':
                $query->orderBy('giacenze.quantita_residua', $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'costo_unitario':
                $query->orderBy('articoli.prezzo_acquisto', $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'valore':
                $query->orderByRaw('COALESCE(giacenze.quantita_residua, 0) * COALESCE(articoli.prezzo_acquisto, 0) ' . $direction)
                    ->orderBy('articoli.codice', 'asc');
                break;
            case 'codice':
            default:
                $query->orderBy('articoli.codice', $direction);
                break;
        }
    }

    public function apriFatturaModal($articoloId = null)
    {
        Log::info('🔓 APERTURA MODAL FATTURA', ['articolo_id' => $articoloId, 'selezionati' => $this->articoliSelezionati]);
        
        // Reset valori precedenti
        $this->reset(['articoliFattura', 'quantitaArticolo', 'costoUnitarioArticolo']);
        $this->quantitaArticolo = 1;
        $this->costoUnitarioArticolo = 0;
        $this->articoloSelezionato = null;
        
        // Se ci sono articoli selezionati multipli, li aggiungi tutti
        if (!empty($this->articoliSelezionati) && count($this->articoliSelezionati) > 0) {
            $articoli = Articolo::whereIn('id', $this->articoliSelezionati)->get();
            foreach ($articoli as $art) {
                $this->articoliFattura[] = [
                    'articolo_id' => $art->id,
                    'quantita' => 1,
                    'costo_unitario' => $art->prezzo_acquisto ?? 0,
                ];
            }
            Log::info('📦 ARTICOLI MULTIPLI AGGIUNTI', ['count' => count($this->articoliFattura)]);
        } 
        // Se è un singolo articolo, aggiungilo direttamente
        elseif ($articoloId) {
            $articolo = Articolo::with(['fatturaDettaglio.fattura'])->findOrFail($articoloId);
            $this->articoliFattura[] = [
                'articolo_id' => $articolo->id,
                'quantita' => 1,
                'costo_unitario' => $articolo->prezzo_acquisto ?? 0,
            ];
            Log::info('📦 ARTICOLO SINGOLO AGGIUNTO', [
                'id' => $articolo->id,
                'codice' => $articolo->codice,
            ]);
        }
        
        $this->showFatturaModal = true;
    }
    
    public function toggleSelezioneArticolo($articoloId)
    {
        if (in_array($articoloId, $this->articoliSelezionati)) {
            $this->articoliSelezionati = array_values(array_diff($this->articoliSelezionati, [$articoloId]));
        } else {
            $this->articoliSelezionati[] = $articoloId;
        }
    }
    
    public function deselezionaTuttiArticoli()
    {
        $this->articoliSelezionati = [];
    }
    
    public function selezionaTuttiArticoli()
    {
        $articoli = $this->articoliGiacenti->items();
        $this->articoliSelezionati = collect($articoli)->pluck('id')->toArray();
    }

    public function aggiungiArticoloAllaFattura($articoloId = null)
    {
        if (!$articoloId && !$this->articoloSelezionato) {
            session()->flash('error', 'Nessun articolo selezionato');
            return;
        }

        $articolo = $articoloId ? Articolo::find($articoloId) : $this->articoloSelezionato;
        if (!$articolo) {
            session()->flash('error', 'Articolo non trovato');
            return;
        }

        $costo = floatval($this->costoUnitarioArticolo ?? $articolo->prezzo_acquisto ?? 0);
        $quantita = intval($this->quantitaArticolo ?? 1);

        if ($costo <= 0) {
            session()->flash('error', 'Il costo deve essere maggiore di 0');
            return;
        }

        $nextIndex = count(array_filter($this->articoliFattura ?? []));
        
        if (!is_array($this->articoliFattura)) {
            $this->articoliFattura = [];
        }

        // Verifica se l'articolo è già nella lista
        $giaPresente = collect($this->articoliFattura)->contains(function ($art) use ($articolo) {
            return isset($art['articolo_id']) && $art['articolo_id'] == $articolo->id;
        });

        if ($giaPresente) {
            session()->flash('info', 'Articolo già presente nella fattura');
            return;
        }

        $this->articoliFattura[$nextIndex] = [
            'articolo_id' => $articolo->id,
            'quantita' => $quantita,
            'costo_unitario' => $costo,
        ];

        Log::info('➕ ARTICOLO AGGIUNTO ALLA FATTURA', [
            'articolo_id' => $articolo->id,
            'quantita' => $quantita,
            'costo_unitario' => $costo,
            'totale_articoli' => count($this->articoliFattura),
        ]);

        // Reset campi
        $this->quantitaArticolo = 1;
        $this->costoUnitarioArticolo = 0;
        $this->articoloSelezionato = null;
        
        session()->flash('success', 'Articolo aggiunto alla fattura!');
    }

    public function rimuoviArticoloDallaFattura($index)
    {
        if (isset($this->articoliFattura[$index])) {
            unset($this->articoliFattura[$index]);
            $this->articoliFattura = array_values($this->articoliFattura); // Reindizza array
        }
    }

    public function ordinaStatistiche($campo)
    {
        if ($this->sortStatisticheField === $campo) {
            $this->sortStatisticheDirection = $this->sortStatisticheDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortStatisticheField = $campo;
            $this->sortStatisticheDirection = 'desc';
        }
    }

    public function filtraPerSede($sedeId)
    {
        $this->sedeId = $sedeId;
    }

    public function filtraPerFornitore($fornitoreId)
    {
        // Imposta il filtro fornitore (può essere un ID numerico o 'n/a')
        $this->fornitoreId = $fornitoreId;
    }

    public function filtraPerCategoria($categoriaId)
    {
        $this->categoriaId = $categoriaId;
    }

    public function getHasDrilldownFiltersProperty(): bool
    {
        return filled($this->sedeId)
            || filled($this->fornitoreId)
            || filled($this->categoriaId)
            || filled($this->marcaId);
    }

    public function getStatisticheOrdinateProperty()
    {
        $statistiche = $this->statistiche;
        
        if ($this->viewStatistiche === 'sede' && isset($statistiche['per_sede'])) {
            $statistiche['per_sede'] = collect($statistiche['per_sede'])
                ->sortBy($this->sortStatisticheField, SORT_REGULAR, $this->sortStatisticheDirection === 'desc')
                ->toArray();
        } elseif ($this->viewStatistiche === 'fornitore' && isset($statistiche['per_fornitore'])) {
            $collection = collect($statistiche['per_fornitore'])
                ->sortBy($this->sortStatisticheField, SORT_REGULAR, $this->sortStatisticheDirection === 'desc');
            
            // Applica filtro ricerca se presente
            if ($this->searchStatistiche) {
                $collection = $collection->filter(function ($item, $key) {
                    return stripos($item['nome'], $this->searchStatistiche) !== false;
                });
            }
            
            $statistiche['per_fornitore'] = $collection->toArray();
        } elseif ($this->viewStatistiche === 'categoria' && isset($statistiche['per_categoria'])) {
            $statistiche['per_categoria'] = collect($statistiche['per_categoria'])
                ->sortBy($this->sortStatisticheField, SORT_REGULAR, $this->sortStatisticheDirection === 'desc')
                ->toArray();
        } elseif ($this->viewStatistiche === 'marca' && isset($statistiche['per_marca'])) {
            $collection = collect($statistiche['per_marca'])
                ->sortBy($this->sortStatisticheField, SORT_REGULAR, $this->sortStatisticheDirection === 'desc');
            
            // Applica filtro ricerca se presente
            if ($this->searchStatistiche) {
                $collection = $collection->filter(function ($item, $key) {
                    return stripos($item['nome'], $this->searchStatistiche) !== false;
                });
            }
            
            $statistiche['per_marca'] = $collection->toArray();
        }
        
        return $statistiche;
    }
    
    public function getFornitoriVisibiliProperty()
    {
        $statistiche = $this->statisticheOrdinate;
        if (!isset($statistiche['per_fornitore'])) {
            return [];
        }
        
        $fornitori = $statistiche['per_fornitore'];
        
        if ($this->mostraTuttiFornitori) {
            return $fornitori;
        }
        
        return array_slice($fornitori, 0, $this->limiteFornitori, true);
    }
    
    public function toggleMostraTuttiFornitori()
    {
        $this->mostraTuttiFornitori = !$this->mostraTuttiFornitori;
    }
    
    public function getMarcheVisibiliProperty()
    {
        $statistiche = $this->statisticheOrdinate;
        if (!isset($statistiche['per_marca'])) {
            return [];
        }
        
        $marche = $statistiche['per_marca'];
        
        if ($this->mostraTutteMarche) {
            return $marche;
        }
        
        return array_slice($marche, 0, $this->limiteMarche, true);
    }
    
    public function toggleMostraTutteMarche()
    {
        $this->mostraTutteMarche = !$this->mostraTutteMarche;
    }
    
    public function filtraPerMarca($marcaId)
    {
        // Se marcaId è 'n/a', cerca articoli senza marca
        $this->marcaId = $marcaId;
    }

    public function getTotaliVistaCorrenteProperty(): array
    {
        $items = match ($this->viewStatistiche) {
            'sede' => $this->statisticheOrdinate['per_sede'] ?? [],
            'fornitore' => $this->fornitoriVisibili,
            'categoria' => $this->statisticheOrdinate['per_categoria'] ?? [],
            'marca' => $this->marcheVisibili,
            default => [],
        };

        if (empty($items)) {
            return [
                'articoli' => (int) ($this->statistiche['totale_articoli'] ?? 0),
                'quantita' => (float) ($this->statistiche['totale_quantita'] ?? 0),
                'valore' => (float) ($this->statistiche['totale_valore'] ?? 0),
            ];
        }

        return [
            'articoli' => (int) collect($items)->sum('articoli'),
            'quantita' => (float) collect($items)->sum('quantita'),
            'valore' => (float) collect($items)->sum('valore'),
        ];
    }

    public function chiudiFatturaModal()
    {
        $this->showFatturaModal = false;
        $this->reset(['fatturaSelezionata', 'articoloSelezionato', 'numeroFattura', 'dataFattura', 
                     'fornitoreFatturaId', 'sedeFatturaId', 'articoliFattura', 'quantitaArticolo', 'costoUnitarioArticolo']);
    }

    public function salvaFattura()
    {
        $this->validate([
            'numeroFattura' => 'required|string|max:50',
            'dataFattura' => 'required|date',
            'fornitoreFatturaId' => 'required|exists:fornitori,id',
            'sedeFatturaId' => 'required|exists:sedi,id',
        ]);

        // Verifica che ci siano articoli nella fattura
        $articoliValidati = collect($this->articoliFattura)
            ->filter(function ($art) {
                return !empty($art) && isset($art['articolo_id']) && !empty($art['articolo_id']) 
                    && isset($art['costo_unitario']) && $art['costo_unitario'] > 0;
            });

        if ($articoliValidati->isEmpty()) {
            session()->flash('error', 'ERRORE: Devi aggiungere almeno un articolo con costo alla fattura. Clicca "Aggiungi alla Fattura" dopo aver inserito il costo.');
            return;
        }

        Log::info('💾 SALVATAGGIO FATTURA', [
            'numero' => $this->numeroFattura,
            'articoli_count' => $articoliValidati->count(),
            'articoli' => $articoliValidati->toArray(),
        ]);

        DB::transaction(function () use ($articoliValidati) {
            // Crea o aggiorna fattura
            $fattura = Fattura::updateOrCreate(
                [
                    'numero' => $this->numeroFattura,
                    'anno' => date('Y', strtotime($this->dataFattura)),
                    'fornitore_id' => $this->fornitoreFatturaId,
                ],
                [
                    'data_documento' => $this->dataFattura,
                    'sede_id' => $this->sedeFatturaId,
                    'stato' => 'caricata',
                    'totale' => 0,
                    'imponibile' => 0,
                    'iva' => 0,
                ]
            );

            // Salva articoli della fattura
            foreach ($articoliValidati as $art) {
                    // Salva storico costo prima di aggiornare - carica articolo
                    $articolo = Articolo::find($art['articolo_id']);
                    
                    if (!$articolo) {
                        Log::error('❌ ARTICOLO NON TROVATO', ['articolo_id' => $art['articolo_id']]);
                        continue;
                    }
                    
                    // Calcola totali riga
                    $quantita = $art['quantita'] ?? 1;
                    $prezzoUnitario = $art['costo_unitario'] ?? 0;
                    $totaleRiga = $quantita * $prezzoUnitario;
                    
                    // Crea dettaglio fattura
                    FatturaDettaglio::updateOrCreate(
                        [
                            'fattura_id' => $fattura->id,
                            'articolo_id' => $articolo->id,
                        ],
                        [
                            'quantita' => $quantita,
                            'prezzo_unitario' => $prezzoUnitario,
                            'totale_riga' => $totaleRiga,
                            'codice_articolo' => $articolo->codice ?? null,
                            'descrizione' => $articolo->descrizione ?? null,
                            'caricato' => true,
                        ]
                    );

                    // Aggiorna costo articolo (l'articolo è già stato trovato sopra)
                    $costoPrecedente = $articolo->prezzo_acquisto;
                    
                    Log::info('📝 AGGIORNAMENTO ARTICOLO', [
                        'articolo_id' => $articolo->id,
                        'codice' => $articolo->codice,
                        'costo_precedente' => $costoPrecedente,
                        'costo_nuovo' => $prezzoUnitario,
                    ]);
                    
                    // Registra storico solo se il costo è cambiato
                    if ($costoPrecedente != $prezzoUnitario) {
                        ArticoloStoricoCosto::create([
                            'articolo_id' => $articolo->id,
                            'costo_precedente' => $costoPrecedente,
                            'costo_nuovo' => $prezzoUnitario,
                            'fattura_id' => $fattura->id,
                            'user_id' => auth()->id() ?? null,
                            'note' => "Costo aggiornato da fattura {$fattura->numero}",
                        ]);
                    }
                    
                    // Aggiorna costo articolo
                    $articolo->update(['prezzo_acquisto' => $prezzoUnitario]);
                    
                    Log::info('✅ ARTICOLO AGGIORNATO', [
                        'articolo_id' => $articolo->id,
                        'prezzo_acquisto' => $articolo->fresh()->prezzo_acquisto,
                    ]);
            }

            // Recalcola totale fattura
            $totale = $articoliValidati->sum(function ($art) {
                return ($art['costo_unitario'] ?? 0) * ($art['quantita'] ?? 1);
            });
            
            $fattura->update([
                'totale' => $totale,
                'imponibile' => $totale,
                'quantita_totale' => $articoliValidati->sum('quantita'),
                'numero_articoli' => $articoliValidati->count(),
            ]);
            
            Log::info('💰 FATTURA SALVATA', [
                'fattura_id' => $fattura->id,
                'numero' => $fattura->numero,
                'totale' => $totale,
                'numero_articoli' => $articoliValidati->count(),
            ]);

            session()->flash('success', 'Fattura salvata con successo! Costi aggiornati.');
        });

        $this->chiudiFatturaModal();
        
        // Reset selezione dopo salvataggio
        $this->articoliSelezionati = [];
        
        // Refresh articoli per vedere i cambiamenti
        $this->resetPage();
    }


    public function updatedViewStatistiche()
    {
        $this->resetPage();
    }

    public function updatingDataDocumentoCaricoPrimaDi()
    {
        $this->resetPage();
    }

    public function updatingSortArticoliField()
    {
        $this->resetPage();
    }

    public function updatingSortArticoliDirection()
    {
        $this->resetPage();
    }

    public function apriStoricoCosti($articoloId)
    {
        $this->articoloStorico = Articolo::with(['storicoCosti.user', 'storicoCosti.fattura'])
            ->findOrFail($articoloId);
        $this->showStoricoCostiModal = true;
    }

    public function chiudiStoricoCostiModal()
    {
        $this->showStoricoCostiModal = false;
        $this->articoloStorico = null;
    }

    public function exportExcel()
    {
        $filtri = [
            'sedeId' => $this->sedeId,
            'fornitoreId' => $this->fornitoreId,
            'categoriaId' => $this->categoriaId,
            'search' => $this->search,
            'dataDocumentoCaricoPrimaDi' => $this->dataDocumentoCaricoPrimaDi,
            'filtroContoDeposito' => $this->filtroContoDeposito,
            'soloSenzaCosto' => $this->soloSenzaCosto,
            'soloGiacenti' => $this->soloGiacenti,
        ];

        $export = new StatisticheMagazzinoExport($filtri, $this->statistiche);
        
        $filename = 'statistiche_magazzino_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download($export, $filename);
    }

    public function exportPdf()
    {
        // Export PDF implementato tramite print browser
        session()->flash('info', 'Usa il pulsante Stampa del browser per salvare come PDF');
    }

    public function getStatisticheConfrontoProperty()
    {
        if (!$this->mostraConfronto || !$this->dataInizio || !$this->dataFine) {
            return null;
        }

        $dataInizio = Carbon::parse($this->dataInizio)->startOfDay();
        $dataFine = Carbon::parse($this->dataFine)->endOfDay();

        // Calcola valorizzazione al periodo precedente
        $articoliPeriodoPrecedente = Articolo::with(['giacenza', 'fatturaDettaglio.fattura.fornitore'])
            ->whereHas('giacenza', function ($q) {
                $q->where('quantita_residua', '>', 0);
            })
            ->where('created_at', '<', $dataInizio)
            ->get();

        $valorePeriodoPrecedente = $articoliPeriodoPrecedente->sum(function ($art) {
            $qta = $art->giacenza->quantita_residua ?? 0;
            $costo = $art->prezzo_acquisto ?? 0;
            return $qta * $costo;
        });

        // Calcola valorizzazione attuale
        $valoreAttuale = $this->statistiche['totale_valore'];

        return [
            'valore_precedente' => $valorePeriodoPrecedente,
            'valore_attuale' => $valoreAttuale,
            'variazione' => $valoreAttuale - $valorePeriodoPrecedente,
            'variazione_percentuale' => $valorePeriodoPrecedente > 0 
                ? (($valoreAttuale - $valorePeriodoPrecedente) / $valorePeriodoPrecedente) * 100 
                : 0,
        ];
    }

    public function render()
    {
        return view('livewire.amministrazione-magazzino-dashboard', [
            'statistiche' => $this->statisticheOrdinate,
            'statisticheConfronto' => $this->statisticheConfronto,
            'sedi' => $this->sedi,
            'fornitori' => $this->fornitori,
            'categorie' => $this->categorie,
        ]);
    }
}
