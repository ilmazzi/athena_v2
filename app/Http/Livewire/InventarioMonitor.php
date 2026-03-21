<?php

namespace App\Http\Livewire;

use App\Models\Articolo;
use App\Models\InventarioSessione;
use App\Models\InventarioScansione;
use App\Models\InventarioEvento;
use App\Models\Sede;
use App\Models\CategoriaMerceologica;
use App\Services\MagazzinoLogicoService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Schema;

class InventarioMonitor extends Component
{
    use WithPagination;

    public $sessioneId = null;
    public $sessione = null;
    public $view = 'overview';
    public $sedeId = '';
    public $categoriaId = '';
    public $statoArticolo = ''; // tutti, trovati, mancanti, non_scansionati
    public $baseConfronto = 'scansione'; // scansione | giacenza
    public $sedi = [];
    public $categorie = [];
    public $statistiche = [];
    public $statisticheMagazzini = [];
    public $risultatiVerifica = [];
    public $risultatiConfronto = [];
    public $showModalVerifica = false;
    public $showModalConfronto = false;
    public $showEditArticolo = false;
    public $editingArticoloId = null;
    public $editingQuantitaTrovata = null;
    public $editingQuantitaSistema = null;
    public $azioneCategoriaId = null;
    public $showConfirmAllinea = false;
    public $showConfirmFinalizza = false;
    public $previewAllinea = 0;
    public $previewFinalizza = [
        'da_allineare' => 0,
        'da_rimuovere' => 0,
    ];
    public $articoliDaInventariare = [];
    public $articoliTrovati = [];
    public $articoliMancanti = [];
    public $articoliNonScansionati = [];
    public $autoRefresh = false;
    public $bulkRule = 'set_trovata_to_sistema';
    public $bulkTarget = 'incongruenze';
    public $showConfirmBulk = false;
    public $bulkPreviewCount = 0;
    public $bulkDiffMin = null;
    public $bulkDiffMax = null;
    public $bulkValoreMin = null;
    public $bulkValoreMax = null;
    public $heatmapDiffHigh = 10;
    public $heatmapDiffMedium = 3;
    public $showConfirmAzione = false;
    public $pendingArticoloId = null;
    public $pendingAction = null;
    public $actionFeedback = null;
    public $lastActionArticoloId = null;
    public $lastActionMessage = null;
    public $lastFinalizzaReport = null;

    public function mount($sessione = null)
    {
        $this->sessioneId = $sessione;
        $this->sedi = Sede::all();
        $this->categorie = collect($this->buildMagazzinoOptions());
        
        if ($this->sessioneId) {
            $this->caricaSessione();
        }
    }

    public function caricaSessione()
    {
        if ($this->sessioneId) {
            $this->sessione = InventarioSessione::with(['sede', 'utente'])
                ->find($this->sessioneId);
            
            if ($this->sessione) {
                $this->sedeId = $this->sessione->sede_id;
                $this->calcolaStatistiche();
                $this->caricaArticoli();
            }
        }
    }

