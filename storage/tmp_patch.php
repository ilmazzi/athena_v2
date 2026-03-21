<?php
$path = 'c:/Users/dmazz/Herd/athena_v2/app/Http/Livewire/InventarioMonitor.php';
$content = file_get_contents($path);
$content = str_replace("\r\n", "\n", $content);

$verify = <<<'CODE'
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

    public function confrontaConArticoli
CODE;
$content = preg_replace('~    public function verificaDati\(\)\n    \{.*?\n\n    public function confrontaConArticoli~s', $verify, $content, 1);

$prepareFinalizza = <<<'CODE'
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

    public function confirmFinalizzaInventario
CODE;
$content = preg_replace('~    public function prepareFinalizzaInventario\(\)\n    \{.*?\n\n    public function confirmFinalizzaInventario~s', $prepareFinalizza, $content, 1);

$confirmFinalizza = <<<'CODE'
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

    public function exportFinalizzaReport
CODE;
$content = preg_replace('~    public function confirmFinalizzaInventario\(\)\n    \{.*?\n\n    public function exportFinalizzaReport~s', $confirmFinalizza, $content, 1);

file_put_contents($path, str_replace("\n", "\r\n", $content));
