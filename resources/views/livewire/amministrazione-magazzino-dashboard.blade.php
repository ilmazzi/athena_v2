<div>
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <iconify-icon icon="solar:chart-2-bold-duotone" class="me-2"></iconify-icon>
                        Amministrazione Magazzini
                    </h4>
                    <p class="text-muted mb-0">Gestione e valorizzazione magazzini - Dashboard amministrativa</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="small text-muted">Ultimo aggiornamento: {{ $statisticheCachedAt ?? '—' }}</div>
                        <div class="small text-muted">Cache 15 min</div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" wire:click="refreshStatistiche">
                        <iconify-icon icon="solar:refresh-bold-duotone" class="me-1"></iconify-icon>
                        Aggiorna statistiche
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Statistiche Globali --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-light-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:box-bold-duotone" class="fs-2 text-primary"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ number_format($statistiche['totale_articoli'], 0, ',', '.') }}</h5>
                            <p class="text-muted mb-0 small">Articoli Giacenti</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:wallet-bold-duotone" class="fs-2 text-success"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">€{{ number_format($statistiche['totale_valore'], 2, ',', '.') }}</h5>
                            <p class="text-muted mb-0 small">Valorizzazione Totale</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:danger-triangle-bold-duotone" class="fs-2 text-warning"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ number_format($statistiche['articoli_senza_costo'], 0, ',', '.') }}</h5>
                            <p class="text-muted mb-0 small">Articoli Senza Costo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-info">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:scale-bold-duotone" class="fs-2 text-info"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ number_format($statistiche['totale_quantita'], 0, ',', '.') }}</h5>
                            <p class="text-muted mb-0 small">Quantità Totale</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtri --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Cerca</label>
                    <input type="text" class="form-control" wire:model.live="search" 
                           placeholder="Codice, descrizione...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Ubicazione fisica</label>
                    <select class="form-select" wire:model.live="sedeId">
                        <option value="">Tutte</option>
                        @foreach($sedi as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Fornitore</label>
                    <select class="form-select" wire:model.live="fornitoreId">
                        <option value="">Tutti</option>
                        @foreach($fornitori as $fornitore)
                            <option value="{{ $fornitore->id }}">{{ $fornitore->ragione_sociale }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Categoria</label>
                    <select class="form-select" wire:model.live="categoriaId">
                        <option value="">Tutte</option>
                        @foreach($categorie as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Doc. carico prima del</label>
                    <input type="date" class="form-control" wire:model.live="dataDocumentoCaricoPrimaDi">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Conto deposito</label>
                    <select class="form-select" wire:model.live="filtroContoDeposito">
                        <option value="tutti">Tutti</option>
                        <option value="solo_reale">Solo magazzino reale</option>
                        <option value="solo_conto_deposito">Solo conto deposito</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Opzioni</label>
                    <div class="d-flex gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model.live="soloSenzaCosto" id="soloSenzaCosto">
                            <label class="form-check-label small" for="soloSenzaCosto">
                                Solo senza costo
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model.live="soloGiacenti" id="soloGiacenti">
                            <label class="form-check-label small" for="soloGiacenti">
                                Solo giacenti
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-1 d-flex align-items-end gap-2">
                    <button class="btn btn-light w-100" wire:click="resetFiltri">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($this->hasDrilldownFilters)
        <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 mb-4">
            <span class="small text-muted fw-semibold me-2">Filtri attivi:</span>
            @if($sedeId)
                @php
                    $sedeLabel = optional($sedi->firstWhere('id', $sedeId))->nome ?? ('Sede ' . $sedeId);
                @endphp
                <button class="btn btn-sm btn-outline-primary" wire:click="clearSedeFilter">
                    Ubicazione: {{ $sedeLabel }}
                    <iconify-icon icon="solar:close-circle-bold" class="ms-1"></iconify-icon>
                </button>
            @endif
            @if($fornitoreId)
                @php
                    $fornitoreLabel = optional($fornitori->firstWhere('id', $fornitoreId))->ragione_sociale ?? $fornitoreId;
                @endphp
                <button class="btn btn-sm btn-outline-primary" wire:click="clearFornitoreFilter">
                    Fornitore: {{ $fornitoreLabel }}
                    <iconify-icon icon="solar:close-circle-bold" class="ms-1"></iconify-icon>
                </button>
            @endif
            @if($categoriaId)
                @php
                    $categoriaLabel = optional($categorie->firstWhere('id', $categoriaId))->nome ?? ('Categoria ' . $categoriaId);
                @endphp
                <button class="btn btn-sm btn-outline-primary" wire:click="clearCategoriaFilter">
                    Categoria: {{ $categoriaLabel }}
                    @if($filtroContoDeposito === 'solo_reale')
                        · solo reale
                    @elseif($filtroContoDeposito === 'solo_conto_deposito')
                        · solo conto deposito
                    @else
                        · reale + conto deposito
                    @endif
                    <iconify-icon icon="solar:close-circle-bold" class="ms-1"></iconify-icon>
                </button>
            @endif
            @if($marcaId)
                <button class="btn btn-sm btn-outline-primary" wire:click="clearMarcaFilter">
                    Marca: {{ $marcaId }}
                    <iconify-icon icon="solar:close-circle-bold" class="ms-1"></iconify-icon>
                </button>
            @endif
            <button class="btn btn-sm btn-outline-secondary ms-auto" wire:click="resetFiltri">
                <iconify-icon icon="solar:undo-left-bold" class="me-1"></iconify-icon>
                Torna alla vista completa
            </button>
        </div>
    @endif

    {{-- Export e Confronto --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Export Statistiche</label>
                    <div class="btn-group w-100">
                        <button class="btn btn-success btn-sm" wire:click="exportExcel">
                            <iconify-icon icon="solar:file-export-bold" class="me-1"></iconify-icon>
                            Excel
                        </button>
                        <button class="btn btn-danger btn-sm" wire:click="exportPdf">
                            <iconify-icon icon="solar:file-export-bold" class="me-1"></iconify-icon>
                            PDF
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model.live="mostraConfronto" id="mostraConfronto">
                        <label class="form-check-label small" for="mostraConfronto">
                            Confronto Periodi
                        </label>
                    </div>
                </div>
                @if($mostraConfronto)
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Data Inizio</label>
                        <input type="date" class="form-control" wire:model.live="dataInizio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Data Fine</label>
                        <input type="date" class="form-control" wire:model.live="dataFine">
                    </div>
                @endif
            </div>
            
            @if($mostraConfronto && $statisticheConfronto)
                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="fw-bold mb-3">Confronto Valorizzazione</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Valore {{ $dataInizio ? Carbon\Carbon::parse($dataInizio)->format('d/m/Y') : 'Precedente' }}:</small>
                            <div class="h5 mb-0">€{{ number_format($statisticheConfronto['valore_precedente'], 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Valore Attuale:</small>
                            <div class="h5 mb-0 text-success">€{{ number_format($statisticheConfronto['valore_attuale'], 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Variazione:</small>
                            <div class="h5 mb-0 @if($statisticheConfronto['variazione'] >= 0) text-success @else text-danger @endif">
                                €{{ number_format($statisticheConfronto['variazione'], 2, ',', '.') }}
                                ({{ number_format($statisticheConfronto['variazione_percentuale'], 2, ',', '.') }}%)
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabs Statistiche --}}
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <ul class="nav nav-tabs card-header-tabs border-0" role="tablist">
                <li class="nav-item">
                    <button class="nav-link @if($viewStatistiche == 'globale') active @endif" 
                            wire:click="$set('viewStatistiche', 'globale')" 
                            type="button">
                        Globale
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($viewStatistiche == 'sede') active @endif" 
                            wire:click="$set('viewStatistiche', 'sede')" 
                            type="button">
                        Per Sede
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($viewStatistiche == 'fornitore') active @endif" 
                            wire:click="$set('viewStatistiche', 'fornitore')" 
                            type="button">
                        Per Fornitore
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($viewStatistiche == 'categoria') active @endif" 
                            wire:click="$set('viewStatistiche', 'categoria')" 
                            type="button">
                        Per Categoria
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link @if($viewStatistiche == 'marca') active @endif" 
                            wire:click="$set('viewStatistiche', 'marca')" 
                            type="button">
                        Per Marca
                        @if(!empty($statistiche['per_marca']))
                            <span class="badge bg-light-primary text-primary ms-1">{{ count($statistiche['per_marca']) }}</span>
                        @endif
                    </button>
                </li>
            </ul>
            @if($categoriaId)
                <span class="badge bg-light-primary text-primary">
                    {{ optional($categorie->firstWhere('id', $categoriaId))->nome ?? ('Categoria ' . $categoriaId) }}
                    @if($filtroContoDeposito === 'solo_reale')
                        · solo reale
                    @elseif($filtroContoDeposito === 'solo_conto_deposito')
                        · solo conto deposito
                    @else
                        · reale + conto deposito
                    @endif
                </span>
            @endif
            </div>
            @if($categoriaId && $sedeId)
                <div class="mt-2 small text-muted">
                    I totali del magazzino restano calcolati sul magazzino logico di appartenenza. L'ubicazione fisica serve come vista di dislocazione interna.
                </div>
            @endif
        </div>
        <div class="card-body">
            @if($categoriaId && !empty($statistiche['per_sede']))
                @php
                    $distribuzionePerSede = collect($statistiche['per_sede']);
                @endphp
                <div class="mb-4 p-3 bg-light rounded border">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h6 class="mb-1 fw-bold">Distribuzione Fisica Del Magazzino</h6>
                            <small class="text-muted">
                                Gli articoli continuano a contare nel magazzino logico selezionato, ma qui vedi dove sono dislocati fisicamente.
                            </small>
                        </div>
                        <span class="badge bg-light-primary text-primary">
                            {{ $distribuzionePerSede->count() }} sedi coinvolte
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ubicazione fisica</th>
                                    <th class="text-end">Articoli</th>
                                    <th class="text-end">Quantità</th>
                                    <th class="text-end">Valorizzazione</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($distribuzionePerSede as $sedeDistribuzione)
                                    <tr>
                                        <td><strong>{{ $sedeDistribuzione['nome'] }}</strong></td>
                                        <td class="text-end">{{ number_format($sedeDistribuzione['articoli'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($sedeDistribuzione['quantita'], 0, ',', '.') }}</td>
                                        <td class="text-end">
                                            <strong class="text-success">€{{ number_format($sedeDistribuzione['valore'], 2, ',', '.') }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>TOTALE DISTRIBUITO</th>
                                    <th class="text-end">{{ number_format($distribuzionePerSede->sum('articoli'), 0, ',', '.') }}</th>
                                    <th class="text-end">{{ number_format($distribuzionePerSede->sum('quantita'), 0, ',', '.') }}</th>
                                    <th class="text-end">
                                        <strong class="text-success">€{{ number_format($distribuzionePerSede->sum('valore'), 2, ',', '.') }}</strong>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            @if($viewStatistiche == 'sede')
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="cursor: pointer;" wire:click="ordinaStatistiche('nome')" class="user-select-none">
                                    Sede
                                    @if($sortStatisticheField == 'nome')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('articoli')">
                                    Articoli
                                    @if($sortStatisticheField == 'articoli')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('quantita')">
                                    Quantità
                                    @if($sortStatisticheField == 'quantita')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('valore')">
                                    Valorizzazione
                                    @if($sortStatisticheField == 'valore')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statistiche['per_sede'] as $sedeId => $sedeData)
                                <tr style="cursor: pointer;" 
                                    wire:click="filtraPerSede('{{ $sedeId }}')"
                                    class="hover-row"
                                    title="Clicca per applicare questo filtro">
                                    <td>
                                        <strong>{{ $sedeData['nome'] }}</strong>
                                        <iconify-icon icon="solar:arrow-right-bold" class="text-muted ms-1 small"></iconify-icon>
                                    </td>
                                    <td class="text-end">{{ number_format($sedeData['articoli'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($sedeData['quantita'], 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <strong class="text-success">€{{ number_format($sedeData['valore'], 2, ',', '.') }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>TOTALE</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['articoli'], 0, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['quantita'], 0, ',', '.') }}</th>
                                <th class="text-end">
                                    <strong class="text-success">€{{ number_format($this->totaliVistaCorrente['valore'], 2, ',', '.') }}</strong>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @elseif($viewStatistiche == 'fornitore')
                {{-- Barra ricerca fornitori --}}
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <iconify-icon icon="solar:magnifer-bold"></iconify-icon>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               wire:model.live.debounce.300ms="searchStatistiche"
                               placeholder="Cerca fornitore...">
                        @if($searchStatistiche)
                            <button class="btn btn-outline-secondary" wire:click="$set('searchStatistiche', '')" type="button">
                                <iconify-icon icon="solar:close-circle-bold"></iconify-icon>
                            </button>
                        @endif
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="cursor: pointer;" wire:click="ordinaStatistiche('nome')" class="user-select-none">
                                    Fornitore
                                    @if($sortStatisticheField == 'nome')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('articoli')">
                                    Articoli
                                    @if($sortStatisticheField == 'articoli')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('quantita')">
                                    Quantità
                                    @if($sortStatisticheField == 'quantita')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('valore')">
                                    Valorizzazione
                                    @if($sortStatisticheField == 'valore')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $fornitoriVisibili = $this->fornitoriVisibili;
                                $totaleFornitori = count($statistiche['per_fornitore'] ?? []);
                                $mostrati = count($fornitoriVisibili);
                            @endphp
                            @foreach($fornitoriVisibili as $fornitoreId => $fornitoreData)
                                <tr style="cursor: pointer;" 
                                    wire:click="filtraPerFornitore('{{ $fornitoreId }}')"
                                    class="hover-row"
                                    title="Clicca per applicare questo filtro">
                                    <td>
                                        <strong>{{ $fornitoreData['nome'] }}</strong>
                                        <iconify-icon icon="solar:arrow-right-bold" class="text-muted ms-1 small"></iconify-icon>
                                    </td>
                                    <td class="text-end">{{ number_format($fornitoreData['articoli'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($fornitoreData['quantita'], 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <strong class="text-success">€{{ number_format($fornitoreData['valore'], 2, ',', '.') }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            @if($totaleFornitori > $limiteFornitori && empty($searchStatistiche))
                                <tr>
                                    <td colspan="4" class="text-center py-3">
                                        <button class="btn btn-sm btn-outline-primary" wire:click="toggleMostraTuttiFornitori">
                                            @if($mostraTuttiFornitori)
                                                <iconify-icon icon="solar:double-alt-arrow-up-bold" class="me-1"></iconify-icon>
                                                Mostra meno ({{ $limiteFornitori }} fornitori)
                                            @else
                                                <iconify-icon icon="solar:double-alt-arrow-down-bold" class="me-1"></iconify-icon>
                                                Mostra tutti ({{ $totaleFornitori }} fornitori totali)
                                            @endif
                                        </button>
                                        <small class="text-muted d-block mt-2">
                                            Mostrati {{ $mostrati }} di {{ $totaleFornitori }} fornitori
                                        </small>
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <th>TOTALE</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['articoli'], 0, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['quantita'], 0, ',', '.') }}</th>
                                <th class="text-end">
                                    <strong class="text-success">€{{ number_format($this->totaliVistaCorrente['valore'], 2, ',', '.') }}</strong>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @elseif($viewStatistiche == 'categoria')
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="cursor: pointer;" wire:click="ordinaStatistiche('nome')" class="user-select-none">
                                    Categoria
                                    @if($sortStatisticheField == 'nome')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('articoli')">
                                    Articoli
                                    @if($sortStatisticheField == 'articoli')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('quantita')">
                                    Quantità
                                    @if($sortStatisticheField == 'quantita')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('valore')">
                                    Valorizzazione
                                    @if($sortStatisticheField == 'valore')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statistiche['per_categoria'] as $categoriaId => $categoriaData)
                                <tr style="cursor: pointer;" 
                                    wire:click="filtraPerCategoria('{{ $categoriaId }}')"
                                    class="hover-row"
                                    title="Clicca per applicare questo filtro">
                                    <td>
                                        <strong>{{ $categoriaData['nome'] }}</strong>
                                        <iconify-icon icon="solar:arrow-right-bold" class="text-muted ms-1 small"></iconify-icon>
                                    </td>
                                    <td class="text-end">{{ number_format($categoriaData['articoli'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($categoriaData['quantita'], 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <strong class="text-success">€{{ number_format($categoriaData['valore'], 2, ',', '.') }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>TOTALE</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['articoli'], 0, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['quantita'], 0, ',', '.') }}</th>
                                <th class="text-end">
                                    <strong class="text-success">€{{ number_format($this->totaliVistaCorrente['valore'], 2, ',', '.') }}</strong>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @elseif($viewStatistiche == 'marca')
                {{-- Barra ricerca marche --}}
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <iconify-icon icon="solar:magnifer-bold"></iconify-icon>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               wire:model.live.debounce.300ms="searchStatistiche"
                               placeholder="Cerca marca...">
                        @if($searchStatistiche)
                            <button class="btn btn-outline-secondary" wire:click="$set('searchStatistiche', '')" type="button">
                                <iconify-icon icon="solar:close-circle-bold"></iconify-icon>
                            </button>
                        @endif
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="cursor: pointer;" wire:click="ordinaStatistiche('nome')" class="user-select-none">
                                    Marca
                                    @if($sortStatisticheField == 'nome')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('articoli')">
                                    Articoli
                                    @if($sortStatisticheField == 'articoli')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('quantita')">
                                    Quantità
                                    @if($sortStatisticheField == 'quantita')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaStatistiche('valore')">
                                    Valorizzazione
                                    @if($sortStatisticheField == 'valore')
                                        <iconify-icon icon="solar:{{ $sortStatisticheDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $marcheVisibili = $this->marcheVisibili;
                                $totaleMarche = count($statistiche['per_marca'] ?? []);
                                $mostrate = count($marcheVisibili);
                            @endphp
                            @foreach($marcheVisibili as $marcaId => $marcaData)
                                <tr style="cursor: pointer;" 
                                    wire:click="filtraPerMarca('{{ $marcaId }}')"
                                    class="hover-row"
                                    title="Clicca per applicare questo filtro">
                                    <td>
                                        <strong>{{ $marcaData['nome'] }}</strong>
                                        <iconify-icon icon="solar:arrow-right-bold" class="text-muted ms-1 small"></iconify-icon>
                                    </td>
                                    <td class="text-end">{{ number_format($marcaData['articoli'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($marcaData['quantita'], 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <strong class="text-success">€{{ number_format($marcaData['valore'], 2, ',', '.') }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            @if($totaleMarche > $limiteMarche && empty($searchStatistiche))
                                <tr>
                                    <td colspan="4" class="text-center py-3">
                                        <button class="btn btn-sm btn-outline-primary" wire:click="toggleMostraTutteMarche">
                                            @if($mostraTutteMarche)
                                                <iconify-icon icon="solar:double-alt-arrow-up-bold" class="me-1"></iconify-icon>
                                                Mostra meno ({{ $limiteMarche }} marche)
                                            @else
                                                <iconify-icon icon="solar:double-alt-arrow-down-bold" class="me-1"></iconify-icon>
                                                Mostra tutte ({{ $totaleMarche }} marche totali)
                                            @endif
                                        </button>
                                        <small class="text-muted d-block mt-2">
                                            Mostrate {{ $mostrate }} di {{ $totaleMarche }} marche
                                        </small>
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <th>TOTALE</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['articoli'], 0, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($this->totaliVistaCorrente['quantita'], 0, ',', '.') }}</th>
                                <th class="text-end">
                                    <strong class="text-success">€{{ number_format($this->totaliVistaCorrente['valore'], 2, ',', '.') }}</strong>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Riepilogo Valori</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Valorizzazione Totale:</strong></td>
                                    <td class="text-end">
                                        <span class="h5 text-success mb-0">€{{ number_format($statistiche['totale_valore'], 2, ',', '.') }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Articoli con Costo:</strong></td>
                                    <td class="text-end">{{ number_format($statistiche['totale_articoli'] - $statistiche['articoli_senza_costo'], 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Articoli Senza Costo:</strong></td>
                                    <td class="text-end text-warning">{{ number_format($statistiche['articoli_senza_costo'], 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Quantità Totale:</strong></td>
                                    <td class="text-end">{{ number_format($statistiche['totale_quantita'], 0, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <h6 class="fw-bold mb-1">Dashboard alleggerita</h6>
                <p class="text-muted mb-0">
                    Questa pagina mostra solo statistiche aggregate. Per il dettaglio articolo per articolo usa la pagina dedicata agli articoli di magazzino.
                </p>
            </div>
            <div class="text-lg-end">
                <span class="badge bg-light-primary text-primary px-3 py-2">Caricamento ottimizzato</span>
            </div>
        </div>
    </div>
    {{--
        <div class="card-body">
            @if($articoli->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" 
                                           class="form-check-input" 
                                           wire:click="@if(count($articoliSelezionati) == $articoli->total()) deselezionaTuttiArticoli @else selezionaTuttiArticoli @endif"
                                           @if(count($articoliSelezionati) == $articoli->total() && $articoli->total() > 0) checked @endif>
                                </th>
                                <th style="cursor: pointer;" wire:click="ordinaArticoli('codice')" class="user-select-none">
                                    Codice
                                    @if($sortArticoliField == 'codice')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th style="cursor: pointer;" wire:click="ordinaArticoli('descrizione')" class="user-select-none">
                                    Descrizione
                                    @if($sortArticoliField == 'descrizione')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th style="cursor: pointer;" wire:click="ordinaArticoli('sede')" class="user-select-none">
                                    Sede
                                    @if($sortArticoliField == 'sede')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th style="cursor: pointer;" wire:click="ordinaArticoli('categoria')" class="user-select-none">
                                    Categoria
                                    @if($sortArticoliField == 'categoria')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th style="cursor: pointer;" wire:click="ordinaArticoli('fornitore')" class="user-select-none">
                                    Fornitore
                                    @if($sortArticoliField == 'fornitore')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-center user-select-none" style="cursor: pointer;" wire:click="ordinaArticoli('quantita')">
                                    Quantità
                                    @if($sortArticoliField == 'quantita')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaArticoli('costo_unitario')">
                                    Costo Unit.
                                    @if($sortArticoliField == 'costo_unitario')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-end user-select-none" style="cursor: pointer;" wire:click="ordinaArticoli('valore')">
                                    Valore
                                    @if($sortArticoliField == 'valore')
                                        <iconify-icon icon="solar:{{ $sortArticoliDirection == 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </th>
                                <th class="text-center">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($articoli as $articolo)
                                @php
                                    $isSelezionato = in_array($articolo->id, $articoliSelezionati);
                                @endphp
                                @php
                                    $qta = $articolo->giacenza->quantita_residua ?? 0;
                                    $costo = $articolo->prezzo_acquisto ?? 0;
                                    $valore = $qta * $costo;
                                    $senzaCosto = $costo == 0 || $costo === null;
                                    // Preferisci fornitore da fattura, altrimenti da DDT
                                    $fornitore = $articolo->fatturaDettaglio->first()?->fattura?->fornitore 
                                                ?? $articolo->ddtDettaglio->first()?->ddt?->fornitore;
                                @endphp
                                <tr class="@if($senzaCosto) table-warning @endif @if($isSelezionato) table-active @endif">
                                    <td>
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               wire:click="toggleSelezioneArticolo({{ $articolo->id }})"
                                               @if($isSelezionato) checked @endif>
                                    </td>
                                    <td>
                                        <strong class="text-primary">{{ $articolo->codice }}</strong>
                                        @if($senzaCosto)
                                            <iconify-icon icon="solar:danger-triangle-bold" class="text-warning ms-1" title="Costo mancante"></iconify-icon>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($articolo->descrizione, 50) }}</td>
                                    <td>
                                        @if($articolo->giacenza && $articolo->giacenza->sede)
                                            {{ $articolo->giacenza->sede->nome }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($articolo->categoriaMerceologica)
                                            {{ $articolo->categoriaMerceologica->nome }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($fornitore)
                                            {{ Str::limit($fornitore->ragione_sociale, 30) }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $qta }}</td>
                                    <td class="text-end">
                                        @if($senzaCosto)
                                            <span class="text-warning fw-bold">-</span>
                                        @else
                                            €{{ number_format($costo, 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($senzaCosto)
                                            <span class="text-warning fw-bold">-</span>
                                        @else
                                            <strong class="text-success">€{{ number_format($valore, 2, ',', '.') }}</strong>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    wire:click="apriFatturaModal({{ $articolo->id }})"
                                                    title="Crea fattura per questo articolo">
                                                <iconify-icon icon="solar:document-bold"></iconify-icon>
                                            </button>
                                            <button class="btn btn-outline-info" 
                                                    wire:click="apriStoricoCosti({{ $articolo->id }})"
                                                    title="Storico costi">
                                                <iconify-icon icon="solar:history-bold"></iconify-icon>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $articoli->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <iconify-icon icon="solar:box-bold-duotone" class="fs-1 text-muted"></iconify-icon>
                    <p class="text-muted mt-3">Nessun articolo trovato con i filtri selezionati</p>
                </div>
            @endif
        </div>
    </div>

    --}}
    {{-- Modal Fattura Acquisto --}}
    @if($showFatturaModal)
        <div class="modal fade show d-block" style="z-index: 1055;" tabindex="-1" role="dialog" wire:key="modal-fattura">
            <div class="modal-backdrop fade show" style="z-index: 1040; pointer-events: none;"></div>
            <div class="modal-dialog modal-lg modal-dialog-centered" style="z-index: 1056;">
                <div class="modal-content" style="pointer-events: auto;">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:document-add-bold" class="me-2"></iconify-icon>
                            {{ $articoloSelezionato ? 'Assegna Fattura e Costo' : 'Nuova Fattura Acquisto' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiFatturaModal"></button>
                    </div>
                    <div class="modal-body">
                        @if(!empty($articoliFattura) && count($articoliFattura) > 0)
                            <div class="alert alert-success">
                                <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
                                <strong>{{ count(array_filter($articoliFattura)) }} articolo/i</strong> nella fattura
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                                <strong>Nessun articolo nella fattura.</strong> Seleziona articoli dalla tabella o aggiungili qui.
                            </div>
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Numero Fattura *</label>
                                <input type="text" class="form-control" wire:model="numeroFattura">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Data Fattura *</label>
                                <input type="date" class="form-control" wire:model="dataFattura">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fornitore *</label>
                                <select class="form-select" wire:model="fornitoreFatturaId">
                                    <option value="">Seleziona...</option>
                                    @foreach($fornitori as $fornitore)
                                        <option value="{{ $fornitore->id }}">{{ $fornitore->ragione_sociale }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sede *</label>
                                <select class="form-select" wire:model="sedeFatturaId">
                                    <option value="">Seleziona...</option>
                                    @foreach($sedi as $sede)
                                        <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>


                        {{-- Lista articoli nella fattura --}}
                        @if(count(array_filter($articoliFattura ?? [])) > 0)
                            <div class="mt-3">
                                <h6 class="fw-bold mb-3">Articoli nella Fattura</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Codice</th>
                                                <th>Descrizione</th>
                                                <th style="width: 100px;">Quantità</th>
                                                <th style="width: 120px;" class="text-end">Costo Unit.</th>
                                                <th style="width: 120px;" class="text-end">Totale</th>
                                                <th style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($articoliFattura as $index => $art)
                                                @if(!empty($art))
                                                    @php
                                                        $artObj = \App\Models\Articolo::find($art['articolo_id'] ?? null);
                                                    @endphp
                                                    <tr>
                                                        <td><strong>{{ $artObj->codice ?? 'N/A' }}</strong></td>
                                                        <td>{{ Str::limit($artObj->descrizione ?? 'N/A', 40) }}</td>
                                                        <td>
                                                            <input type="number" 
                                                                   class="form-control form-control-sm" 
                                                                   wire:model.live="articoliFattura.{{ $index }}.quantita"
                                                                   min="1"
                                                                   style="width: 80px;">
                                                        </td>
                                                        <td class="text-end">
                                                            <input type="number" 
                                                                   step="0.01" 
                                                                   class="form-control form-control-sm text-end" 
                                                                   wire:model.live="articoliFattura.{{ $index }}.costo_unitario"
                                                                   min="0.01"
                                                                   style="width: 110px;">
                                                        </td>
                                                        <td class="text-end fw-bold">
                                                            €{{ number_format(($art['costo_unitario'] ?? 0) * ($art['quantita'] ?? 1), 2, ',', '.') }}
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    wire:click="rimuoviArticoloDallaFattura({{ $index }})"
                                                                    title="Rimuovi">
                                                                <iconify-icon icon="solar:trash-bin-bold"></iconify-icon>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="4" class="text-end">TOTALE:</th>
                                                <th class="text-end">
                                                    €{{ number_format(collect(array_filter($articoliFattura ?? []))->sum(function($art) { return ($art['costo_unitario'] ?? 0) * ($art['quantita'] ?? 1); }), 2, ',', '.') }}
                                                </th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Sezione per aggiungere altri articoli manualmente (opzionale) --}}
                        @if(empty($articoliSelezionati) || count($articoliSelezionati) == 0)
                            <div class="card mt-3" style="border-style: dashed;">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3">Aggiungi Articolo Manuale</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Cerca Articolo</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   wire:model.live.debounce.300ms="search"
                                                   placeholder="Codice o descrizione...">
                                            @if($search && $articoli->count() > 0)
                                                <div class="list-group mt-2" style="max-height: 200px; overflow-y: auto;">
                                                    @foreach($articoli->take(5) as $art)
                                                        <button type="button" 
                                                                class="list-group-item list-group-item-action"
                                                                wire:click="aggiungiArticoloAllaFattura({{ $art->id }})">
                                                            <strong>{{ $art->codice }}</strong> - {{ Str::limit($art->descrizione, 50) }}
                                                            <small class="text-muted">(€{{ number_format($art->prezzo_acquisto ?? 0, 2, ',', '.') }})</small>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiFatturaModal">Annulla</button>
                        <button type="button" class="btn btn-primary" wire:click="salvaFattura"
                                @if(count(array_filter($articoliFattura ?? [])) == 0) disabled @endif>
                            <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                            Salva Fattura
                            @if(count(array_filter($articoliFattura ?? [])) == 0)
                                <span class="badge bg-warning text-dark ms-2">Aggiungi articoli</span>
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Storico Costi --}}
    @if($showStoricoCostiModal && $articoloStorico)
        <div class="modal fade show d-block" style="z-index: 1055;" tabindex="-1" role="dialog" wire:key="modal-storico">
            <div class="modal-backdrop fade show" style="z-index: 1040; pointer-events: none;"></div>
            <div class="modal-dialog modal-lg modal-dialog-centered" style="z-index: 1056;">
                <div class="modal-content" style="pointer-events: auto;">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:history-bold" class="me-2"></iconify-icon>
                            Storico Costi - {{ $articoloStorico->codice }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiStoricoCostiModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <strong>Costo Attuale:</strong> 
                            @if($articoloStorico->prezzo_acquisto)
                                €{{ number_format($articoloStorico->prezzo_acquisto, 2, ',', '.') }}
                            @else
                                <span class="text-warning">Non impostato</span>
                            @endif
                        </div>
                        
                        @if($articoloStorico->storicoCosti && $articoloStorico->storicoCosti->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Costo Precedente</th>
                                            <th>Costo Nuovo</th>
                                            <th>Variazione</th>
                                            <th>Fattura</th>
                                            <th>Utente</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($articoloStorico->storicoCosti->sortByDesc('created_at') as $storico)
                                            @php
                                                $variazione = $storico->costo_nuovo - ($storico->costo_precedente ?? 0);
                                            @endphp
                                            <tr>
                                                <td>{{ $storico->created_at->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    @if($storico->costo_precedente)
                                                        €{{ number_format($storico->costo_precedente, 2, ',', '.') }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td><strong>€{{ number_format($storico->costo_nuovo, 2, ',', '.') }}</strong></td>
                                                <td>
                                                    @if($variazione > 0)
                                                        <span class="text-success">+€{{ number_format($variazione, 2, ',', '.') }}</span>
                                                    @elseif($variazione < 0)
                                                        <span class="text-danger">€{{ number_format($variazione, 2, ',', '.') }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($storico->fattura)
                                                        {{ $storico->fattura->numero }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>{{ $storico->user->name ?? 'N/A' }}</td>
                                                <td>{{ $storico->note ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <iconify-icon icon="solar:history-bold-duotone" class="fs-1 text-muted"></iconify-icon>
                                <p class="text-muted mt-2">Nessuno storico disponibile per questo articolo</p>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiStoricoCostiModal">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Flash Messages --}}
    @if(session()->has('success'))
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif
    
    @if(session()->has('info'))
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif
    
    @if(session()->has('error'))
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <iconify-icon icon="solar:danger-circle-bold" class="me-2"></iconify-icon>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    <style>
        .hover-row:hover {
            background-color: rgba(0, 123, 255, 0.08) !important;
        }
    </style>

</div>