    private function normalizeCategoriePermesse(?array $categoriePermesse): array
    {
        if (empty($categoriePermesse)) {
            return [];
        }

        $service = app(MagazzinoLogicoService::class);

        return collect($categoriePermesse)
            ->map(fn ($categoriaId) => $service->resolveFromCategoriaId((int) $categoriaId) ?? (int) $categoriaId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeCategoriaFiltro($categoriaId): ?int
    {
        if ($categoriaId === null || $categoriaId === '') {
            return null;
        }

        return app(MagazzinoLogicoService::class)->resolveFromCategoriaId((int) $categoriaId) ?? (int) $categoriaId;
    }

    private function buildMagazzinoOptions(): array
    {
        $service = app(MagazzinoLogicoService::class);

        return CategoriaMerceologica::query()
            ->orderBy('nome')
            ->get()
            ->map(function ($categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);

                return $magazzinoLogico ? (object) [
                    'id' => $magazzinoLogico,
                    'nome' => 'Magazzino ' . $magazzinoLogico,
                ] : null;
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values()
            ->all();
    }

    public function calcolaStatistiche()
    {
        if (!$this->sessione) return;

        $categoriePermesse = $this->normalizeCategoriePermesse($this->sessione->categorie_permesse);

        $query = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        });

        if (!empty($categoriePermesse)) {
            $query->whereIn('magazzino_logico', $categoriePermesse);
        }

        $articoliTotali = $query->count();
        $articoliScansionati = InventarioScansione::where('sessione_id', $this->sessioneId)->distinct('articolo_id')->count('articolo_id');
        $articoliTrovati = InventarioScansione::where('sessione_id', $this->sessioneId)->where('azione', 'trovato')->distinct('articolo_id')->count('articolo_id');
        $articoliEliminati = InventarioScansione::where('sessione_id', $this->sessioneId)->where('azione', 'eliminato')->distinct('articolo_id')->count('articolo_id');

        $scansioniBase = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();
        $scansioniConDifferenza = (clone $scansioniBase)->whereRaw("{$diffExpr} != 0")->count();
        $scansioniConEccesso = (clone $scansioniBase)->whereRaw("{$diffExpr} > 0")->count();
        $scansioniConMancanza = (clone $scansioniBase)->whereRaw("{$diffExpr} < 0")->count();
        $scansioniParziali = (clone $scansioniBase)
            ->whereNotNull('inventario_scansioni.quantita_trovata')
            ->whereRaw("COALESCE(inventario_scansioni.quantita_trovata, 0) > 0 AND {$diffExpr} < 0")
            ->count();

        $articoliNonScansionati = $articoliTotali - $articoliScansionati;
        $progresso = $articoliTotali > 0 ? round(($articoliScansionati / $articoliTotali) * 100, 2) : 0;

        \Log::info('Statistiche Inventario', [
            'sessione_id' => $this->sessioneId,
            'sede_id' => $this->sessione->sede_id,
            'categorie_permesse' => $categoriePermesse,
            'articoli_totali' => $articoliTotali,
            'articoli_scansionati' => $articoliScansionati,
            'articoli_trovati' => $articoliTrovati,
            'articoli_eliminati' => $articoliEliminati,
            'articoli_non_scansionati' => $articoliNonScansionati,
            'progresso' => $progresso,
        ]);

        $valoreMagazzinoQuery = \App\Models\Giacenza::where('sede_id', $this->sessione->sede_id)
            ->where('quantita_residua', '>', 0);
        if (!empty($categoriePermesse)) {
            $valoreMagazzinoQuery->whereIn('magazzino_logico', $categoriePermesse);
        }
        $valoreMagazzino = $valoreMagazzinoQuery->sum(\DB::raw('quantita_residua * costo_unitario'));

        $this->statistiche = [
            'articoli_totali' => $articoliTotali,
            'articoli_scansionati' => $articoliScansionati,
            'articoli_trovati' => $articoliTrovati,
            'articoli_eliminati' => $articoliEliminati,
            'articoli_non_scansionati' => $articoliNonScansionati,
            'scansioni_con_differenza' => $scansioniConDifferenza,
            'scansioni_con_eccesso' => $scansioniConEccesso,
            'scansioni_con_mancanza' => $scansioniConMancanza,
            'scansioni_parziali' => $scansioniParziali,
            'valore_magazzino' => $valoreMagazzino,
            'progresso' => $progresso,
            'completato' => $articoliNonScansionati == 0,
        ];

        $baseMagazzini = \App\Models\Giacenza::query()
            ->where('sede_id', $this->sessione->sede_id)
            ->where('quantita_residua', '>', 0)
            ->when(!empty($categoriePermesse), fn ($q) => $q->whereIn('magazzino_logico', $categoriePermesse))
            ->selectRaw('magazzino_logico, COUNT(DISTINCT articolo_id) as totali, SUM(quantita_residua * costo_unitario) as valore')
            ->groupBy('magazzino_logico')
            ->get()
            ->keyBy('magazzino_logico');

        $scanMagazzini = InventarioScansione::query()
            ->where('inventario_scansioni.sessione_id', $this->sessioneId)
            ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            ->when(!empty($categoriePermesse), fn ($q) => $q->whereIn('articoli.magazzino_logico', $categoriePermesse))
            ->when($this->baseConfronto === 'giacenza', function ($query) {
                $giacenzeSub = \DB::table('giacenze')
                    ->select('articolo_id', \DB::raw('SUM(quantita_residua) as quantita_sistema'))
                    ->where('sede_id', $this->sessione->sede_id)
                    ->groupBy('articolo_id');
                $query->leftJoinSub($giacenzeSub, 'giacenze_sede', function ($join) {
                    $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_sede.articolo_id');
                });
            })
            ->selectRaw("
                articoli.magazzino_logico as categoria_id,
                COUNT(DISTINCT inventario_scansioni.articolo_id) as scansionati,
                SUM(CASE WHEN inventario_scansioni.azione = 'trovato' THEN 1 ELSE 0 END) as trovati,
                SUM(CASE WHEN inventario_scansioni.azione = 'eliminato' THEN 1 ELSE 0 END) as eliminati,
                SUM(CASE WHEN {$diffExpr} != 0 THEN 1 ELSE 0 END) as incongruenze,
                SUM(CASE WHEN {$diffExpr} > 0 THEN 1 ELSE 0 END) as eccedenze,
                SUM(CASE WHEN {$diffExpr} < 0 THEN 1 ELSE 0 END) as mancanze,
                SUM(CASE WHEN COALESCE(inventario_scansioni.quantita_trovata, 0) > 0 AND {$diffExpr} < 0 THEN 1 ELSE 0 END) as parziali
            ")
            ->groupBy('articoli.magazzino_logico')
            ->get()
            ->keyBy('categoria_id');

        $magazzinoIds = !empty($categoriePermesse)
            ? collect($categoriePermesse)
            : $baseMagazzini->keys()->merge($scanMagazzini->keys())->filter()->unique()->sort()->values();

        $this->statisticheMagazzini = $magazzinoIds->map(function ($magazzinoId) use ($baseMagazzini, $scanMagazzini) {
            $base = $baseMagazzini->get($magazzinoId);
            $scan = $scanMagazzini->get($magazzinoId);
            $totali = (int) ($base?->totali ?? 0);
            $scansionati = (int) ($scan?->scansionati ?? 0);
            $trovati = (int) ($scan?->trovati ?? 0);
            $eliminati = (int) ($scan?->eliminati ?? 0);
            $mancanti = max($totali - $scansionati, 0);

            return [
                'id' => (int) $magazzinoId,
                'nome' => 'Magazzino ' . $magazzinoId,
                'totali' => $totali,
                'scansionati' => $scansionati,
                'trovati' => $trovati,
                'eliminati' => $eliminati,
                'mancanti' => $mancanti,
                'incongruenze' => (int) ($scan?->incongruenze ?? 0),
                'eccedenze' => (int) ($scan?->eccedenze ?? 0),
                'parziali' => (int) ($scan?->parziali ?? 0),
                'valore' => (float) ($base?->valore ?? 0),
            ];
        })->values()->toArray();
    }

    public function caricaArticoli()
    {
        if (!$this->sessione) return;

        // Questo metodo ora serve solo per aggiornare le statistiche
        // La paginazione è gestita da getArticoliFiltrati()
        $this->calcolaStatistiche();
    }

    public function filtraArticoli()
    {
        $this->caricaArticoli();
    }

    public function verificaDati()
    {
        if (!$this->sessione) return;

        $categoriePermesse = $this->normalizeCategoriePermesse($this->sessione->categorie_permesse);

        $articoliPerSede = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        })->count();

        $articoliPerCategoria = [];
        if (!empty($categoriePermesse)) {
            foreach ($categoriePermesse as $categoriaId) {
                $count = Articolo::whereHas('giacenze', function ($q) {
                    $q->where('sede_id', $this->sessione->sede_id)
                      ->where('quantita_residua', '>', 0);
                })->where('magazzino_logico', $categoriaId)->count();

                $articoliPerCategoria['Magazzino ' . $categoriaId] = $count;
            }
        }

        $articoliConFiltri = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        });

        if (!empty($categoriePermesse)) {
            $articoliConFiltri->whereIn('magazzino_logico', $categoriePermesse);
        }
        $totaleConFiltri = $articoliConFiltri->count();

        $scansioniTotali = InventarioScansione::where('sessione_id', $this->sessioneId)->count();
        $scansioniDistinct = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->distinct('articolo_id')
            ->count('articolo_id');

        $totaleArticoliDB = Articolo::count();
        $totaleGiacenze = \App\Models\Giacenza::where('quantita_residua', '>', 0)->count();

        \Log::info('Verifica Dati Inventario DETTAGLIATA', [
            'sessione_id' => $this->sessioneId,
            'sede_id' => $this->sessione->sede_id,
            'sede_nome' => $this->sessione->sede->nome,
            'categorie_permesse' => $categoriePermesse,
            'articoli_per_sede' => $articoliPerSede,
            'articoli_per_categoria' => $articoliPerCategoria,
            'totale_con_filtri' => $totaleConFiltri,
            'totale_articoli_db' => $totaleArticoliDB,
            'totale_giacenze' => $totaleGiacenze,
            'scansioni_totali' => $scansioniTotali,
            'scansioni_distinct' => $scansioniDistinct,
        ]);

        $this->risultatiVerifica = [
            'sessione_id' => $this->sessioneId,
            'sede_id' => $this->sessione->sede_id,
            'sede_nome' => $this->sessione->sede->nome,
            'categorie_permesse' => $categoriePermesse,
            'articoli_per_sede' => $articoliPerSede,
            'articoli_per_categoria' => $articoliPerCategoria,
            'totale_con_filtri' => $totaleConFiltri,
            'totale_articoli_db' => $totaleArticoliDB,
            'totale_giacenze' => $totaleGiacenze,
            'scansioni_totali' => $scansioniTotali,
            'scansioni_distinct' => $scansioniDistinct,
        ];
        
        $this->showModalVerifica = true;
        session()->flash('info', "âœ… Dati verificati! Visualizza i risultati qui sotto.");
    }

    public function confrontaConArticoli()
    {
        if (!$this->sessione) return;

        // Conteggio dalla pagina articoli (tutti gli articoli con giacenze > 0)
        $articoliPagina = Articolo::whereHas('giacenze', function ($q) {
            $q->where('quantita_residua', '>', 0);
        })->count();

        // Conteggio per sede specifica
        $articoliSede = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        })->count();

        // Conteggio per categorie 1-9
        $articoliCategorie19 = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        })->whereIn('magazzino_logico', [1,2,3,4,5,6,7,8,9])->count();

        // Conteggio per tutte le categorie
        $articoliTutteCategorie = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        })->count();

        \Log::info('Confronto con Pagina Articoli', [
            'sessione_id' => $this->sessioneId,
            'sede_id' => $this->sessione->sede_id,
            'sede_nome' => $this->sessione->sede->nome,
            'articoli_pagina_totale' => $articoliPagina,
            'articoli_sede' => $articoliSede,
            'articoli_magazzini_1_9' => $articoliCategorie19,
            'articoli_tutte_categorie' => $articoliTutteCategorie,
            'categorie_permesse_sessione' => $this->sessione->categorie_permesse
        ]);

        // Memorizza i risultati per mostrare nel modal
        $this->risultatiConfronto = [
            'sessione_id' => $this->sessioneId,
            'sede_id' => $this->sessione->sede_id,
            'sede_nome' => $this->sessione->sede->nome,
            'articoli_pagina_totale' => $articoliPagina,
            'articoli_sede' => $articoliSede,
            'articoli_magazzini_1_9' => $articoliCategorie19,
            'articoli_tutte_categorie' => $articoliTutteCategorie,
            'categorie_permesse_sessione' => $this->sessione->categorie_permesse
        ];
        
        $this->showModalConfronto = true;
        session()->flash('info', "✅ Confronto completato! Visualizza i risultati qui sotto.");
    }

    public function resetFiltri()
    {
        $this->categoriaId = '';
        $this->statoArticolo = '';
        $this->caricaArticoli();
    }

    public function setFiltroStat(string $filtro)
    {
        $this->statoArticolo = $filtro;
        $this->caricaArticoli();
        $this->view = 'articoli';
        $this->dispatch('scroll-to-articoli');
    }

    public function setView(string $view)
    {
        $this->view = $view;
        if ($view === 'articoli') {
            $this->dispatch('scroll-to-articoli');
        }
    }

    public function setCategoria(int $categoriaId)
    {
        $this->categoriaId = $categoriaId;
        $this->view = 'articoli';
        $this->caricaArticoli();
        $this->dispatch('scroll-to-articoli');
    }

    public function chiudiModalVerifica()
    {
        $this->showModalVerifica = false;
        $this->risultatiVerifica = [];
    }

    public function chiudiModalConfronto()
    {
        $this->showModalConfronto = false;
        $this->risultatiConfronto = [];
    }

    public function getScansioniEffettuate()
    {
        if (!$this->sessione) {
            return collect();
        }

        return InventarioScansione::where('sessione_id', $this->sessioneId)
            ->with(['articolo.categoriaMerceologica'])
            ->orderBy('data_scansione', 'desc')
            ->get();
    }

    public function getArticoliFiltrati()
    {
        if (!$this->sessione) {
            return \App\Models\Articolo::where('id', 0)->paginate(20);
        }

        return $this->buildArticoliQuery()
            ->orderBy('codice')
            ->paginate(20);
    }

    public function render()
    {
        $articoli = $this->getArticoliFiltrati();
        $eventi = $this->getEventiRecenti();
        $topAnomalie = $this->getTopAnomalie();
        $reportAnomalie = $this->getReportAnomalie();
        $kpiTimeline = $this->getKpiTimeline();
        $kpiPerCategoria = $this->getKpiPerCategoria();
        $heatmapAnomalie = $this->getHeatmapAnomalie();
        $topAnomalieValore = $this->getTopAnomalieValore();
        $azioniConsigliate = $this->getAzioniConsigliate();
        
        return view('livewire.inventario-monitor', [
            'articoli' => $articoli,
            'eventi' => $eventi,
            'topAnomalie' => $topAnomalie,
            'reportAnomalie' => $reportAnomalie,
            'kpiTimeline' => $kpiTimeline,
            'kpiPerCategoria' => $kpiPerCategoria,
            'heatmapAnomalie' => $heatmapAnomalie,
            'topAnomalieValore' => $topAnomalieValore,
            'azioniConsigliate' => $azioniConsigliate,
        ])->layout('layouts.vertical');
    }

    private function scansioniConGiacenzaQuery()
    {
        $giacenzeSub = \DB::table('giacenze')
            ->select('articolo_id', \DB::raw('SUM(quantita_residua) as quantita_sistema'))
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');

        return \DB::table('inventario_scansioni')
            ->where('inventario_scansioni.sessione_id', $this->sessioneId)
            ->leftJoinSub($giacenzeSub, 'giacenze_sede', function ($join) {
                $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_sede.articolo_id');
            });
    }

    private function diffExpression(): string
    {
        if ($this->baseConfronto === 'giacenza') {
            return "(COALESCE(inventario_scansioni.quantita_trovata, 0) - COALESCE(giacenze_sede.quantita_sistema, 0))";
        }

        return "(COALESCE(inventario_scansioni.quantita_trovata, 0) - COALESCE(inventario_scansioni.quantita_sistema, 0))";
    }

    private function scansioniBaseQuery()
    {
        if ($this->baseConfronto === 'giacenza') {
            return $this->scansioniConGiacenzaQuery();
        }

        return \DB::table('inventario_scansioni')
            ->where('inventario_scansioni.sessione_id', $this->sessioneId);
    }

    public function updatedBaseConfronto()
    {
        $this->calcolaStatistiche();
    }

    public function allineaQuantita()
    {
        $this->prepareAllineaQuantita();
    }

    public function finalizzaInventario()
    {
        $this->prepareFinalizzaInventario();
    }

    public function prepareAllineaQuantita()
    {
        if (!$this->sessione) {
            return;
        }

        $this->azioneCategoriaId = $this->normalizeCategoriaFiltro($this->categoriaId);
        $scansioni = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->where('azione', 'trovato')
            ->whereNotNull('quantita_trovata');
        if ($this->azioneCategoriaId) {
            $scansioni->whereHas('articolo', function ($q) {
                $q->where('magazzino_logico', $this->azioneCategoriaId);
            });
        }
        $this->previewAllinea = $scansioni->count();
        $this->showConfirmAllinea = true;
        $this->dispatch('open-confirm-modal', type: 'allinea');
    }

    public function confirmAllineaQuantita()
    {
        if (!$this->sessione) {
            return;
        }

        try {
            $service = app(\App\Services\InventarioService::class);
            $aggiornate = $service->allineaQuantitaScansionate($this->sessioneId, $this->azioneCategoriaId);
            $this->logEvento('allinea_quantita', [
                'categoria_id' => $this->azioneCategoriaId,
                'righe_aggiornate' => $aggiornate,
                'base_confronto' => $this->baseConfronto,
            ]);
            $this->calcolaStatistiche();
            $this->showConfirmAllinea = false;
            $this->dispatch('close-confirm-modal', type: 'allinea');
            $this->azioneCategoriaId = null;
            session()->flash('success', "Quantità allineate: {$aggiornate}");
        } catch (\Exception $e) {
            $this->showConfirmAllinea = false;
            $this->dispatch('close-confirm-modal', type: 'allinea');
            $this->azioneCategoriaId = null;
            session()->flash('error', 'Errore durante l\'allineamento: ' . $e->getMessage());
        }
    }

    public function prepareFinalizzaInventario()
    {
        if (!$this->sessione) {
            return;
        }

        $categoriePermesse = $this->normalizeCategoriePermesse($this->sessione->categorie_permesse);
        $this->azioneCategoriaId = $this->normalizeCategoriaFiltro($this->categoriaId);
        $daAllineareQuery = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->where('azione', 'trovato')
            ->whereNotNull('quantita_trovata');
        if ($this->azioneCategoriaId) {
            $daAllineareQuery->whereHas('articolo', function ($q) {
                $q->where('magazzino_logico', $this->azioneCategoriaId);
            });
        }
        $daAllineare = $daAllineareQuery->count();

        $articoliTrovatiQuery = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->where('azione', 'trovato')
            ->select('articolo_id');
        $articoliEliminatiQuery = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->where('azione', 'eliminato')
            ->select('articolo_id');
        if ($this->azioneCategoriaId) {
            $articoliTrovatiQuery->whereHas('articolo', function ($q) {
                $q->where('magazzino_logico', $this->azioneCategoriaId);
            });
            $articoliEliminatiQuery->whereHas('articolo', function ($q) {
                $q->where('magazzino_logico', $this->azioneCategoriaId);
            });
        }
        $articoliTrovati = $articoliTrovatiQuery->pluck('articolo_id')->toArray();
        $articoliEliminati = $articoliEliminatiQuery->pluck('articolo_id')->toArray();

        $query = Articolo::whereHas('giacenze', function ($q) {
            $q->where('sede_id', $this->sessione->sede_id)
              ->where('quantita_residua', '>', 0);
        });
        if (!empty($categoriePermesse)) {
            $query->whereIn('magazzino_logico', $categoriePermesse);
        }
        if ($this->azioneCategoriaId) {
            $query->where('magazzino_logico', $this->azioneCategoriaId);
        }
        if (!empty($articoliTrovati)) {
            $query->whereNotIn('id', $articoliTrovati);
        }
        if (!empty($articoliEliminati)) {
            $query->orWhereIn('id', $articoliEliminati);
        }

        $daRimuovere = $query->count();

        $this->previewFinalizza = [
            'da_allineare' => $daAllineare,
            'da_rimuovere' => $daRimuovere,
        ];
        $this->showConfirmFinalizza = true;
        $this->dispatch('open-confirm-modal', type: 'finalizza');
    }

    public function confirmFinalizzaInventario()
    {
        if (!$this->sessione) {
            return;
        }

        try {
            $service = app(\App\Services\InventarioService::class);
            $azioneCategoriaId = $this->azioneCategoriaId;
            if ($azioneCategoriaId) {
                $categoriaNome = 'Magazzino ' . $azioneCategoriaId;
                $service->finalizzaCategoria($this->sessioneId, $azioneCategoriaId);
                $this->logEvento('finalizza_categoria', [
                    'categoria_id' => $azioneCategoriaId,
                    'base_confronto' => $this->baseConfronto,
                ]);
                $this->lastFinalizzaReport = [
                    'sessione_id' => $this->sessioneId,
                    'sede' => $this->sessione->sede->nome ?? '',
                    'categoria' => $categoriaNome,
                    'da_allineare' => $this->previewFinalizza['da_allineare'] ?? 0,
                    'da_rimuovere' => $this->previewFinalizza['da_rimuovere'] ?? 0,
                    'base_confronto' => $this->baseConfronto,
                    'tipo' => 'categoria',
                    'chiusura_at' => now()->format('d/m/Y H:i'),
                ];
                $this->calcolaStatistiche();
            } else {
                $service->chiudiSessione($this->sessioneId);
                $this->logEvento('finalizza_inventario', [
                    'base_confronto' => $this->baseConfronto,
                ]);
                $this->lastFinalizzaReport = [
                    'sessione_id' => $this->sessioneId,
                    'sede' => $this->sessione->sede->nome ?? '',
                    'categoria' => 'Tutti i magazzini',
                    'da_allineare' => $this->previewFinalizza['da_allineare'] ?? 0,
                    'da_rimuovere' => $this->previewFinalizza['da_rimuovere'] ?? 0,
                    'base_confronto' => $this->baseConfronto,
                    'tipo' => 'totale',
                    'chiusura_at' => now()->format('d/m/Y H:i'),
                ];
                $this->caricaSessione();
            }
            $this->showConfirmFinalizza = false;
            $this->dispatch('close-confirm-modal', type: 'finalizza');
            $this->azioneCategoriaId = null;
            session()->flash('success', $azioneCategoriaId ? 'Inventario magazzino finalizzato.' : 'Inventario finalizzato con successo.');
        } catch (\Exception $e) {
            $this->showConfirmFinalizza = false;
            $this->dispatch('close-confirm-modal', type: 'finalizza');
            $this->azioneCategoriaId = null;
            session()->flash('error', 'Errore durante la finalizzazione: ' . $e->getMessage());
        }
    }

    public function exportFinalizzaReport()
    {
        if (!$this->lastFinalizzaReport) {
            return;
        }

        $filename = 'report_chiusura_' . $this->sessioneId . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
        ];
        $report = $this->lastFinalizzaReport;

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sessione', $report['sessione_id']]);
            fputcsv($handle, ['Sede', $report['sede']]);
            fputcsv($handle, ['Magazzino', $report['categoria']]);
            fputcsv($handle, ['Tipo chiusura', $report['tipo']]);
            fputcsv($handle, ['Base confronto', $report['base_confronto']]);
            fputcsv($handle, ['Chiusura', $report['chiusura_at']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Righe allineate', $report['da_allineare']]);
            fputcsv($handle, ['Articoli rimossi', $report['da_rimuovere']]);
            fclose($handle);
        }, $filename, $headers);
    }

    public function prepareBulkResolution()
    {
        if (!$this->sessione) {
            return;
        }

        $this->bulkPreviewCount = $this->buildScansioniAnomaliaQuery($this->bulkTarget)->count();
        $this->showConfirmBulk = true;
        $this->dispatch('open-confirm-modal', type: 'bulk');
    }

    public function riattivaSessione()
    {
        if (!$this->sessione) {
            return;
        }

        try {
            $service = app(\App\Services\InventarioService::class);
            $service->riattivaSessione($this->sessioneId);
            $this->caricaSessione();
            $this->logEvento('riattiva_sessione', [
                'base_confronto' => $this->baseConfronto,
            ]);
            session()->flash('success', 'Sessione riattivata.');
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la riattivazione: ' . $e->getMessage());
        }
    }

    public function confirmBulkResolution()
    {
        if (!$this->sessione) {
            return;
        }

        $ids = $this->buildScansioniAnomaliaQuery($this->bulkTarget)
            ->pluck('inventario_scansioni.articolo_id')
            ->toArray();

        if (empty($ids)) {
            $this->showConfirmBulk = false;
            $this->dispatch('close-confirm-modal', type: 'bulk');
            session()->flash('info', 'Nessun articolo da aggiornare.');
            return;
        }

        $now = now();
        $updated = 0;
        $chunks = array_chunk($ids, 200);

        foreach ($chunks as $chunk) {
            $scansioni = InventarioScansione::where('sessione_id', $this->sessioneId)
                ->whereIn('articolo_id', $chunk)
                ->get()
                ->keyBy('articolo_id');

            $giacenzeMap = collect();
            if ($this->baseConfronto === 'giacenza') {
                $giacenzeMap = \DB::table('giacenze')
                    ->select('articolo_id', \DB::raw('SUM(quantita_residua) as quantita_sistema'))
                    ->where('sede_id', $this->sessione->sede_id)
                    ->whereIn('articolo_id', $chunk)
                    ->groupBy('articolo_id')
                    ->pluck('quantita_sistema', 'articolo_id');
            }

            foreach ($chunk as $articoloId) {
                $scansione = $scansioni->get($articoloId);
                if (!$scansione) {
                    continue;
                }

                $targetSistema = $this->baseConfronto === 'giacenza'
                    ? (int) ($giacenzeMap[$articoloId] ?? 0)
                    : (int) ($scansione->quantita_sistema ?? 0);

                if ($this->bulkRule === 'set_trovata_zero') {
                    $quantitaTrovata = 0;
                } else {
                    $quantitaTrovata = $targetSistema;
                }

                $azione = $quantitaTrovata > 0 ? 'trovato' : 'eliminato';
                $differenza = $quantitaTrovata - (int) ($scansione->quantita_sistema ?? $targetSistema);

                InventarioScansione::where('id', $scansione->id)->update([
                    'azione' => $azione,
                    'quantita_trovata' => $quantitaTrovata,
                    'differenza' => $differenza,
                    'data_scansione' => $now,
                    'updated_at' => $now,
                ]);

                $updated++;
            }
        }

        $this->logEvento('bulk_risoluzione', [
            'target' => $this->bulkTarget,
            'regola' => $this->bulkRule,
            'conteggio' => $updated,
            'base_confronto' => $this->baseConfronto,
        ]);

        $this->calcolaStatistiche();
        $this->showConfirmBulk = false;
        $this->dispatch('close-confirm-modal', type: 'bulk');
        session()->flash('success', "Aggiornati {$updated} articoli.");
    }

    public function prepareSuggestedAction(int $articoloId, string $azione)
    {
        if (!$this->sessione) {
            return;
        }

        $this->pendingArticoloId = $articoloId;
        $this->pendingAction = $azione;
        $this->showConfirmAzione = true;
        $this->dispatch('open-confirm-modal', type: 'azione');
    }

    public function confirmSuggestedAction()
    {
        if (!$this->sessione || !$this->pendingArticoloId || !$this->pendingAction) {
            return;
        }

        $articoloId = $this->pendingArticoloId;
        $azione = $this->pendingAction;

        $scansione = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->where('articolo_id', $articoloId)
            ->first();
        if (!$scansione) {
            session()->flash('error', 'Scansione non trovata.');
            return;
        }

        $giacenza = \DB::table('giacenze')
            ->where('sede_id', $this->sessione->sede_id)
            ->where('articolo_id', $articoloId)
            ->sum('quantita_residua');

        $targetSistema = $this->baseConfronto === 'giacenza'
            ? (int) $giacenza
            : (int) ($scansione->quantita_sistema ?? $giacenza);

        $quantitaTrovata = $azione === 'zero' ? 0 : $targetSistema;
        $azioneFinale = $quantitaTrovata > 0 ? 'trovato' : 'eliminato';
        $differenza = $quantitaTrovata - (int) ($scansione->quantita_sistema ?? $targetSistema);

        $scansione->update([
            'azione' => $azioneFinale,
            'quantita_trovata' => $quantitaTrovata,
            'differenza' => $differenza,
            'data_scansione' => now(),
        ]);

        $this->logEvento('azione_suggerita', [
            'articolo_id' => $articoloId,
            'azione' => $azione,
            'base_confronto' => $this->baseConfronto,
        ]);

        $this->calcolaStatistiche();
        $this->showConfirmAzione = false;
        $this->pendingArticoloId = null;
        $this->pendingAction = null;
        $this->dispatch('close-confirm-modal', type: 'azione');
        session()->flash('success', 'Azione applicata.');
    }

    public function openEditArticolo(int $articoloId)
    {
        if (!$this->sessione) {
            return;
        }

        $articolo = Articolo::with('giacenze')->findOrFail($articoloId);
        $scansione = InventarioScansione::where('sessione_id', $this->sessioneId)
            ->where('articolo_id', $articoloId)
            ->first();

        $this->editingArticoloId = $articoloId;
        $this->editingQuantitaSistema = $articolo->giacenze
            ->where('sede_id', $this->sessione->sede_id)
            ->sum('quantita_residua');
        $this->editingQuantitaTrovata = $scansione?->quantita_trovata;
        $this->showEditArticolo = true;
        $this->dispatch('open-confirm-modal', type: 'edit');
    }

    public function closeEditArticolo()
    {
        $this->showEditArticolo = false;
        $this->editingArticoloId = null;
        $this->editingQuantitaTrovata = null;
        $this->editingQuantitaSistema = null;
        $this->dispatch('close-confirm-modal', type: 'edit');
    }

    public function saveEditArticolo()
    {
        if (!$this->sessione || !$this->editingArticoloId) {
            return;
        }

        $this->validate([
            'editingQuantitaTrovata' => 'nullable|integer|min:0',
            'editingQuantitaSistema' => 'nullable|integer|min:0',
        ]);

        $articolo = Articolo::findOrFail($this->editingArticoloId);

        if ($this->editingQuantitaSistema !== null) {
            \DB::table('giacenze')
                ->where('articolo_id', $articolo->id)
                ->where('sede_id', $this->sessione->sede_id)
                ->update([
                    'quantita_residua' => $this->editingQuantitaSistema,
                    'quantita' => $this->editingQuantitaSistema,
                    'ultimo_inventario_at' => now(),
                ]);
        }

        $quantitaTrovata = $this->editingQuantitaTrovata;
        $quantitaSistema = $this->editingQuantitaSistema ?? $articolo->giacenze()
            ->where('sede_id', $this->sessione->sede_id)
            ->sum('quantita_residua');

        if ($quantitaTrovata !== null) {
            $azione = $quantitaTrovata > 0 ? 'trovato' : 'eliminato';
            \DB::table('inventario_scansioni')->updateOrInsert(
                ['sessione_id' => $this->sessioneId, 'articolo_id' => $articolo->id],
                [
                    'azione' => $azione,
                    'quantita_trovata' => $quantitaTrovata,
                    'quantita_sistema' => $quantitaSistema,
                    'differenza' => $quantitaTrovata - $quantitaSistema,
                    'note' => null,
                    'data_scansione' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->logEvento('modifica_articolo', [
            'articolo_id' => $articolo->id,
            'quantita_trovata' => $quantitaTrovata,
            'quantita_sistema' => $quantitaSistema,
            'base_confronto' => $this->baseConfronto,
        ]);
        $articolo->update(['inventariato' => true]);
        $this->calcolaStatistiche();
        $this->closeEditArticolo();
        session()->flash('success', 'Articolo aggiornato.');
    }

    public function markInventariato(int $articoloId)
    {
        if (!$this->sessione) {
            return;
        }

        $articolo = Articolo::findOrFail($articoloId);
        $quantitaSistema = $articolo->giacenze()
            ->where('sede_id', $this->sessione->sede_id)
            ->sum('quantita_residua');

        \DB::table('inventario_scansioni')->updateOrInsert(
            ['sessione_id' => $this->sessioneId, 'articolo_id' => $articolo->id],
            [
                'azione' => $quantitaSistema > 0 ? 'trovato' : 'eliminato',
                'quantita_trovata' => $quantitaSistema,
                'quantita_sistema' => $quantitaSistema,
                'differenza' => 0,
                'note' => null,
                'data_scansione' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->logEvento('inventariato', [
            'articolo_id' => $articolo->id,
            'quantita_trovata' => $quantitaSistema,
            'quantita_sistema' => $quantitaSistema,
            'base_confronto' => $this->baseConfronto,
        ]);
        $articolo->update(['inventariato' => true]);
        $this->calcolaStatistiche();
        $this->actionFeedback = "Inventariato: {$articolo->codice} - {$articolo->descrizione}";
        $this->lastActionArticoloId = $articolo->id;
        $this->lastActionMessage = 'Articolo segnato come inventariato.';
        $this->resetPage();
        $this->dispatch('scroll-to-feedback');
        session()->flash('success', 'Articolo segnato come inventariato.');
    }

    public function bulkMarkInventariato()
    {
        if (!$this->sessione) {
            return;
        }

        $query = $this->buildArticoliQuery();
        $count = 0;
        $now = now();

        $query->orderBy('id')->chunk(200, function ($articoli) use (&$count, $now) {
            $upserts = [];
            $ids = [];
            foreach ($articoli as $articolo) {
                $quantitaSistema = $articolo->giacenze
                    ->where('sede_id', $this->sessione->sede_id)
                    ->sum('quantita_residua');

                $upserts[] = [
                    'sessione_id' => $this->sessioneId,
                    'articolo_id' => $articolo->id,
                    'azione' => $quantitaSistema > 0 ? 'trovato' : 'eliminato',
                    'quantita_trovata' => $quantitaSistema,
                    'quantita_sistema' => $quantitaSistema,
                    'differenza' => 0,
                    'note' => 'Bulk inventariato',
                    'data_scansione' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $ids[] = $articolo->id;
                $count++;
            }

            if (!empty($upserts)) {
                \DB::table('inventario_scansioni')->upsert(
                    $upserts,
                    ['sessione_id', 'articolo_id'],
                    ['azione', 'quantita_trovata', 'quantita_sistema', 'differenza', 'note', 'data_scansione', 'updated_at']
                );
            }
            if (!empty($ids)) {
                Articolo::whereIn('id', $ids)->update(['inventariato' => true]);
            }
        });

        $this->logEvento('bulk_inventariato', [
            'conteggio' => $count,
            'filtri' => $this->getFiltroPayload(),
            'base_confronto' => $this->baseConfronto,
        ]);
        $this->calcolaStatistiche();
        $this->actionFeedback = "Inventariati {$count} articoli (filtri attuali).";
        $this->lastActionArticoloId = null;
        $this->lastActionMessage = null;
        $this->resetPage();
        $this->dispatch('scroll-to-feedback');
        session()->flash('success', "Inventariati {$count} articoli.");
    }

    public function exportCsv()
    {
        if (!$this->sessione) {
            return;
        }

        $filename = 'inventario_' . $this->sessioneId . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Codice',
                'Descrizione',
                'Magazzino',
                'Qta sistema',
                'Qta trovata',
                'Diff',
                'Esito',
            ]);

            $query = $this->buildArticoliQuery();
            $query->orderBy('id')->chunk(200, function ($articoli) use ($handle) {
                foreach ($articoli as $articolo) {
                    $scansione = $articolo->scansioni->first();
                    $quantitaSistemaGiacenza = $articolo->giacenze
                        ->where('sede_id', $this->sessione->sede_id)
                        ->sum('quantita_residua');
                    $quantitaSistema = $this->baseConfronto === 'giacenza'
                        ? $quantitaSistemaGiacenza
                        : ($scansione?->quantita_sistema ?? null);
                    $quantitaTrovata = $scansione?->quantita_trovata;
                    $diff = $quantitaSistema !== null && $quantitaTrovata !== null
                        ? ($quantitaTrovata - $quantitaSistema)
                        : null;

                    if (!$scansione) {
                        $esito = 'Non scansionato';
                    } elseif (($quantitaTrovata ?? 0) > 0) {
                        $esito = 'Trovato';
                    } else {
                        $esito = 'Assente';
                    }

                    fputcsv($handle, [
                        $articolo->codice,
                        $articolo->descrizione,
                        $articolo->categoriaMerceologica->nome ?? '',
                        $quantitaSistema ?? '',
                        $quantitaTrovata ?? '',
                        $diff ?? '',
                        $esito,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportAnomalieCsv()
    {
        if (!$this->sessione) {
            return;
        }

        $filename = 'anomalie_' . $this->sessioneId . '_' . $this->bulkTarget . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Codice',
                'Descrizione',
                'Magazzino',
                'Qta sistema',
                'Qta trovata',
                'Diff',
                'Valore diff',
                'Tipo',
            ]);

            $base = $this->buildScansioniAnomaliaQuery($this->bulkTarget)
                ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
                ;

            if ($this->baseConfronto === 'giacenza') {
                $giacenzeSub = \DB::table('giacenze')
                    ->select(
                        'articolo_id',
                        \DB::raw('AVG(costo_unitario) as costo_unitario')
                    )
                    ->where('sede_id', $this->sessione->sede_id)
                    ->groupBy('articolo_id');
                $base->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
                    $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
                });
            }

            $diffExpr = $this->diffExpression();
            $base->selectRaw("
                articoli.codice,
                articoli.descrizione,
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COALESCE(inventario_scansioni.quantita_trovata, 0) as quantita_trovata,
                {$diffExpr} as diff,
                " . ($this->baseConfronto === 'giacenza'
                    ? "COALESCE(giacenze_sede.quantita_sistema, 0) as quantita_sistema, COALESCE(giacenze_costo.costo_unitario, 0) as costo_unitario"
                    : "COALESCE(inventario_scansioni.quantita_sistema, 0) as quantita_sistema, 0 as costo_unitario") . "
            ");

            $base->orderByRaw('ABS(diff) DESC')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    $valoreDiff = $row->diff * ($row->costo_unitario ?? 0);
                    $tipo = $row->diff > 0 ? 'eccedenza' : 'mancanza';
                    fputcsv($handle, [
                        $row->codice,
                        $row->descrizione,
                        $row->categoria ?? '',
                        $row->quantita_sistema,
                        $row->quantita_trovata,
                        $row->diff,
                        number_format($valoreDiff, 2, '.', ''),
                        $tipo,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportRegistroCsv()
    {
        if (!$this->sessione) {
            return;
        }

        $filename = 'registro_' . $this->sessioneId . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Quando',
                'Tipo',
                'Articolo',
                'Utente',
                'Payload',
            ]);

            InventarioEvento::query()
                ->with(['utente', 'articolo'])
                ->where('sessione_id', $this->sessioneId)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        $articoloLabel = $row->articolo
                            ? ($row->articolo->codice . ' - ' . $row->articolo->descrizione)
                            : '';
                        fputcsv($handle, [
                            $row->created_at?->format('d/m/Y H:i'),
                            $row->tipo,
                            $articoloLabel,
                            $row->utente->name ?? '',
                            $row->payload ? json_encode($row->payload) : '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportNonScansionatiCsv()
    {
        if (!$this->sessione) {
            return;
        }

        $filename = 'non_scansionati_' . $this->sessioneId . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Codice',
                'Descrizione',
                'Magazzino',
                'Qta sistema',
            ]);

            $query = Articolo::query()
                ->with(['categoriaMerceologica', 'giacenze'])
                ->whereHas('giacenze', function ($q) {
                    $q->where('sede_id', $this->sessione->sede_id)
                      ->where('quantita_residua', '>', 0);
                });

            if ($this->sessione->categorie_permesse && !empty($this->sessione->categorie_permesse)) {
                $query->whereIn('magazzino_logico', $this->normalizeCategoriePermesse($this->sessione->categorie_permesse));
            }

            if ($this->categoriaId) {
                $query->where('magazzino_logico', $this->normalizeCategoriaFiltro($this->categoriaId));
            }

            $scansionati = InventarioScansione::where('sessione_id', $this->sessioneId)
                ->pluck('articolo_id')
                ->toArray();
            if (!empty($scansionati)) {
                $query->whereNotIn('id', $scansionati);
            }

            $query->orderBy('id')->chunk(200, function ($articoli) use ($handle) {
                foreach ($articoli as $articolo) {
                    $quantitaSistema = $articolo->giacenze
                        ->where('sede_id', $this->sessione->sede_id)
                        ->sum('quantita_residua');
                    fputcsv($handle, [
                        $articolo->codice,
                        $articolo->descrizione,
                        $articolo->categoriaMerceologica->nome ?? '',
                        $quantitaSistema,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportFullAuditCsv()
    {
        if (!$this->sessione) {
            return;
        }

        $filename = 'audit_inventario_' . $this->sessioneId . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        $this->logEvento('export_audit', [
            'base_confronto' => $this->baseConfronto,
        ]);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Sezione: Anomalie (tutte)
            fputcsv($handle, ['[ANOMALIE]']);
            fputcsv($handle, [
                'Codice',
                'Descrizione',
                'Magazzino',
                'Qta sistema',
                'Qta trovata',
                'Diff',
                'Valore diff',
                'Tipo',
            ]);

            $base = $this->scansioniBaseQuery()
                ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
                ;

            if ($this->baseConfronto === 'giacenza') {
                $giacenzeSub = \DB::table('giacenze')
                    ->select(
                        'articolo_id',
                        \DB::raw('AVG(costo_unitario) as costo_unitario')
                    )
                    ->where('sede_id', $this->sessione->sede_id)
                    ->groupBy('articolo_id');
                $base->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
                    $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
                });
            }

            $diffExpr = $this->diffExpression();
            $base->selectRaw("
                articoli.codice,
                articoli.descrizione,
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COALESCE(inventario_scansioni.quantita_trovata, 0) as quantita_trovata,
                {$diffExpr} as diff,
                " . ($this->baseConfronto === 'giacenza'
                    ? "COALESCE(giacenze_sede.quantita_sistema, 0) as quantita_sistema, COALESCE(giacenze_costo.costo_unitario, 0) as costo_unitario"
                    : "COALESCE(inventario_scansioni.quantita_sistema, 0) as quantita_sistema, 0 as costo_unitario") . "
            ");
            $base->whereRaw("{$diffExpr} != 0")->orderByRaw('ABS(diff) DESC')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    $valoreDiff = $row->diff * ($row->costo_unitario ?? 0);
                    $tipo = $row->diff > 0 ? 'eccedenza' : 'mancanza';
                    fputcsv($handle, [
                        $row->codice,
                        $row->descrizione,
                        $row->categoria ?? '',
                        $row->quantita_sistema,
                        $row->quantita_trovata,
                        $row->diff,
                        number_format($valoreDiff, 2, '.', ''),
                        $tipo,
                    ]);
                }
            });

            fputcsv($handle, []);

            // Sezione: Non scansionati
            fputcsv($handle, ['[NON_SCANSIONATI]']);
            fputcsv($handle, [
                'Codice',
                'Descrizione',
                'Magazzino',
                'Qta sistema',
            ]);

            $query = Articolo::query()
                ->with(['categoriaMerceologica', 'giacenze'])
                ->whereHas('giacenze', function ($q) {
                    $q->where('sede_id', $this->sessione->sede_id)
                      ->where('quantita_residua', '>', 0);
                });

            if ($this->sessione->categorie_permesse && !empty($this->sessione->categorie_permesse)) {
                $query->whereIn('magazzino_logico', $this->normalizeCategoriePermesse($this->sessione->categorie_permesse));
            }

            $scansionati = InventarioScansione::where('sessione_id', $this->sessioneId)
                ->pluck('articolo_id')
                ->toArray();
            if (!empty($scansionati)) {
                $query->whereNotIn('id', $scansionati);
            }

            $query->orderBy('id')->chunk(200, function ($articoli) use ($handle) {
                foreach ($articoli as $articolo) {
                    $quantitaSistema = $articolo->giacenze
                        ->where('sede_id', $this->sessione->sede_id)
                        ->sum('quantita_residua');
                    fputcsv($handle, [
                        $articolo->codice,
                        $articolo->descrizione,
                        $articolo->categoriaMerceologica->nome ?? '',
                        $quantitaSistema,
                    ]);
                }
            });

            fputcsv($handle, []);

            // Sezione: Registro
            fputcsv($handle, ['[REGISTRO]']);
            fputcsv($handle, [
                'Quando',
                'Tipo',
                'Articolo',
                'Utente',
                'Payload',
            ]);

            InventarioEvento::query()
                ->with(['utente', 'articolo'])
                ->where('sessione_id', $this->sessioneId)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        $articoloLabel = $row->articolo
                            ? ($row->articolo->codice . ' - ' . $row->articolo->descrizione)
                            : '';
                        fputcsv($handle, [
                            $row->created_at?->format('d/m/Y H:i'),
                            $row->tipo,
                            $articoloLabel,
                            $row->utente->name ?? '',
                            $row->payload ? json_encode($row->payload) : '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    private function getEventiRecenti()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        if (!Schema::hasTable('inventario_eventi')) {
            return collect();
        }

        return InventarioEvento::query()
            ->with(['utente', 'articolo'])
            ->where('sessione_id', $this->sessioneId)
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    private function getTopAnomalie()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');

        return $base
            ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            
            ->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
                $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
            })
            ->selectRaw("
                inventario_scansioni.articolo_id,
                articoli.codice,
                articoli.descrizione,
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COALESCE(inventario_scansioni.quantita_trovata, 0) as quantita_trovata,
                {$diffExpr} as diff,
                COALESCE(giacenze_costo.costo_unitario, 0) as costo_unitario
            ")
            ->whereRaw("{$diffExpr} != 0")
            ->orderByRaw('ABS(diff) DESC')
            ->limit(20)
            ->get();
    }

    private function getReportAnomalie()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery()
            ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            ;

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');
        $base->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
            $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
        });

        $diffExpr = $this->diffExpression();
        $valoreDiffExpr = "SUM({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0))";

        return $base
            ->selectRaw("
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COUNT(*) as righe,
                SUM(CASE WHEN {$diffExpr} > 0 THEN 1 ELSE 0 END) as eccedenze,
                SUM(CASE WHEN {$diffExpr} < 0 THEN 1 ELSE 0 END) as mancanze,
                SUM({$diffExpr}) as diff_totale,
                {$valoreDiffExpr} as valore_diff
            ")
            ->whereRaw("{$diffExpr} != 0")
            ->groupBy('articoli.magazzino_logico')
            ->orderByRaw("ABS({$valoreDiffExpr}) DESC")
            ->limit(30)
            ->get();
    }

    private function getKpiTimeline()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');
        $base->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
            $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
        });

        if ($this->sessione?->data_inizio) {
            $from = $this->sessione->data_inizio->copy()->startOfDay();
            $to = ($this->sessione->data_fine ?? now())->copy()->endOfDay();
            $base->whereBetween('inventario_scansioni.data_scansione', [$from, $to]);
        }

        return $base
            ->selectRaw("
                DATE(inventario_scansioni.data_scansione) as giorno,
                COUNT(*) as scansioni,
                SUM(CASE WHEN inventario_scansioni.azione = 'trovato' THEN 1 ELSE 0 END) as trovati,
                SUM(CASE WHEN inventario_scansioni.azione = 'eliminato' THEN 1 ELSE 0 END) as eliminati,
                SUM({$diffExpr}) as diff_totale,
                SUM({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0)) as valore_diff
            ")
            ->groupBy('giorno')
            ->orderByDesc('giorno')
            ->limit(90)
            ->get();
    }

    private function getKpiPerCategoria()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        $base->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            ;

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');
        $base->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
            $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
        });
        $valoreDiffExpr = "SUM({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0))";

        return $base
            ->selectRaw("
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COUNT(*) as scansioni,
                SUM(CASE WHEN inventario_scansioni.azione = 'trovato' THEN 1 ELSE 0 END) as trovati,
                SUM(CASE WHEN inventario_scansioni.azione = 'eliminato' THEN 1 ELSE 0 END) as eliminati,
                SUM({$diffExpr}) as diff_totale,
                {$valoreDiffExpr} as valore_diff
            ")
            ->groupBy('articoli.magazzino_logico')
            ->orderByRaw("ABS({$valoreDiffExpr}) DESC")
            ->limit(30)
            ->get();
    }

    private function getHeatmapAnomalie()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        $base->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            ;

        return $base
            ->selectRaw("
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                SUM(CASE WHEN ABS({$diffExpr}) >= ? THEN 1 ELSE 0 END) as critiche,
                SUM(CASE WHEN ABS({$diffExpr}) >= ? AND ABS({$diffExpr}) < ? THEN 1 ELSE 0 END) as medie,
                SUM(CASE WHEN ABS({$diffExpr}) > 0 AND ABS({$diffExpr}) < ? THEN 1 ELSE 0 END) as basse,
                COUNT(*) as totali
            ", [
                (int) $this->heatmapDiffHigh,
                (int) $this->heatmapDiffMedium,
                (int) $this->heatmapDiffHigh,
                (int) $this->heatmapDiffMedium,
            ])
            ->whereRaw("{$diffExpr} != 0")
            ->groupBy('articoli.magazzino_logico')
            ->orderByRaw('critiche DESC, medie DESC, basse DESC')
            ->limit(30)
            ->get();
    }

    private function getTopAnomalieValore()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');

        return $base
            ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            
            ->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
                $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
            })
            ->selectRaw("
                inventario_scansioni.articolo_id,
                articoli.codice,
                articoli.descrizione,
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COALESCE(inventario_scansioni.quantita_trovata, 0) as quantita_trovata,
                {$diffExpr} as diff,
                COALESCE(giacenze_costo.costo_unitario, 0) as costo_unitario,
                ({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0)) as valore_diff
            ")
            ->whereRaw("{$diffExpr} != 0")
            ->orderByRaw('ABS(valore_diff) DESC')
            ->limit(20)
            ->get();
    }

    private function getAzioniConsigliate()
    {
        if (!$this->sessioneId) {
            return collect();
        }

        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');

        return $base
            ->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
            
            ->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
                $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
            })
            ->selectRaw("
                inventario_scansioni.articolo_id,
                articoli.codice,
                articoli.descrizione,
                CONCAT('Magazzino ', articoli.magazzino_logico) as categoria,
                COALESCE(inventario_scansioni.quantita_trovata, 0) as quantita_trovata,
                {$diffExpr} as diff,
                COALESCE(giacenze_costo.costo_unitario, 0) as costo_unitario,
                ({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0)) as valore_diff
            ")
            ->whereRaw("{$diffExpr} != 0")
            ->orderByRaw('ABS(valore_diff) DESC')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $row->azione = $row->diff > 0
                    ? 'Eccedenza: verifica carico/giacenza e causale'
                    : (($row->quantita_trovata > 0)
                        ? 'Parziale: verifica conteggio o allinea quantità'
                        : 'Mancanza: verifica scarichi o allinea a zero');
                return $row;
            });
    }

    private function logEvento(string $tipo, array $payload = []): void
    {
        if (!$this->sessioneId) {
            return;
        }

        InventarioEvento::create([
            'sessione_id' => $this->sessioneId,
            'articolo_id' => $payload['articolo_id'] ?? null,
            'sede_id' => $this->sessione?->sede_id,
            'user_id' => auth()->id(),
            'tipo' => $tipo,
            'payload' => $payload,
        ]);
    }

    private function buildArticoliQuery()
    {
        $skipGiacenzeFilter = in_array($this->statoArticolo, ['eccedenze', 'parziali', 'incongruenze'], true);

        $query = Articolo::query()
            ->with([
                'categoriaMerceologica',
                'giacenze',
                'scansioni' => function ($q) {
                    $q->where('sessione_id', $this->sessioneId);
                },
            ]);

        if (!$skipGiacenzeFilter) {
            $query->whereHas('giacenze', function ($q) {
                $q->where('sede_id', $this->sessione->sede_id)
                  ->where('quantita_residua', '>', 0);
            });
        }

        if ($this->sessione->categorie_permesse && !empty($this->sessione->categorie_permesse)) {
            $query->whereIn('magazzino_logico', $this->normalizeCategoriePermesse($this->sessione->categorie_permesse));
        }

        if ($this->categoriaId) {
            $query->where('magazzino_logico', $this->normalizeCategoriaFiltro($this->categoriaId));
        }

        switch ($this->statoArticolo) {
            case 'trovati':
                $articoliTrovati = InventarioScansione::where('sessione_id', $this->sessioneId)
                    ->where('azione', 'trovato')
                    ->pluck('articolo_id')
                    ->toArray();
                if (!empty($articoliTrovati)) {
                    $query->whereIn('id', $articoliTrovati);
                } else {
                    $query->where('id', 0);
                }
                break;
            case 'mancanti':
                $articoliEliminati = InventarioScansione::where('sessione_id', $this->sessioneId)
                    ->where('azione', 'eliminato')
                    ->pluck('articolo_id')
                    ->toArray();
                if (!empty($articoliEliminati)) {
                    $query->whereIn('id', $articoliEliminati);
                } else {
                    $query->where('id', 0);
                }
                break;
            case 'non_scansionati':
                $articoliScansionati = InventarioScansione::where('sessione_id', $this->sessioneId)
                    ->pluck('articolo_id')
                    ->toArray();
                if (!empty($articoliScansionati)) {
                    $query->whereNotIn('id', $articoliScansionati);
                }
                break;
            case 'eccedenze':
                $articoliEccedenze = $this->scansioniBaseQuery()
                    ->whereRaw("{$this->diffExpression()} > 0")
                    ->pluck('inventario_scansioni.articolo_id')
                    ->toArray();
                if (!empty($articoliEccedenze)) {
                    $query->whereIn('id', $articoliEccedenze);
                } else {
                    $query->where('id', 0);
                }
                break;
            case 'parziali':
                $articoliParziali = $this->scansioniBaseQuery()
                    ->whereNotNull('inventario_scansioni.quantita_trovata')
                    ->whereRaw("COALESCE(inventario_scansioni.quantita_trovata, 0) > 0 AND {$this->diffExpression()} < 0")
                    ->pluck('inventario_scansioni.articolo_id')
                    ->toArray();
                if (!empty($articoliParziali)) {
                    $query->whereIn('id', $articoliParziali);
                } else {
                    $query->where('id', 0);
                }
                break;
            case 'incongruenze':
                $articoliIncongruenti = $this->scansioniBaseQuery()
                    ->whereRaw("{$this->diffExpression()} != 0")
                    ->pluck('inventario_scansioni.articolo_id')
                    ->toArray();
                if (!empty($articoliIncongruenti)) {
                    $query->whereIn('id', $articoliIncongruenti);
                } else {
                    $query->where('id', 0);
                }
                break;
        }

        return $query;
    }

    private function buildScansioniAnomaliaQuery(string $target)
    {
        $base = $this->scansioniBaseQuery();
        $diffExpr = $this->diffExpression();

        if ($this->categoriaId) {
            $base->join('articoli', 'inventario_scansioni.articolo_id', '=', 'articoli.id')
                ->where('articoli.magazzino_logico', $this->normalizeCategoriaFiltro($this->categoriaId));
        }

        $giacenzeSub = \DB::table('giacenze')
            ->select(
                'articolo_id',
                \DB::raw('AVG(costo_unitario) as costo_unitario')
            )
            ->where('sede_id', $this->sessione->sede_id)
            ->groupBy('articolo_id');
        $base->leftJoinSub($giacenzeSub, 'giacenze_costo', function ($join) {
            $join->on('inventario_scansioni.articolo_id', '=', 'giacenze_costo.articolo_id');
        });

        switch ($target) {
            case 'eccedenze':
                $base->whereRaw("{$diffExpr} > 0");
                break;
            case 'parziali':
                $base->whereRaw("COALESCE(inventario_scansioni.quantita_trovata, 0) > 0 AND {$diffExpr} < 0");
                break;
            default:
                $base->whereRaw("{$diffExpr} != 0");
                break;
        }

        if ($this->bulkDiffMin !== null && $this->bulkDiffMin !== '') {
            $base->whereRaw("ABS({$diffExpr}) >= ?", [(int) $this->bulkDiffMin]);
        }
        if ($this->bulkDiffMax !== null && $this->bulkDiffMax !== '') {
            $base->whereRaw("ABS({$diffExpr}) <= ?", [(int) $this->bulkDiffMax]);
        }

        if ($this->bulkValoreMin !== null && $this->bulkValoreMin !== '') {
            $base->whereRaw("ABS({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0)) >= ?", [(float) $this->bulkValoreMin]);
        }
        if ($this->bulkValoreMax !== null && $this->bulkValoreMax !== '') {
            $base->whereRaw("ABS({$diffExpr} * COALESCE(giacenze_costo.costo_unitario, 0)) <= ?", [(float) $this->bulkValoreMax]);
        }

        return $base;
    }

    private function getFiltroPayload(): array
    {
        return [
            'categoria_id' => $this->categoriaId,
            'stato' => $this->statoArticolo,
            'base_confronto' => $this->baseConfronto,
        ];
    }
}
