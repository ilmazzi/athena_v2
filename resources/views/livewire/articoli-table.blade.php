<div>
    <!-- Messaggi Flash -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:close-circle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Badge Filtri Attivi -->
    @php
        $filtriAttivi = [];
        if($search) $filtriAttivi[] = ['label' => 'Ricerca', 'value' => $search, 'field' => 'search'];
        if(!empty($magazziniSelezionati)) {
            $magazziniSelezionatiNomi = collect($magazzini)->whereIn('id', $magazziniSelezionati)->pluck('nome')->toArray();
            $filtriAttivi[] = ['label' => 'Magazzino', 'value' => implode(', ', $magazziniSelezionatiNomi), 'field' => 'magazziniSelezionati'];
        } elseif($magazzinoFilter) {
            $mag = collect($magazzini)->firstWhere('id', $magazzinoFilter);
            $filtriAttivi[] = ['label' => 'Magazzino', 'value' => $mag ? $mag->nome : $magazzinoFilter, 'field' => 'magazzinoFilter'];
        }
        if($fornitoreFilter) {
            $forn = collect($fornitori)->firstWhere('id', $fornitoreFilter);
            $filtriAttivi[] = ['label' => 'Fornitore', 'value' => $forn ? $forn->ragione_sociale : $fornitoreFilter, 'field' => 'fornitoreFilter'];
        }
        if($giacenzaFilter) $filtriAttivi[] = ['label' => 'Giacenza', 'value' => ucfirst($giacenzaFilter), 'field' => 'giacenzaFilter'];
        if($giacenza) {
            $giacenzaLabels = ['positiva' => 'Con Giacenza', 'zero' => 'Giacenza Zero', 'negativa' => 'Giacenza Negativa', 'nessuna' => 'Senza Giacenze'];
            $filtriAttivi[] = ['label' => 'Filtro Giacenza', 'value' => $giacenzaLabels[$giacenza] ?? ucfirst($giacenza), 'field' => 'giacenza'];
        }
        if($statoArticoloFilter) $filtriAttivi[] = ['label' => 'Stato Articolo', 'value' => ucfirst($statoArticoloFilter), 'field' => 'statoArticoloFilter'];
        if($marcaFilter) $filtriAttivi[] = ['label' => 'Marca', 'value' => $marcaFilter, 'field' => 'marcaFilter'];
        if($ubicazioneFilter) {
            $sede = collect($sedi)->firstWhere('id', $ubicazioneFilter);
            $filtriAttivi[] = ['label' => 'Sede', 'value' => $sede ? $sede->nome : $ubicazioneFilter, 'field' => 'ubicazioneFilter'];
        }
        if($statoFilter) $filtriAttivi[] = ['label' => 'Stato', 'value' => ucfirst($statoFilter), 'field' => 'statoFilter'];
        if($fotoFilter) {
            $fotoLabel = $fotoFilter === 'con' ? 'Con foto' : 'Senza foto';
            $filtriAttivi[] = ['label' => 'Foto', 'value' => $fotoLabel, 'field' => 'fotoFilter'];
        }
        if($inDepositoFilter === '1') {
            $filtriAttivi[] = ['label' => 'Conto deposito', 'value' => 'Solo in deposito', 'field' => 'inDepositoFilter'];
        }
    @endphp

    @if(count($filtriAttivi) > 0)
        <div class="mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small fw-semibold">
                    <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>
                    Filtri attivi ({{ count($filtriAttivi) }}):
                </span>
                @foreach($filtriAttivi as $filtro)
                    <span class="badge bg-primary-subtle text-primary d-inline-flex align-items-center gap-2 px-3 py-2">
                        <span><strong>{{ $filtro['label'] }}:</strong> {{ Str::limit($filtro['value'], 30) }}</span>
                        <button type="button" 
                                class="btn-close btn-close-sm" 
                                style="font-size: 0.6rem;"
                                @if($filtro['field'] === 'magazziniSelezionati')
                                    wire:click="deselezionaTuttiMagazzini"
                                @else
                                    wire:click="$set('{{ $filtro['field'] }}', '')"
                                @endif
                                aria-label="Rimuovi filtro"></button>
                    </span>
                @endforeach
                <button class="btn btn-sm btn-danger" wire:click="resetFilters">
                    <iconify-icon icon="solar:trash-bin-minimalistic-bold" class="me-1"></iconify-icon>
                    Rimuovi tutti
                </button>
            </div>
        </div>
    @endif

    <!-- Filtro principale: Magazzino + Ricerca -->
    <div class="card border-0 shadow-sm mb-4" style="position: sticky; top: 64px; z-index: 1000;">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5">
                    <label class="form-label small fw-semibold mb-1">Magazzino</label>
                    <details class="position-relative" id="magazzinoDropdown">
                        <summary class="btn btn-secondary btn-sm w-100 text-start d-flex justify-content-between align-items-center">
                            <span>
                                @if(empty($magazziniSelezionati))
                                    Tutti i Magazzini
                                @else
                                    {{ count($magazziniSelezionati) }} Magazzini Selezionati
                                @endif
                            </span>
                            <iconify-icon icon="solar:alt-arrow-down-bold" class="ms-2"></iconify-icon>
                        </summary>
                        <div class="position-absolute w-100 bg-white border rounded shadow-lg" 
                             style="top: 100%; left: 0; z-index: 1050; max-height: 300px; overflow-y: auto;">
                            <div class="p-2">
                                <div class="d-flex gap-2 mb-2">
                                    <button class="btn btn-sm btn-success flex-fill" wire:click="selezionaTuttiMagazzini">
                                        <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                        Tutti
                                    </button>
                                    <button class="btn btn-sm btn-danger flex-fill" wire:click="deselezionaTuttiMagazzini">
                                        <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                        Nessuno
                                    </button>
                                </div>
                                <hr class="my-2">
                                @foreach($magazzini as $magazzino)
                                    <div class="form-check py-1">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               id="magazzino_{{ $magazzino->id }}"
                                               wire:change="toggleMagazzino({{ $magazzino->id }})"
                                               @if(in_array($magazzino->id, $magazziniSelezionati)) checked @endif>
                                        <label class="form-check-label w-100" for="magazzino_{{ $magazzino->id }}">
                                            {{ $magazzino->id }} - {{ $magazzino->nome }}
                                            @if(isset($magazzino->articoli_count))
                                                <small class="text-muted">({{ $magazzino->articoli_count }})</small>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                </div>
                <div class="col-lg-4">
                    <label class="form-label small fw-semibold mb-1">Ricerca</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <iconify-icon icon="solar:magnifer-bold"></iconify-icon>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               placeholder="Codice, descrizione, fornitore..." 
                               wire:model.live.debounce.600ms="search">
                    </div>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button class="btn btn-sm btn-outline-primary"
                                type="button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#articoliFiltersCanvas"
                                aria-controls="articoliFiltersCanvas">
                            <iconify-icon icon="solar:slider-vertical-bold" class="me-1"></iconify-icon>
                            Altri filtri
                            @if(count($filtriAttivi) > 0)
                                <span class="badge bg-primary ms-2">{{ count($filtriAttivi) }}</span>
                            @endif
                        </button>
                        <button class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#prezziFornitoreCanvas"
                                aria-controls="prezziFornitoreCanvas">
                            <iconify-icon icon="solar:dollar-minimalistic-bold" class="me-1"></iconify-icon>
                            Prezzi fornitore
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Filtri -->
    <div class="offcanvas offcanvas-top" tabindex="-1" id="articoliFiltersCanvas" aria-labelledby="articoliFiltersCanvasLabel" style="height: 85vh;">
        <div class="offcanvas-header">
            <h6 class="offcanvas-title" id="articoliFiltersCanvasLabel">
                <iconify-icon icon="solar:filter-bold" class="me-2"></iconify-icon>
                Filtri e Ricerca
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body overflow-auto">
            @if(count($filtriAttivi) > 0)
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted small fw-semibold">
                            <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>
                            Filtri attivi ({{ count($filtriAttivi) }}):
                        </span>
                        @foreach($filtriAttivi as $filtro)
                            <span class="badge bg-primary-subtle text-primary d-inline-flex align-items-center gap-2 px-3 py-2">
                                <span><strong>{{ $filtro['label'] }}:</strong> {{ Str::limit($filtro['value'], 30) }}</span>
                                <button type="button" 
                                        class="btn-close btn-close-sm" 
                                        style="font-size: 0.6rem;"
                                        @if($filtro['field'] === 'magazziniSelezionati')
                                            wire:click="deselezionaTuttiMagazzini"
                                        @else
                                            wire:click="$set('{{ $filtro['field'] }}', '')"
                                        @endif
                                        aria-label="Rimuovi filtro"></button>
                            </span>
                        @endforeach
                        <button class="btn btn-sm btn-danger" wire:click="resetFilters">
                            <iconify-icon icon="solar:trash-bin-minimalistic-bold" class="me-1"></iconify-icon>
                            Rimuovi tutti
                        </button>
                    </div>
                </div>
            @endif

            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-sm btn-info" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#advancedFilters"
                        aria-expanded="true"
                        aria-controls="advancedFilters"
                        id="toggleAdvancedBtn">
                    <iconify-icon icon="solar:slider-vertical-bold" class="me-1"></iconify-icon>
                    Avanzati
                </button>
                <button class="btn btn-sm btn-secondary" wire:click="resetFilters">
                    <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                    Reset
                </button>
            </div>

            <div class="row g-3">
                <!-- Filtro Fornitore -->
                <div class="col-lg-3">
                    <label class="form-label small fw-semibold">Fornitore</label>
                    <select class="form-select form-select-sm" wire:model.live="fornitoreFilter">
                        <option value="">Tutti</option>
                        @foreach($fornitori as $fornitore)
                            <option value="{{ $fornitore->id }}">{{ $fornitore->ragione_sociale }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Filtro Giacenza -->
                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Giacenza</label>
                    <select class="form-select form-select-sm" wire:model.live="giacenzaFilter">
                        <option value="">Tutti</option>
                        <option value="giacenti">Solo Giacenti</option>
                        <option value="in_produzione">In Produzione</option>
                        <option value="scarichi">Solo Scarichi</option>
                    </select>
                </div>

                <!-- Filtro Stato Articolo -->
                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Stato Articolo</label>
                    <select class="form-select form-select-sm" wire:model.live="statoArticoloFilter">
                        <option value="">Tutti</option>
                        <option value="disponibile">Disponibili</option>
                        <option value="scaricato">Scaricati</option>
                    </select>
                </div>

                <!-- Per Pagina -->
                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Per Pagina</label>
                    <select class="form-select form-select-sm" wire:model.live="perPage">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                    </select>
                </div>
            </div>

            <!-- Filtri Avanzati (Aperti per default) -->
            <div class="collapse show mt-3" id="advancedFilters">
                <div class="row g-3">
                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Marca</label>
                        <select class="form-select form-select-sm" wire:model.live="marcaFilter">
                            <option value="">Tutte</option>
                            @foreach($marche as $marca)
                                <option value="{{ $marca }}">{{ $marca }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Sede</label>
                        <select class="form-select form-select-sm" wire:model.live="ubicazioneFilter">
                            <option value="">Tutte</option>
                            @foreach($sedi as $sede)
                                <option value="{{ $sede->id }}">
                                    {{ $sede->nome }} ({{ $sede->citta }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2" wire:ignore>
                        <label class="form-label small fw-semibold">Data Inizio</label>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               placeholder="gg/mm/aaaa"
                               id="dataDocumentoFrom"
                               data-input>
                    </div>

                    <div class="col-lg-2" wire:ignore>
                        <label class="form-label small fw-semibold">Data Fine</label>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               placeholder="gg/mm/aaaa"
                               id="dataDocumentoTo"
                               data-input>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Prezzo Min (€)</label>
                        <input type="number" class="form-control form-control-sm" placeholder="Min" wire:model.live.debounce.500ms="prezzoMin">
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Prezzo Max (€)</label>
                        <input type="number" class="form-control form-control-sm" placeholder="Max" wire:model.live.debounce.500ms="prezzoMax">
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Foto</label>
                        <select class="form-select form-select-sm" wire:model.live="fotoFilter">
                            <option value="">Tutte</option>
                            <option value="con">Con foto</option>
                            <option value="senza">Senza foto</option>
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Filtri Speciali</label>
                        <div class="d-flex gap-3 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="soloVetrina" id="soloVetrina">
                                <label class="form-check-label small" for="soloVetrina">In Vetrina</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="inDepositoFilter" value="1" id="inDepositoFilter">
                                <label class="form-check-label small" for="inDepositoFilter">In Deposito</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Prezzi Fornitore -->
    <div class="offcanvas offcanvas-top" tabindex="-1" id="prezziFornitoreCanvas" aria-labelledby="prezziFornitoreCanvasLabel" style="height: 85vh;" wire:ignore.self>
        <div class="offcanvas-header">
            <h6 class="offcanvas-title" id="prezziFornitoreCanvasLabel">
                <iconify-icon icon="solar:dollar-minimalistic-bold" class="me-2"></iconify-icon>
                Prezzi Fornitore (aggiornamento massivo)
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body overflow-auto">
            <div class="row g-3">
                <div class="col-lg-3">
                    <label class="form-label small fw-semibold">Fornitore</label>
                    <select class="form-select form-select-sm" wire:model.live="prezziFornitoreId">
                        <option value="">Seleziona fornitore</option>
                        @foreach($fornitori as $fornitore)
                            <option value="{{ $fornitore->id }}">{{ $fornitore->ragione_sociale }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Criterio</label>
                    <select class="form-select form-select-sm" wire:model.live="prezziMatchType">
                        <option value="referenza">Referenza</option>
                        <option value="modello">Modello</option>
                        <option value="seriale">Seriale</option>
                        <option value="ean">EAN</option>
                        <option value="codice">Codice</option>
                        <option value="descrizione">Descrizione</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label small fw-semibold">Valore</label>
                    <input type="text" class="form-control form-control-sm" placeholder="Es. REF-123" wire:model.live.debounce.500ms="prezziMatchValue">
                </div>
                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Nuovo prezzo</label>
                    <input type="text" class="form-control form-control-sm" placeholder="€ 123,45" wire:model.live.debounce.500ms="prezziNuovoPrezzo">
                </div>
                <div class="col-lg-2 d-flex align-items-end">
                    <button class="btn btn-sm btn-outline-secondary w-100" wire:click="aggiornaPreviewPrezzi">
                        Cerca
                    </button>
                </div>
                <div class="col-12">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="prezziSoloSenzaPrezzo" wire:model.live="prezziSoloSenzaPrezzo">
                        <label class="form-check-label small" for="prezziSoloSenzaPrezzo">Solo articoli senza prezzo</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="prezziSalvaRegola" wire:model.live="prezziSalvaRegola">
                        <label class="form-check-label small" for="prezziSalvaRegola">Salva regola fornitore</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="prezziApplicaATutti" wire:model.live="prezziApplicaATutti">
                        <label class="form-check-label small" for="prezziApplicaATutti">Applica a tutti i risultati trovati</label>
                    </div>
                </div>

                <div class="col-12">
                    @if($prezziPreviewLoaded)
                        <div class="alert alert-info py-2">
                            <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                            Trovati <strong>{{ $prezziPreviewTotal }}</strong> articoli. Mostro i primi <strong>{{ count($prezziPreview) }}</strong>.
                        </div>
                    @endif
                </div>

                @if($prezziPreviewLoaded)
                    <div class="col-12">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" wire:click="toggleSelezionaTuttiPreview">
                                        </th>
                                        <th>Codice</th>
                                        <th>Descrizione</th>
                                        <th>Referenza</th>
                                        <th>Seriale</th>
                                        <th>Prezzo attuale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($prezziPreview as $row)
                                        @php
                                            $referenza = $row['caratteristiche']['referenza'] ?? null;
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input"
                                                       wire:model.live="prezziSelezionati"
                                                       value="{{ $row['id'] }}">
                                            </td>
                                            <td>{{ $row['codice'] }}</td>
                                            <td>{{ Str::limit($row['descrizione'] ?? 'N/A', 40) }}</td>
                                            <td>{{ $referenza ?? '-' }}</td>
                                            <td>{{ $row['numero_seriale'] ?? '-' }}</td>
                                            <td>
                                                @if(!empty($row['prezzo_fornitore']))
                                                    €{{ number_format($row['prezzo_fornitore'], 2, ',', '.') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Nessun risultato</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-sm btn-primary" wire:click="applicaPrezzoFornitore">
                            Applica prezzi selezionati
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Tabella Articoli Professionale -->
    <div class="card border-0  shadow-sm">
        <div class="card-header  border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0">
                        @if($magazzinoFilter)
                            @php
                                $magazzinoSelezionato = $magazzini->firstWhere('id', $magazzinoFilter);
                                $codiceMagazzino = $magazzinoSelezionato->codice ?? '';
                                $nomeMagazzino = $magazzinoSelezionato->nome ?? 'Magazzino Sconosciuto';
                                $numeroMagazzino = (int) str_replace('MAG', '', $codiceMagazzino);
                            @endphp
                            Articoli - {{ $numeroMagazzino }} - {{ $nomeMagazzino }}
                        @else
                            Articoli Magazzino
                        @endif
                    </h6>
                    <small class="text-muted">
                        {{ $articoli->total() }} articoli • Pagina {{ $articoli->currentPage() }} di {{ $articoli->lastPage() }}
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <!-- Controlli Sorting -->
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-secondary" wire:click="sortBy('codice')" title="Ordina per Codice">
                            <iconify-icon icon="solar:hash-bold" class="me-1"></iconify-icon>
                            Codice
                        </button>
                        <button class="btn btn-secondary" wire:click="sortBy('prezzo_acquisto')" title="Ordina per Prezzo">
                            <iconify-icon icon="solar:dollar-bold" class="me-1"></iconify-icon>
                            Prezzo
                        </button>
                        <button class="btn btn-secondary" wire:click="sortBy('data_carico')" title="Ordina per Data">
                            <iconify-icon icon="solar:calendar-bold" class="me-1"></iconify-icon>
                            Data
                        </button>
                    </div>
                    <div class="position-relative">
                        <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="toggleColumnsDropdown">
                            <iconify-icon icon="solar:slider-vertical-bold" class="me-1"></iconify-icon>
                            Colonne
                        </button>
                        @if($showColumnsDropdown)
                            <div class="position-absolute end-0 mt-2 bg-white border rounded shadow-lg p-2"
                                 style="z-index: 1050; min-width: 220px;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Seleziona colonne</small>
                                    <button class="btn btn-link btn-sm p-0" wire:click="resetVisibleColumns">Reset</button>
                                </div>
                                @foreach($this->columnOptions as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="col_{{ $key }}"
                                               wire:model.live="visibleColumns.{{ $key }}">
                                        <label class="form-check-label" for="col_{{ $key }}">
                                            {{ $label }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-centered">
                    <thead class="">
                        @php
                            // Mappa icone eleganti per categoria merceologica
                            $iconeCategorie = [
                                1 => ['icon' => 'lucide:alarm-clock', 'color' => 'text-primary', 'title' => 'Sveglie'],           // Sveglie
                                2 => ['icon' => 'lucide:watch', 'color' => 'text-secondary', 'title' => 'Orologi Acciaio'], // Orologi Acciaio
                                3 => ['icon' => 'lucide:watch', 'color' => 'text-warning', 'title' => 'Orologi Oro'],       // Orologi Oro
                                4 => ['icon' => 'lucide:link', 'color' => '', 'title' => 'Cinturini'],           // Cinturini
                                5 => ['icon' => 'lucide:gem', 'color' => 'text-info', 'title' => 'Gioielleria'],         // Gioielleria
                                6 => ['icon' => 'lucide:sparkles', 'color' => '', 'title' => 'Argenteria'],         // Argenteria
                                7 => ['icon' => 'lucide:sparkles', 'color' => 'text-secondary', 'title' => 'Silver'],             // Silver (grigio)
                                8 => ['icon' => 'fluent-emoji-high-contrast:dodo', 'color' => 'text-success', 'title' => 'Dodo'],             // Dodo
                            ];
                            
                            // Determina categoria attuale e se è orologio
                            $categoriaAttuale = null;
                            $isOrologioCategory = false;
                            $iconaCategoria = ['icon' => 'lucide:package', 'color' => 'text-secondary', 'title' => 'Tutti']; // default
                            
                            if($this->magazzinoFilter) {
                                $categoriaAttuale = \App\Models\CategoriaMerceologica::find($this->magazzinoFilter);
                                if($categoriaAttuale) {
                                    $iconaCategoria = $iconeCategorie[$categoriaAttuale->id] ?? $iconaCategoria;
                                    $isOrologioCategory = in_array($categoriaAttuale->id, [1, 2, 3, 4]); // Sveglie, Orologi Acciaio, Oro, Cinturini
                                }
                            } elseif($articoli->count() > 0) {
                                $primoArticolo = $articoli->first();
                                if($primoArticolo->categoriaMerceologica) {
                                    $categoriaAttuale = $primoArticolo->categoriaMerceologica;
                                    $iconaCategoria = $iconeCategorie[$categoriaAttuale->id] ?? $iconaCategoria;
                                    $isOrologioCategory = in_array($categoriaAttuale->id, [1, 2, 3, 4]); // Sveglie, Orologi Acciaio, Oro, Cinturini
                                }
                            }
                        @endphp
                        <tr>
                            @if($visibleColumns['codice'] ?? true)
                            <th style="cursor: pointer;" wire:click="sortBy('codice')">
                                <div class="d-flex align-items-center gap-1">
                                    @php
                                        // Header icona: se filtro attivo usa categoria, altrimenti icona generica
                                        if($this->magazzinoFilter) {
                                            $iconaHeader = $iconaCategoria;
                                        } else {
                                            $iconaHeader = ['icon' => 'lucide:package', 'color' => 'text-secondary', 'title' => 'Tutti'];
                                        }
                                    @endphp
                                    <iconify-icon icon="{{ $iconaHeader['icon'] }}" class="{{ $iconaHeader['color'] }}" title="{{ $iconaHeader['title'] }}"></iconify-icon>
                                    Codice
                                    @if($sortField === 'codice')
                                        <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </div>
                            </th>
                            @endif
                            @if($visibleColumns['descrizione'] ?? true)
                            <th style="cursor: pointer;" wire:click="sortBy('descrizione')">
                                <div class="d-flex align-items-center gap-1">
                                    <iconify-icon icon="solar:text-field-bold" class="text-info"></iconify-icon>
                                    Descrizione
                                    @if($sortField === 'descrizione')
                                        <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </div>
                            </th>
                            @endif
                            @if(!$isOrologioCategory && ($visibleColumns['specifiche'] ?? true))
                            <th>
                                <iconify-icon icon="solar:settings-bold" class="text-secondary me-1"></iconify-icon>
                                Specifiche
                            </th>
                            @endif
                            @if(!$isOrologioCategory && ($visibleColumns['caratura'] ?? true))
                            <th>
                                <iconify-icon icon="solar:gem-bold" class="text-warning me-1"></iconify-icon>
                                Caratura
                            </th>
                            @endif
                            @if($visibleColumns['giacenza'] ?? true)
                            <th>
                                <iconify-icon icon="solar:box-bold" class="text-secondary me-1"></iconify-icon>
                                Giacenza
                            </th>
                            @endif
                            @if($visibleColumns['costo_unitario'] ?? true)
                            <th style="cursor: pointer;" wire:click="sortBy('prezzo_acquisto')">
                                <div class="d-flex align-items-center gap-1">
                                    <iconify-icon icon="solar:dollar-bold" class="text-warning"></iconify-icon>
                                    Costo Unitario
                                    @if($sortField === 'prezzo_acquisto')
                                        <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'alt-arrow-up' : 'alt-arrow-down' }}-bold" class="text-primary"></iconify-icon>
                                    @endif
                                </div>
                            </th>
                            @endif
                            @if($visibleColumns['prezzo_fornitore'] ?? true)
                            <th>
                                <div class="d-flex align-items-center gap-1">
                                    <iconify-icon icon="solar:tag-price-bold" class="text-success"></iconify-icon>
                                    Prezzo Fornitore
                                </div>
                            </th>
                            @endif
                            @if($visibleColumns['valore_totale'] ?? true)
                            <th>
                                <div class="d-flex align-items-center gap-1">
                                    <iconify-icon icon="solar:calculator-bold" class="text-secondary"></iconify-icon>
                                    Valore Totale
                                </div>
                            </th>
                            @endif
                            @if($visibleColumns['dati_carico'] ?? true)
                            <th>
                                <iconify-icon icon="solar:file-text-bold" class="text-info me-1"></iconify-icon>
                                Dati Carico
                            </th>
                            @endif
                            @if($visibleColumns['ubicazione'] ?? true)
                            <th>
                                <iconify-icon icon="solar:map-point-bold" class="text-danger me-1"></iconify-icon>
                                Ubicazione
                            </th>
                            @endif
                            @if($visibleColumns['azioni'] ?? true)
                            <th class="text-center">
                                <iconify-icon icon="solar:settings-bold" class="text-secondary me-1"></iconify-icon>
                                Azioni
                            </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($articoli as $index => $articolo)
                            <tr wire:key="articolo-{{ $articolo->id }}">
                                @if($visibleColumns['codice'] ?? true)
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @php
                                            // Se c'è un filtro attivo, usa l'icona della categoria filtrata
                                            // Altrimenti usa l'icona specifica dell'articolo
                                            if($this->magazzinoFilter) {
                                                $iconaArticolo = $iconaCategoria;
                                            } else {
                                                // Nessun filtro: usa l'icona specifica dell'articolo
                                                $iconaArticolo = $iconeCategorie[$articolo->categoria_merceologica_id] ?? ['icon' => 'lucide:help-circle', 'color' => 'text-secondary', 'title' => 'Sconosciuto'];
                                            }
                                        @endphp
                                        <div class="d-flex align-items-center justify-content-center">
                                            <iconify-icon icon="{{ $iconaArticolo['icon'] }}" class="{{ $iconaArticolo['color'] }} fs-5" title="{{ $iconaArticolo['title'] }}"></iconify-icon>
                                        </div>
                                        @php
                                            $fotoPrincipale = $articolo->foto_principale;
                                            $fotoUrl = null;
                                            if (!empty($fotoPrincipale)) {
                                                $fotoUrl = Str::startsWith($fotoPrincipale, ['http://', 'https://'])
                                                    ? $fotoPrincipale
                                                    : asset('storage/' . ltrim($fotoPrincipale, '/'));
                                            }
                                        @endphp
                                        @if($fotoUrl)
                                            <img src="{{ $fotoUrl }}"
                                                 alt="Foto {{ $articolo->codice }}"
                                                 class="rounded border"
                                                 style="width: 36px; height: 36px; object-fit: cover; cursor: pointer;"
                                                 data-bs-toggle="modal"
                                                 data-bs-target="#articoloFotoModal"
                                                 data-foto-url="{{ $fotoUrl }}"
                                                 data-foto-alt="Foto {{ $articolo->codice }}">
                                        @endif
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-semibold">{{ $articolo->codice }}</span>
                                                @if($fotoUrl)
                                                    <iconify-icon icon="solar:camera-bold" class="text-success" title="Foto presente"></iconify-icon>
                                                @else
                                                    <iconify-icon icon="solar:camera-off-bold" class="text-muted" title="Nessuna foto"></iconify-icon>
                                                @endif
                                                @if($articolo->contoDepositoCorrente && $articolo->quantita_in_deposito > 0)
                                                    <span class="badge bg-warning-subtle text-warning">
                                                        Deposito {{ $articolo->contoDepositoCorrente->sedeDestinataria->nome ?? 'N/D' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <small class="text-muted">#{{ $articoli->firstItem() + $index }}</small>
                                        </div>
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['descrizione'] ?? true)
                                <td>
                                    <div>
                                        <span class="fw-semibold ">{{ Str::limit($articolo->descrizione, 30) ?? 'N/A' }}</span>
                                        @if($articolo->descrizione_estesa)
                                            <br><small class="text-muted">{{ Str::limit($articolo->descrizione_estesa, 40) }}</small>
                                        @endif
                                        @if($articolo->prodottoFinito && $articolo->prodottoFinito->componentiArticoli->isNotEmpty())
                                            @php
                                                $componenti = $articolo->prodottoFinito->componentiArticoli
                                                    ->map(fn($c) => $c->articolo?->codice ?: $c->articolo?->descrizione)
                                                    ->filter()
                                                    ->take(6)
                                                    ->implode(', ');
                                            @endphp
                                            @if($componenti)
                                                <br><small class="text-muted">Componenti: {{ $componenti }}</small>
                                            @endif
                                        @endif
                                        
                                        <!-- Marca e Referenza -->
                                        <br><small class="text-muted">
                                            <iconify-icon icon="solar:star-bold" class="text-warning me-1"></iconify-icon>
                                            @if($articolo->caratteristiche && is_array($articolo->caratteristiche) && isset($articolo->caratteristiche['marca']))
                                                <span class="fw-semibold">Marca:</span> {{ $articolo->caratteristiche['marca'] }}
                                            @else
                                                <span class="fw-semibold">Marca:</span> N/A
                                            @endif
                                            @if($articolo->caratteristiche && is_array($articolo->caratteristiche) && isset($articolo->caratteristiche['referenza']))
                                                <span class="ms-2">
                                                    <iconify-icon icon="solar:settings-bold" class="text-secondary me-1"></iconify-icon>
                                                    <span class="fw-semibold">Ref:</span> {{ $articolo->caratteristiche['referenza'] }}
                                                </span>
                                            @endif
                                            @if(!empty($articolo->numero_seriale))
                                                <span class="ms-2">
                                                    <iconify-icon icon="solar:shield-keyhole-bold" class="text-info me-1"></iconify-icon>
                                                    <span class="fw-semibold">SN:</span> {{ $articolo->numero_seriale }}
                                                </span>
                                            @endif
                                        </small>
                                    </div>
                                </td>
                                @endif
                                @if(!$isOrologioCategory && ($visibleColumns['specifiche'] ?? true))
                                <td>
                                    <div class="text-center">
                                        <!-- Per gioielli: materiale, colore, peso -->
                                        @if($articolo->materiale)
                                            <div class="mb-1">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <iconify-icon icon="solar:tag-bold" class="text-warning"></iconify-icon>
                                                    <span class="badge bg-warning-subtle text-warning">{{ $articolo->materiale }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        
                                        @if($articolo->colore)
                                            <div class="mb-1">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <div class="rounded-circle bg-{{ strtolower($articolo->colore) }} border border-light" style="width: 14px; height: 14px;"></div>
                                                    <span class="fw-semibold ">{{ $articolo->colore }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        
                                        @if($articolo->peso_lordo || $articolo->peso_netto)
                                            <div class="mb-1">
                                                @if($articolo->peso_lordo)
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <iconify-icon icon="solar:weight-bold" class="text-secondary"></iconify-icon>
                                                        <span class="fw-semibold text-secondary">{{ number_format($articolo->peso_lordo, 1) }}g</span>
                                                    </div>
                                                @endif
                                                @if($articolo->peso_netto && $articolo->peso_netto != $articolo->peso_lordo)
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <iconify-icon icon="solar:scale-bold" class="text-primary"></iconify-icon>
                                                        <span class="fw-semibold text-primary">{{ number_format($articolo->peso_netto, 1) }}g</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        
                                        @if(!$articolo->materiale && !$articolo->colore && !$articolo->peso_lordo && !$articolo->peso_netto)
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if(!$isOrologioCategory && ($visibleColumns['caratura'] ?? true))
                                <td>
                                    <div class="text-center">
                                        <!-- Per gioielli: titolo e caratura -->
                                        @if($articolo->titolo || $articolo->caratura)
                                            @if($articolo->titolo)
                                                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                                                    <iconify-icon icon="solar:gem-bold" class="text-warning"></iconify-icon>
                                                    <span class="fw-semibold ">{{ $articolo->titolo }}K</span>
                                                </div>
                                                <small class="text-muted">Titolo</small>
                                            @endif
                                            @if($articolo->caratura)
                                                <br><div class="d-flex align-items-center justify-content-center gap-1 mt-1">
                                                    <iconify-icon icon="solar:star-bold" class="text-info"></iconify-icon>
                                                    <span class="fw-semibold text-info">{{ $articolo->caratura }}ct</span>
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['giacenza'] ?? true)
                                <td>
                                    <div class="text-center">
                                        @if($articolo->giacenza)
                                            @php
                                                $inProdottoFinito = $articolo->isInProdottoFinito();
                                                $prodottoFinito = $inProdottoFinito ? $articolo->prodotto_finito : null;
                                                // Se è in un prodotto finito, mostra la quantità originale, altrimenti la residua
                                                $giacenzaMostrata = $inProdottoFinito ? $articolo->giacenza->quantita : $articolo->giacenza->quantita_residua;
                                                $badgeColor = $inProdottoFinito ? 'warning' : ($articolo->giacenza->quantita_residua > 0 ? 'success' : 'danger');
                                            @endphp
                                            
                                            <!-- Giacenza -->
                                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                                <span class="badge rounded-pill bg-{{ $badgeColor }}" 
                                                      style="cursor: pointer;"
                                                      data-bs-toggle="popover" 
                                                      data-bs-placement="top"
                                                      data-bs-html="true"
                                                      data-bs-trigger="hover"
                                                      data-bs-content="
                                                        <div class='text-start'>
                                                            <div class='mb-1'><strong>Qtà Iniziale:</strong> {{ number_format($articolo->giacenza->quantita, 0, ',', '.') }}</div>
                                                            <div class='mb-1'><strong>Qtà Residua:</strong> {{ number_format($articolo->giacenza->quantita_residua, 0, ',', '.') }}</div>
                                                            @if($inProdottoFinito && $prodottoFinito)
                                                                <div class='text-warning mt-2'><strong>In Prodotto Finito:</strong><br>{{ $prodottoFinito->codice }}</div>
                                                            @endif
                                                            @if($articolo->data_carico)
                                                                <div class='text-muted small mt-2'>Caricato: {{ \Carbon\Carbon::parse($articolo->data_carico)->format('d/m/Y') }}</div>
                                                            @endif
                                                        </div>
                                                      ">
                                                    {{ number_format($giacenzaMostrata, 0, ',', '.') }}
                                                </span>
                                            </div>
                                            
                                            <!-- Stato -->
                                            <div class="mb-1">
                                                @if($inProdottoFinito && $prodottoFinito)
                                                    <a href="{{ route('prodotti-finiti.dettaglio', $prodottoFinito->id) }}" 
                                                       class="badge bg-warning-subtle text-warning text-decoration-none"
                                                       data-bs-toggle="tooltip"
                                                       title="Usato in: {{ $prodottoFinito->codice }} - {{ $prodottoFinito->descrizione }}">
                                                        <iconify-icon icon="solar:package-bold" class="me-1"></iconify-icon>
                                                        In un PF
                                                    </a>
                                                @elseif($articolo->giacenza->quantita_residua > 0)
                                                    <span class="badge bg-success-subtle text-success">Giacente</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">Scaricato</span>
                                                @endif
                                            </div>
                                            
                                            <!-- Vetrina -->
                                            @if($articolo->inventariato)
                                                <div class="mb-1">
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <iconify-icon icon="solar:eye-bold" class="text-warning"></iconify-icon>
                                                        <span class="badge bg-warning-subtle text-warning">In Vetrina</span>
                                                    </div>
                                                    @if($articolo->articoliVetrina && $articolo->articoliVetrina->first() && $articolo->articoliVetrina->first()->prezzo_vetrina)
                                                        @php
                                                            $prezzoVetrina = $articolo->articoliVetrina->first()->prezzo_vetrina;
                                                        @endphp
                                                        <small class="text-muted">
                                                            @if(is_numeric($prezzoVetrina))
                                                                €{{ number_format((float)$prezzoVetrina, 0, ',', '.') }}
                                                            @else
                                                                {{ $prezzoVetrina }}
                                                            @endif
                                                        </small>
                                                    @endif
                                                </div>
                                            @endif
                                        @else
                                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                                <iconify-icon icon="solar:close-circle-bold" class="text-danger fs-5"></iconify-icon>
                                                <span class="badge rounded-pill bg-danger">0</span>
                                            </div>
                                            <span class="badge bg-danger-subtle text-danger">Esaurito</span>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['costo_unitario'] ?? true)
                                <td>
                                    <div class="text-center">
                                        @if($articolo->prezzo_acquisto)
                                            <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                                                <iconify-icon icon="solar:dollar-bold" class="text-warning"></iconify-icon>
                                                <span class="fw-semibold text-warning">€{{ number_format($articolo->prezzo_acquisto, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['prezzo_fornitore'] ?? true)
                                <td>
                                    <div class="text-center">
                                        @if($articolo->prezzo_fornitore)
                                            <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                                                <iconify-icon icon="solar:tag-price-bold" class="text-success"></iconify-icon>
                                                <span class="fw-semibold text-success">€{{ number_format($articolo->prezzo_fornitore, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['valore_totale'] ?? true)
                                <td>
                                    <div class="text-center">
                                        @if($articolo->prezzo_acquisto && $articolo->giacenza)
                                            <span class="fw-semibold ">€{{ number_format($articolo->prezzo_acquisto * $articolo->giacenza->quantita_residua, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['dati_carico'] ?? true)
                                <td>
                                    <div class="text-center">
                                        @php
                                            // Preferisci fornitore articolo, poi fattura, poi DDT
                                            $fattura = $articolo->fatturaDettaglio->first()?->fattura;
                                            $ddt = $articolo->ddtDettaglio->first()?->ddt;
                                            $documento = $fattura ?? $ddt;
                                            $tipoDocumento = $fattura ? 'FATTURA' : 'DDT';
                                            $badgeColor = $fattura ? 'success' : 'primary';
                                            $fornitoreDocumento = $documento?->fornitore;
                                            $documentoListUrl = $documento
                                                ? route('documenti-acquisto.index', ['search' => $documento->numero, 'tipoDocumento' => $fattura ? 'fattura' : 'ddt'])
                                                : null;
                                            $documentoPdfUrl = null;
                                            if ($documento) {
                                                if ($documento->tipo_carico === 'ocr' && $documento->ocrDocument) {
                                                    $documentoPdfUrl = route('ocr.documents.pdf', $documento->ocrDocument);
                                                } elseif (!empty($documento->allegato_path)) {
                                                    $documentoPdfUrl = $fattura
                                                        ? route('documenti-acquisto.fattura.pdf', $documento)
                                                        : route('documenti-acquisto.ddt.pdf', $documento);
                                                }
                                            }
                                        @endphp
                                        
                                        <!-- Fornitore -->
                                        <div class="mb-1">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <iconify-icon icon="solar:shop-bold" class="text-warning"></iconify-icon>
                                                <small class="fw-semibold">
                                                    {{ Str::limit($fornitoreDocumento?->ragione_sociale ?? 'N/A', 15) }}
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <!-- Numero Documento -->
                                        <div class="mb-1">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <iconify-icon icon="solar:document-bold" class="text-info"></iconify-icon>
                                                @if($documento)
                                                    <a href="{{ $documentoPdfUrl ?? $documentoListUrl }}"
                                                       class="link-primary text-decoration-underline small"
                                                       @if($documentoPdfUrl) target="_blank" @endif>
                                                        {{ $documento->numero }}
                                                    </a>
                                                @else
                                                    <small class="text-muted">N/A</small>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <!-- Data e Tipo -->
                                        <div class="mb-1">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <iconify-icon icon="solar:calendar-bold" class="text-primary"></iconify-icon>
                                                <small class="text-muted">
                                                    @if($documento && $documento->data_documento)
                                                        {{ $documento->data_documento->format('d/m/Y') }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </small>
                                                <span class="badge bg-{{ $badgeColor }}-subtle text-{{ $badgeColor }} fs-11">{{ $tipoDocumento }}</span>
                                            </div>
                                        </div>
                                        
                                        @if($fattura && $ddt)
                                            <!-- Se ci sono entrambi, mostra anche il DDT -->
                                            <div class="mb-1 mt-2 pt-2 border-top">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <iconify-icon icon="solar:document-bold" class="text-info small"></iconify-icon>
                                                    <small class="text-muted">
                                                        DDT:
                                                        @php
                                                            $ddtPdfUrl = null;
                                                            if ($ddt->tipo_carico === 'ocr' && $ddt->ocrDocument) {
                                                                $ddtPdfUrl = route('ocr.documents.pdf', $ddt->ocrDocument);
                                                            } elseif (!empty($ddt->allegato_path)) {
                                                                $ddtPdfUrl = route('documenti-acquisto.ddt.pdf', $ddt);
                                                            }
                                                            $ddtListUrl = route('documenti-acquisto.index', ['search' => $ddt->numero, 'tipoDocumento' => 'ddt']);
                                                        @endphp
                                                        <a href="{{ $ddtPdfUrl ?? $ddtListUrl }}"
                                                           class="link-primary text-decoration-underline"
                                                           @if($ddtPdfUrl) target="_blank" @endif>
                                                            {{ $ddt->numero }}
                                                        </a>
                                                    </small>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                @endif
                                @if($visibleColumns['ubicazione'] ?? true)
                                <td>
                                    @if($articolo->contoDepositoCorrente && $articolo->quantita_in_deposito > 0)
                                        <div class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <iconify-icon icon="solar:map-point-bold" class="text-warning"></iconify-icon>
                                                <span class="fw-semibold">{{ $articolo->contoDepositoCorrente->sedeDestinataria->nome ?? 'N/D' }}</span>
                                            </div>
                                            <small class="text-muted">
                                                {{ $articolo->contoDepositoCorrente->sedeDestinataria->citta ?? 'Conto deposito' }}
                                            </small>
                                        </div>
                                    @elseif($articolo->giacenza && $articolo->giacenza->sede)
                                        <div class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <iconify-icon icon="solar:map-point-bold" class="text-danger"></iconify-icon>
                                                <span class="fw-semibold">{{ $articolo->giacenza->sede->nome }}</span>
                                            </div>
                                            <small class="text-muted">{{ $articolo->giacenza->sede->citta }}</small>
                                        </div>
                                    @elseif($articolo->sede)
                                        <div class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <iconify-icon icon="solar:map-point-bold" class="text-danger"></iconify-icon>
                                                <span class="fw-semibold">{{ $articolo->sede->nome }}</span>
                                            </div>
                                            <small class="text-muted">{{ $articolo->sede->citta }}</small>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                @endif
                                @if($visibleColumns['azioni'] ?? true)
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown">
                                            <iconify-icon icon="solar:menu-dots-bold" class="text-secondary"></iconify-icon>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('articoli.show', $articolo->id) }}">
                                                    <iconify-icon icon="solar:eye-bold" class="text-primary me-2"></iconify-icon>
                                                    Visualizza
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" wire:click="apriModalFoto({{ $articolo->id }})">
                                                    <iconify-icon icon="solar:camera-bold" class="text-info me-2"></iconify-icon>
                                                    Gestisci Immagine
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" wire:click="apriModalModifica({{ $articolo->id }})">
                                                    <iconify-icon icon="solar:pen-bold" class="text-warning me-2"></iconify-icon>
                                                    Modifica
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" wire:click="apriModalStampa({{ $articolo->id }})">
                                                    <iconify-icon icon="solar:printer-bold" class="text-success me-2"></iconify-icon>
                                                    Stampa Etichetta
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-success" wire:click="apriModalRicarico({{ $articolo->id }})">
                                                    <iconify-icon icon="solar:box-add-bold" class="text-success me-2"></iconify-icon>
                                                    Ricarica Quantità
                                                </button>
                                            </li>
                                            @if($articolo->stato_articolo === 'disponibile')
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button class="dropdown-item text-danger" wire:click="scaricaArticolo({{ $articolo->id }})">
                                                        <iconify-icon icon="solar:box-remove-bold" class="text-danger me-2"></iconify-icon>
                                                        Scarica Articolo
                                                    </button>
                                                </li>
                                            @elseif($articolo->stato_articolo === 'scaricato')
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button class="dropdown-item text-success" wire:click="ripristinaArticolo({{ $articolo->id }})">
                                                        <iconify-icon icon="solar:box-add-bold" class="text-success me-2"></iconify-icon>
                                                        Ripristina Articolo
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            @php
                                $colspan = count(array_filter($visibleColumns ?? []));
                                if ($isOrologioCategory) {
                                    $colspan -= ($visibleColumns['specifiche'] ?? false) ? 1 : 0;
                                    $colspan -= ($visibleColumns['caratura'] ?? false) ? 1 : 0;
                                }
                                $colspan = max(1, $colspan);
                            @endphp
                            <tr>
                                <td colspan="{{ $colspan }}" class="text-center py-4">
                                    <iconify-icon icon="solar:magnifer-zoom-out-bold" class="fs-48 text-muted mb-3"></iconify-icon>
                                    <h5 class="text-muted">Nessun articolo trovato</h5>
                                    <p class="text-muted">Prova a modificare i filtri di ricerca per trovare gli articoli</p>
                                    <button class="btn btn-primary" wire:click="resetFilters">
                                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                                        Reset Filtri
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginazione Corretta Larkon -->
        @if($articoli->hasPages())
            <div class="card-footer border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-0 small">
                            <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                            Mostrando <strong>{{ $articoli->firstItem() }}</strong> - <strong>{{ $articoli->lastItem() }}</strong> 
                            di <strong>{{ number_format($articoli->total()) }}</strong> articoli
                        </p>
                    </div>
                    <nav aria-label="Page navigation example">
                        {{ $articoli->links() }}
                    </nav>
                </div>
            </div>
        @endif
    </div>

    <!-- Modal Scarico Parziale -->
    @if($showModalScarico && $articoloDaScaricare)
        <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:box-remove-bold" class="text-danger me-2"></iconify-icon>
                            Scarico Parziale
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModalScarico"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                    <strong>Articolo:</strong> {{ $articoloDaScaricare->codice }} - {{ $articoloDaScaricare->descrizione }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Giacenza Disponibile</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <iconify-icon icon="solar:box-bold"></iconify-icon>
                                    </span>
                                    <input type="text" class="form-control" value="{{ $giacenzaDisponibile }}" readonly>
                                    <span class="input-group-text">pezzi</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Quantità da Scaricare</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <iconify-icon icon="solar:minus-bold"></iconify-icon>
                                    </span>
                                    <input type="number" class="form-control" wire:model.live="quantitaDaScaricare" 
                                           min="1" max="{{ $giacenzaDisponibile }}">
                                    <span class="input-group-text">pezzi</span>
                                </div>
                                @error('quantitaDaScaricare')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        @if($quantitaDaScaricare > 0 && $quantitaDaScaricare <= $giacenzaDisponibile)
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                                        <strong>Giacenza Residua:</strong> {{ $giacenzaDisponibile - $quantitaDaScaricare }} pezzi
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalScarico">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="confermaScaricoParziale"
                                @if($quantitaDaScaricare <= 0 || $quantitaDaScaricare > $giacenzaDisponibile) disabled @endif>
                            <iconify-icon icon="solar:box-remove-bold" class="me-1"></iconify-icon>
                            Scarica {{ $quantitaDaScaricare }} pezzi
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    <!-- Modal Ricarico Quantità -->
    @if($showModalRicarico && $articoloDaRicaricare)
        <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:box-add-bold" class="text-success me-2"></iconify-icon>
                            Ricarica Quantità
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModalRicarico"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                            <strong>Articolo:</strong> {{ $articoloDaRicaricare->codice }} - {{ $articoloDaRicaricare->descrizione }}
                            <br>
                            <strong>Quantità ripristinabile:</strong> {{ $giacenzaMancante }}
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantità da ripristinare</label>
                            <input type="number" class="form-control" min="1" max="{{ $giacenzaMancante }}" wire:model.defer="quantitaDaRicaricare">
                            <small class="text-muted">Max: {{ $giacenzaMancante }}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalRicarico">Annulla</button>
                        <button type="button" class="btn btn-success" wire:click="confermaRicarico">Ripristina</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    <!-- Modal Stampa Etichetta -->
    @if($showModalStampa && $articoloDaStampare)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:printer-bold-duotone" class="me-2"></iconify-icon>
                            Stampa Etichetta
                        </h5>
                        <button type="button" wire:click="chiudiModalStampa" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <h6 class="fw-bold">Articolo: {{ $articoloDaStampare->codice }}</h6>
                            <p class="text-muted mb-0">{{ $articoloDaStampare->descrizione }}</p>
                        </div>

                        <div class="mb-3">
                            <label for="stampanteSelezionata" class="form-label fw-semibold">
                                <iconify-icon icon="solar:printer-bold-duotone" class="me-1"></iconify-icon>
                                Stampante
                            </label>
                            @if(!empty($stampantiDisponibili))
                                <select class="form-select" id="stampanteSelezionata" wire:model.live="stampanteSelezionata">
                                    <option value="">Seleziona una stampante...</option>
                                    @foreach($stampantiDisponibili as $stampante)
                                        <option value="{{ $stampante['id'] }}">
                                            {{ $stampante['nome'] }} ({{ $stampante['modello'] }}) - {{ $stampante['ip_address'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                                    Sono mostrate solo le stampanti compatibili con questo articolo
                                </div>
                            @else
                                <div class="alert alert-warning">
                                    <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                                    <strong>Nessuna stampante disponibile</strong><br>
                                    Non ci sono stampanti compatibili con questo articolo o non hai i permessi necessari.
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tipo cartellino</label>
                            <select class="form-select" wire:model.live="layoutEtichetta">
                                <option value="standard">Standard (con carico)</option>
                                <option value="nc_prezzo">NC - Solo prezzo</option>
                                <option value="nc_prezzo_carati">NC - Prezzo + carati</option>
                                <option value="nc_prezzo_completo">NC - Prezzo + oro + pietre</option>
                            </select>
                            <div class="form-text">
                                NC = senza carico. Le voci oro/pietre sono prese dai dati articolo.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Formato Prezzo</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" wire:model.live="formatoPrezzo" value="euro" id="formatoEuro">
                                    <label class="form-check-label" for="formatoEuro">
                                        Euro (€)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" wire:model.live="formatoPrezzo" value="codificato" id="formatoCodificato">
                                    <label class="form-check-label" for="formatoCodificato">
                                        Codificato (es. 345X3P3)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Prezzo da stampare</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" wire:model.live="prezzoEtichettaFonte" value="fornitore" id="prezzoFonteFornitore">
                                    <label class="form-check-label" for="prezzoFonteFornitore">
                                        Fornitore
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" wire:model.live="prezzoEtichettaFonte" value="vetrina" id="prezzoFonteVetrina">
                                    <label class="form-check-label" for="prezzoFonteVetrina">
                                        Vetrina
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" wire:model.live="prezzoEtichettaFonte" value="manuale" id="prezzoFonteManuale">
                                    <label class="form-check-label" for="prezzoFonteManuale">
                                        Manuale
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted">Selezione valida per tutte le stampe finché non la cambi.</small>
                        </div>

                        <div class="mb-3">
                            <label for="prezzoEtichetta" class="form-label fw-semibold">
                                Prezzo per Etichetta
                                @if($formatoPrezzo === 'euro')
                                    <small class="text-muted">(formato: 123,45)</small>
                                @else
                                    <small class="text-muted">(formato: 345X3P3)</small>
                                @endif
                            </label>
                            <div class="input-group">
                                @if($formatoPrezzo === 'euro')
                                    <span class="input-group-text">€</span>
                                @endif
                                <input type="text" 
                                       class="form-control" 
                                       id="prezzoEtichetta"
                                       wire:model.live="prezzoEtichetta"
                                       @if($formatoPrezzo === 'euro')
                                           placeholder="123,45"
                                       @else
                                           placeholder="345X3P3"
                                       @endif>
                            </div>
                            @if($formatoPrezzo === 'euro')
                                <div class="form-text">
                                    <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                                    Usa la virgola come separatore decimale
                                </div>
                            @else
                                <div class="form-text">
                                    <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                                    Formato libero per codici speciali
                                </div>
                            @endif
                        </div>

                        @if(!empty($prezzoEtichetta))
                            <div class="alert alert-info">
                                <iconify-icon icon="solar:eye-bold" class="me-2"></iconify-icon>
                                <strong>Anteprima:</strong> 
                                @if($formatoPrezzo === 'euro')
                                    €{{ number_format((float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', $prezzoEtichetta)), 2, ',', '.') }}
                                @else
                                    {{ $prezzoEtichetta }}
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalStampa">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-success" wire:click="confermaStampaEtichetta"
                                @if(empty($prezzoEtichetta) || empty($stampanteSelezionata) || empty($stampantiDisponibili)) disabled @endif>
                            <iconify-icon icon="solar:printer-bold" class="me-1"></iconify-icon>
                            Stampa Etichetta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    <!-- Modal Modifica Articolo -->
    @if($showModalModifica && $articoloDaModificare)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:pen-bold" class="me-2"></iconify-icon>
                            Modifica Articolo
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModalModifica"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Codice</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.codice" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Descrizione</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.descrizione">
                                @error('modifica.descrizione')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrizione estesa</label>
                                <textarea class="form-control" rows="2" wire:model.defer="modifica.descrizione_estesa"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" wire:model.defer="modifica.categoria_merceologica_id">
                                    <option value="">Seleziona categoria</option>
                                    @foreach($magazzini as $magazzino)
                                        <option value="{{ $magazzino->id }}">{{ $magazzino->id }} - {{ $magazzino->nome }}</option>
                                    @endforeach
                                </select>
                                @error('modifica.categoria_merceologica_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Prezzo acquisto</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.prezzo_acquisto" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Prezzo fornitore</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.prezzo_fornitore" placeholder="€ 0,00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Modello</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.modello">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Materiale</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.materiale">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Colore</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.colore">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Titolo</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.titolo">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Caratura</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.caratura">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Peso lordo (g)</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.peso_lordo">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Peso netto (g)</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.peso_netto">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.marca">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Referenza</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.referenza">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">EAN</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.ean">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Numero seriale</label>
                                <input type="text" class="form-control" wire:model.defer="modifica.numero_seriale">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Note</label>
                                <textarea class="form-control" rows="2" wire:model.defer="modifica.note"></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Opzioni</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="modificaInVetrina" wire:model.defer="modifica.in_vetrina">
                                        <label class="form-check-label" for="modificaInVetrina">In vetrina</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="modificaInventariato" wire:model.defer="modifica.inventariato">
                                        <label class="form-check-label" for="modificaInventariato">Inventariato</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="modificaCatalogo" wire:model.defer="modifica.visibile_catalogo">
                                        <label class="form-check-label" for="modificaCatalogo">Visibile catalogo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalModifica">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="salvaModificaArticolo">
                            <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>
                            Salva modifiche
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
    
    <!-- Modal Gestione Immagine -->
    @if($showModalFoto && $articoloFotoTarget)
        <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog" wire:poll.4s="verificaUploadFotoMobile">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:camera-bold" class="text-info me-2"></iconify-icon>
                            Gestione Immagine - {{ $articoloFotoTarget->codice }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModalFoto"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Anteprima attuale</label>
                                @php
                                    $fotoPrincipale = $articoloFotoTarget->foto_principale;
                                    $fotoUrl = null;
                                    if (!empty($fotoPrincipale)) {
                                        if (Str::startsWith($fotoPrincipale, ['http://', 'https://'])) {
                                            $fotoUrl = $fotoPrincipale;
                                        } elseif (Str::startsWith($fotoPrincipale, ['/storage/', 'storage/'])) {
                                            $fotoUrl = asset(ltrim($fotoPrincipale, '/'));
                                        } else {
                                            $fotoUrl = asset('storage/' . ltrim($fotoPrincipale, '/'));
                                        }
                                    }
                                @endphp
                                <div class="border rounded p-3 text-center bg-light-subtle">
                                    @if($fotoUrl)
                                        <img src="{{ $fotoUrl }}" alt="Foto {{ $articoloFotoTarget->codice }}" class="img-fluid rounded" style="max-height: 280px;">
                                    @else
                                        <div class="text-muted py-5">
                                            <iconify-icon icon="solar:camera-off-bold" class="fs-40 mb-2"></iconify-icon>
                                            <div>Nessuna immagine presente</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Carica / sostituisci immagine</label>
                                <input
                                    id="articoloFotoUploadInput"
                                    type="file"
                                    class="form-control"
                                    wire:model="fotoUpload"
                                    accept="image/*"
                                    data-auto-resize-photo="1"
                                >
                                <div class="form-text">Ridimensionamento automatico attivo (max 1920px).</div>
                                @error('fotoUpload')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                                <div id="articoloFotoUploadFeedback" class="alert d-none mt-2 py-2 px-3" role="alert"></div>
                                @if($fotoUpload)
                                    <div class="mt-3 text-center border rounded p-2">
                                        <img src="{{ $fotoUpload->temporaryUrl() }}" alt="Anteprima nuova foto" class="img-fluid rounded" style="max-height: 180px;">
                                    </div>
                                @endif

                                <hr>
                                <label class="form-label fw-semibold">Upload da cellulare (QR)</label>
                                @if(!empty($mobileUploadQrBase64))
                                    <div class="text-center mb-2">
                                        <img src="data:image/png;base64,{{ $mobileUploadQrBase64 }}" alt="QR upload foto" class="img-fluid border rounded p-2 bg-white" style="max-width: 220px;">
                                    </div>
                                @endif
                                @if(!empty($mobileUploadUrl))
                                    <div class="small text-muted mb-2">Scansiona il QR o apri il link:</div>
                                    <a href="{{ $mobileUploadUrl }}" target="_blank" class="small d-block text-break">{{ $mobileUploadUrl }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalFoto">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Chiudi
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="eliminaFotoArticolo"
                                onclick="return confirm('Eliminare la foto di questo articolo?')">
                            <iconify-icon icon="solar:trash-bin-trash-bold" class="me-1"></iconify-icon>
                            Elimina Immagine
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="salvaFotoArticolo">
                            <iconify-icon icon="solar:upload-bold" class="me-1"></iconify-icon>
                            Salva Immagine
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

@once
    <div class="modal fade" id="articoloFotoModal" tabindex="-1" aria-labelledby="articoloFotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="articoloFotoModalLabel">Foto articolo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="articoloFotoModalImg" src="" alt="" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('articoloFotoModal');
                if (!modalEl) {
                    return;
                }

                modalEl.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    if (!trigger) {
                        return;
                    }
                    const img = modalEl.querySelector('#articoloFotoModalImg');
                    if (!img) {
                        return;
                    }
                    const fotoUrl = trigger.getAttribute('data-foto-url') || '';
                    const fotoAlt = trigger.getAttribute('data-foto-alt') || 'Foto articolo';
                    img.src = fotoUrl;
                    img.alt = fotoAlt;
                });

                modalEl.addEventListener('hidden.bs.modal', function () {
                    const img = modalEl.querySelector('#articoloFotoModalImg');
                    if (img) {
                        img.src = '';
                        img.alt = '';
                    }
                });
            });

            const photoFeedbackEl = () => document.getElementById('articoloFotoUploadFeedback');
            const showPhotoFeedback = (message, type = 'danger') => {
                const el = photoFeedbackEl();
                if (!el) {
                    return;
                }
                el.className = `alert alert-${type} mt-2 py-2 px-3`;
                el.textContent = message;
            };
            const clearPhotoFeedback = () => {
                const el = photoFeedbackEl();
                if (!el) {
                    return;
                }
                el.className = 'alert d-none mt-2 py-2 px-3';
                el.textContent = '';
            };

            const readImageFromFile = (file) => new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const img = new Image();
                    img.onload = () => resolve(img);
                    img.onerror = reject;
                    img.src = reader.result;
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });

            const autoResizeImageFile = async (file, maxSide = 1920, targetBytes = 2 * 1024 * 1024) => {
                if (!file || !file.type || !file.type.startsWith('image/')) {
                    return file;
                }

                const image = await readImageFromFile(file);
                const ratio = Math.min(1, maxSide / Math.max(image.width, image.height));
                const width = Math.max(1, Math.round(image.width * ratio));
                const height = Math.max(1, Math.round(image.height * ratio));

                if (ratio === 1 && file.size <= targetBytes) {
                    return file;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    return file;
                }

                ctx.drawImage(image, 0, 0, width, height);

                const preferJpeg = file.type === 'image/jpeg' || file.type === 'image/jpg';
                const mime = preferJpeg ? 'image/jpeg' : 'image/webp';
                let quality = 0.86;
                let blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, quality));

                while (blob && blob.size > targetBytes && quality > 0.55) {
                    quality -= 0.08;
                    blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, quality));
                }

                if (!blob || blob.size >= file.size) {
                    return file;
                }

                const name = file.name.replace(/\.[^.]+$/, '') + (mime === 'image/jpeg' ? '.jpg' : '.webp');
                return new File([blob], name, { type: mime, lastModified: Date.now() });
            };

            const replaceInputWithFile = (input, file) => {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            };

            document.addEventListener('change', async (event) => {
                const input = event.target;
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }
                if (!input.matches('input[data-auto-resize-photo="1"]')) {
                    return;
                }
                if (input.dataset.skipAutoResizeOnce === '1') {
                    input.dataset.skipAutoResizeOnce = '0';
                    return;
                }

                const file = input.files?.[0];
                if (!file) {
                    clearPhotoFeedback();
                    return;
                }

                try {
                    event.stopImmediatePropagation();
                    clearPhotoFeedback();
                    const resized = await autoResizeImageFile(file);
                    replaceInputWithFile(input, resized);
                    input.dataset.skipAutoResizeOnce = '1';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    showPhotoFeedback('Errore durante l\'ottimizzazione immagine. Riprova.');
                }
            }, true);

            const cleanupOffcanvasScrollLock = () => {
                const anyOpen = document.querySelector('.offcanvas.show');
                if (anyOpen) {
                    return;
                }
                const htmlEl = document.documentElement;
                document.body.classList.remove('offcanvas-backdrop', 'modal-open');
                document.body.classList.remove('overflow-hidden');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                if (htmlEl) {
                    htmlEl.style.overflow = '';
                    htmlEl.style.paddingRight = '';
                }
                const backdrops = document.querySelectorAll('.offcanvas-backdrop');
                backdrops.forEach((backdrop) => backdrop.remove());
            };

            document.addEventListener('livewire:init', () => {
                Livewire.on('close-filters-canvas', () => {
                    const canvasEl = document.getElementById('articoliFiltersCanvas');
                    if (!canvasEl) {
                        return;
                    }
                    const instance = bootstrap.Offcanvas.getInstance(canvasEl) || new bootstrap.Offcanvas(canvasEl);
                    instance.hide();
                    setTimeout(cleanupOffcanvasScrollLock, 50);
                });

                if (Livewire.hook) {
                    Livewire.hook('commit', ({ succeed }) => {
                        succeed(() => cleanupOffcanvasScrollLock());
                    });
                }

                if (Livewire.hook) {
                    Livewire.hook('request', ({ fail }) => {
                        fail(({ status }) => {
                            if (Number(status) === 413) {
                                showPhotoFeedback('Immagine troppo grande. Riduci dimensione/qualita e riprova.');
                            }
                        });
                    });
                }

                Livewire.on('foto-mobile-upload-rilevato', (payload) => {
                    const codice = payload?.codice ? ` (${payload.codice})` : '';
                    showPhotoFeedback(`Foto aggiornata da cellulare${codice}.`, 'success');
                });
            });

            document.addEventListener('hidden.bs.offcanvas', (event) => {
                const target = event.target;
                if (!target || !target.classList.contains('offcanvas')) {
                    return;
                }
                cleanupOffcanvasScrollLock();
            });

            const observer = new MutationObserver(() => {
                cleanupOffcanvasScrollLock();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        </script>
    @endpush
@endonce
</div>
