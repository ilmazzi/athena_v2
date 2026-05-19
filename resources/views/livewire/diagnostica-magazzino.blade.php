<div>
    {{-- Header --}}
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0 fw-bold text-danger">
                    <iconify-icon icon="solar:bug-bold" class="me-2"></iconify-icon>
                    Diagnostica Magazzino
                </h4>
                <small class="text-muted">Strumento chirurgico per trovare anomalie nei dati</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-secondary fs-6">{{ number_format($count) }} articoli trovati</span>
                <button wire:click="exportExcel" class="btn btn-success btn-sm">
                    <iconify-icon icon="solar:file-download-bold" class="me-1"></iconify-icon>
                    Export Excel
                </button>
                <button wire:click="resetFiltri" class="btn btn-outline-secondary btn-sm">
                    <iconify-icon icon="solar:restart-bold" class="me-1"></iconify-icon>
                    Reset filtri
                </button>
            </div>
        </div>
    </div>

    {{-- FILTRI --}}
    <div class="card mb-3 border-warning">
        <div class="card-header bg-warning bg-opacity-10 fw-bold py-2">
            <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon> Filtri
        </div>
        <div class="card-body py-3">
            <div class="row g-2">

                {{-- Ricerca --}}
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Cerca (codice, descrizione, seriale, EAN)</label>
                    <input wire:model.live.debounce.400ms="search" type="text"
                           class="form-control form-control-sm" placeholder="es. 2-64333 o Rolex...">
                </div>

                {{-- Magazzino prefisso --}}
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1">Mag.</label>
                    <select wire:model.live="magazzino" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        @foreach(range(1,22) as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Categoria --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Categoria</label>
                    <select wire:model.live="categoriaId" class="form-select form-select-sm">
                        <option value="">Tutte</option>
                        @foreach($categorie as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->codice }} — {{ $cat->nome }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Sede --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Sede</label>
                    <select wire:model.live="sedeId" class="form-select form-select-sm">
                        <option value="">Tutte</option>
                        @foreach($sedi as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Stato articolo --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Stato articolo</label>
                    <select wire:model.live="statoArticolo" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="disponibile">disponibile</option>
                        <option value="scaricato">scaricato</option>
                        <option value="in_prodotto_finito">in_prodotto_finito</option>
                        <option value="scaricato_in_pf">scaricato_in_pf</option>
                    </select>
                </div>

                {{-- Tipo carico --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Tipo carico</label>
                    <select wire:model.live="tipoCarico" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="ddt">DDT</option>
                        <option value="fattura">Fattura</option>
                        <option value="manuale">Manuale</option>
                        <option value="produzione_interna">Produzione interna</option>
                    </select>
                </div>

                {{-- Fornitore --}}
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Fornitore (contiene)</label>
                    <input wire:model.live.debounce.400ms="fornitoreSearch" type="text"
                           class="form-control form-control-sm" placeholder="es. Rolex, De Pascalis...">
                </div>

                {{-- Qta min/max --}}
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1">Qta min</label>
                    <input wire:model.live.debounce.500ms="qtaMin" type="number"
                           class="form-control form-control-sm" placeholder="0">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1">Qta max</label>
                    <input wire:model.live.debounce.500ms="qtaMax" type="number"
                           class="form-control form-control-sm" placeholder="∞">
                </div>

                {{-- Data carico --}}
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Data carico da</label>
                    <input wire:model.live="dataCaricoDa" type="date" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Data carico a</label>
                    <input wire:model.live="dataCaricoA" type="date" class="form-control form-control-sm">
                </div>

                {{-- Eliminati --}}
                <div class="col-md-2 d-flex flex-column justify-content-end">
                    <div class="form-check form-check-sm">
                        <input wire:model.live="includiEliminati" class="form-check-input" type="checkbox" id="incElim">
                        <label class="form-check-label small" for="incElim">Includi eliminati (soft-delete)</label>
                    </div>
                    <div class="form-check form-check-sm">
                        <input wire:model.live="soloEliminati" class="form-check-input" type="checkbox" id="soloElim">
                        <label class="form-check-label small" for="soloElim">Solo eliminati</label>
                    </div>
                </div>

            </div>

            {{-- Anomalie preset --}}
            <div class="row g-2 mt-1">
                <div class="col-12">
                    <label class="form-label form-label-sm mb-1 text-danger fw-bold">
                        <iconify-icon icon="solar:danger-triangle-bold" class="me-1"></iconify-icon>
                        Filtro anomalia rapida
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($this->anomalieDisponibili as $key => $label)
                            <button wire:click="$set('anomalia', '{{ $key }}')"
                                class="btn btn-sm {{ $anomalia === $key ? 'btn-danger' : 'btn-outline-danger' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Colonne selezionabili --}}
    <div class="card mb-3 border-info">
        <div class="card-header bg-info bg-opacity-10 fw-bold py-2"
             data-bs-toggle="collapse" data-bs-target="#colonnePanel" style="cursor:pointer;">
            <iconify-icon icon="solar:columns-bold" class="me-1"></iconify-icon>
            Colonne export
            <small class="text-muted ms-2">({{ count($colonneSelezionate) }} selezionate — clicca per espandere)</small>
        </div>
        <div class="collapse" id="colonnePanel">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($tutteLeColonne as $key => $label)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox"
                                   id="col_{{ $key }}"
                                   wire:click="toggleColonna('{{ $key }}')"
                                   {{ in_array($key, $colonneSelezionate) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="col_{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Tabella --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                <table class="table table-sm table-hover table-bordered mb-0" style="font-size:0.78rem;">
                    <thead class="table-dark sticky-top">
                        @php
                            $sortIcon = fn(string $col) => match(true) {
                                $sortBy === \App\Http\Livewire\DiagnosticaMagazzino::colSql($col) && $sortDir === 'asc'  => '▲',
                                $sortBy === \App\Http\Livewire\DiagnosticaMagazzino::colSql($col) && $sortDir === 'desc' => '▼',
                                default => '⇅',
                            };
                            $thClass = 'sortable-th';
                        @endphp
                        <tr>
                            <th wire:click="ordinaPer('codice')" class="{{ $thClass }}" style="cursor:pointer; white-space:nowrap;">Codice {{ $sortIcon('codice') }}</th>
                            <th wire:click="ordinaPer('mag')" class="{{ $thClass }}" style="cursor:pointer;">Mag. {{ $sortIcon('mag') }}</th>
                            <th wire:click="ordinaPer('descrizione')" class="{{ $thClass }}" style="cursor:pointer;">Descrizione {{ $sortIcon('descrizione') }}</th>
                            <th wire:click="ordinaPer('categoria')" class="{{ $thClass }}" style="cursor:pointer;">Categoria {{ $sortIcon('categoria') }}</th>
                            <th wire:click="ordinaPer('sede')" class="{{ $thClass }}" style="cursor:pointer;">Sede {{ $sortIcon('sede') }}</th>
                            <th wire:click="ordinaPer('qta')" class="{{ $thClass }} text-center" style="cursor:pointer;">Qta {{ $sortIcon('qta') }}</th>
                            <th wire:click="ordinaPer('qta_residua')" class="{{ $thClass }} text-center" style="cursor:pointer;">Qta Res. {{ $sortIcon('qta_residua') }}</th>
                            <th wire:click="ordinaPer('stato_articolo')" class="{{ $thClass }}" style="cursor:pointer;">Stato {{ $sortIcon('stato_articolo') }}</th>
                            <th wire:click="ordinaPer('fornitore')" class="{{ $thClass }}" style="cursor:pointer;">Fornitore {{ $sortIcon('fornitore') }}</th>
                            <th wire:click="ordinaPer('num_documento')" class="{{ $thClass }}" style="cursor:pointer;">N. Doc {{ $sortIcon('num_documento') }}</th>
                            <th wire:click="ordinaPer('data_documento')" class="{{ $thClass }}" style="cursor:pointer;">Data Doc {{ $sortIcon('data_documento') }}</th>
                            <th wire:click="ordinaPer('prezzo_carico')" class="{{ $thClass }} text-end" style="cursor:pointer;">€ Carico {{ $sortIcon('prezzo_carico') }}</th>
                            <th wire:click="ordinaPer('referenza_doc')" class="{{ $thClass }}" style="cursor:pointer;">Ref. Doc {{ $sortIcon('referenza_doc') }}</th>
                            <th wire:click="ordinaPer('materiale')" class="{{ $thClass }}" style="cursor:pointer;">Materiale {{ $sortIcon('materiale') }}</th>
                            <th wire:click="ordinaPer('numero_seriale')" class="{{ $thClass }}" style="cursor:pointer;">N. Seriale {{ $sortIcon('numero_seriale') }}</th>
                            <th>Referenza JSON</th>
                            <th wire:click="ordinaPer('data_carico')" class="{{ $thClass }}" style="cursor:pointer;">Data Carico {{ $sortIcon('data_carico') }}</th>
                            <th wire:click="ordinaPer('deleted_at')" class="{{ $thClass }}" style="cursor:pointer;">Eliminato {{ $sortIcon('deleted_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($articoli as $a)
                            @php
                                $isDeleted   = !empty($a->deleted_at);
                                $qtaRes      = $a->quantita_residua ?? 0;
                                $qtaOrig     = $a->quantita ?? '—';
                                $anomaliaRow = '';
                                if ($isDeleted && $qtaRes > 0) $anomaliaRow = 'table-danger';
                                elseif (!$isDeleted && $qtaRes <= 0) $anomaliaRow = 'table-warning';
                                elseif ($qtaRes > 1) $anomaliaRow = 'table-info';

                                $caratteristiche = $a->caratteristiche ? json_decode($a->caratteristiche, true) : [];
                                $referenza = $caratteristiche['referenza'] ?? '—';

                                // Rileva mismatch categoria/mag
                                $magCodice = (int) explode('-', $a->codice)[0];
                                $catNum = (int) preg_replace('/[^0-9]/', '', $a->categoria_codice ?? '');
                                $mismatch = ($catNum > 0 && $magCodice !== $catNum);
                            @endphp
                            <tr class="{{ $anomaliaRow }}">
                                <td>
                                    <a href="{{ route('articoli.show', $a->id) }}" target="_blank"
                                       class="fw-bold text-decoration-none">{{ $a->codice }}</a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $a->mag }}</span>
                                </td>
                                <td>{{ Str::limit($a->descrizione, 50) }}</td>
                                <td>
                                    <span class="{{ $mismatch ? 'text-danger fw-bold' : '' }}" title="{{ $mismatch ? 'Mismatch: codice=' . $magCodice . ' cat=' . $catNum : '' }}">
                                        {{ $a->categoria_codice ?? '—' }}
                                        @if($mismatch) <iconify-icon icon="solar:danger-triangle-bold"></iconify-icon>@endif
                                    </span>
                                </td>
                                <td>{{ $a->sede_nome ?? '—' }}</td>
                                <td class="text-center">{{ $qtaOrig }}</td>
                                <td class="text-center fw-bold {{ $qtaRes > 1 ? 'text-primary' : ($qtaRes <= 0 ? 'text-muted' : '') }}">
                                    {{ $qtaRes }}
                                </td>
                                <td>
                                    <span class="badge {{ match($a->stato_articolo) {
                                        'disponibile' => 'bg-success',
                                        'scaricato'   => 'bg-secondary',
                                        'in_prodotto_finito', 'scaricato_in_pf' => 'bg-warning text-dark',
                                        default       => 'bg-light text-dark'
                                    } }}">{{ $a->stato_articolo }}</span>
                                </td>
                                {{-- Dati carico --}}
                                <td class="{{ empty($a->fornitore_nome) ? 'text-danger' : '' }}" style="white-space:nowrap;">
                                    {{ $a->fornitore_nome ?: '—' }}
                                </td>
                                <td class="text-muted small">{{ $a->num_documento ?: '—' }}</td>
                                <td class="text-muted small" style="white-space:nowrap;">
                                    {{ $a->data_documento ? \Carbon\Carbon::parse($a->data_documento)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="text-end" style="white-space:nowrap;">
                                    {{ $a->prezzo_carico !== null ? '€ ' . number_format((float)$a->prezzo_carico, 2, ',', '.') : '—' }}
                                </td>
                                <td class="text-muted small">{{ $a->referenza_doc ?: '—' }}</td>
                                {{-- Fine dati carico --}}
                                <td>{{ $a->materiale ?? '—' }}</td>
                                <td class="{{ empty($a->numero_seriale) ? 'text-danger' : '' }}">
                                    {{ $a->numero_seriale ?: '—' }}
                                </td>
                                <td class="{{ $referenza === '—' ? 'text-danger' : '' }}">
                                    {{ $referenza }}
                                </td>
                                <td>{{ $a->data_carico ?? '—' }}</td>
                                <td class="{{ $isDeleted ? 'text-danger fw-bold' : '' }}">
                                    {{ $isDeleted ? \Carbon\Carbon::parse($a->deleted_at)->format('d/m/Y') : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="text-center text-muted py-4">
                                    Nessun articolo trovato con i filtri selezionati.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex align-items-center justify-content-between py-2">
            <div class="d-flex align-items-center gap-3">
                {{ $articoli->links() }}
            </div>
            <div>
                <label class="form-label-sm me-1 small">Righe per pagina:</label>
                <select wire:model.live="perPage" class="form-select form-select-sm d-inline-block" style="width:80px;">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Legenda colori --}}
    <div class="mt-2 d-flex gap-3 small text-muted">
        <span><span class="badge bg-danger">rosso</span> Eliminato con qta > 0</span>
        <span><span class="badge bg-warning text-dark">giallo</span> Attivo ma qta = 0</span>
        <span><span class="badge bg-info">blu</span> Qta > 1</span>
        <span><iconify-icon icon="solar:danger-triangle-bold" class="text-danger"></iconify-icon> Categoria/Mag discordanti</span>
    </div>
</div>
