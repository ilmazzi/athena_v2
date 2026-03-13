<div>
    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:close-circle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Statistiche -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">Prodotti Totali</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['totali']) }}</h4>
                            <small class="text-muted">Finiti + Semilavorati</small>
                        </div>
                        <div class="bg-primary-subtle rounded p-2">
                            <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="fs-4 text-primary"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">Completati</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['completati']) }}</h4>
                            <small class="text-muted">Pronti per vendita</small>
                        </div>
                        <div class="bg-success-subtle rounded p-2">
                            <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-4 text-success"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">In Lavorazione</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['in_lavorazione']) }}</h4>
                            <small class="text-muted">Da completare</small>
                        </div>
                        <div class="bg-warning-subtle rounded p-2">
                            <iconify-icon icon="solar:settings-bold-duotone" class="fs-4 text-warning"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">Valore Totale</h6>
                            <h4 class="mb-0 fw-bold">€ {{ number_format($stats['valore_totale'], 2, ',', '.') }}</h4>
                            <small class="text-muted">{{ $stats['venduti'] }} venduti</small>
                        </div>
                        <div class="bg-info-subtle rounded p-2">
                            <iconify-icon icon="solar:euro-bold-duotone" class="fs-4 text-info"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri e Ricerca -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label small fw-semibold">Ricerca</label>
                    <input type="text" 
                           class="form-control" 
                           placeholder="Codice, descrizione..." 
                           wire:model.live.debounce.300ms="search">
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Tipologia</label>
                    <select class="form-select" wire:model.live="tipologiaFilter">
                        <option value="">Tutte</option>
                        <option value="prodotto_finito">Prodotto Finito</option>
                        <option value="semilavorato">Semilavorato</option>
                        <option value="componente">Componente</option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Stato</label>
                    <select class="form-select" wire:model.live="statoFilter">
                        <option value="">Tutti</option>
                        <option value="in_lavorazione">In Lavorazione</option>
                        <option value="completato">Completato</option>
                        <option value="venduto">Venduto</option>
                        <option value="scartato">Scartato</option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Categoria</label>
                    <select class="form-select" wire:model.live="categoriaFilter">
                        <option value="">Tutte</option>
                        @foreach($categorie as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Per Pagina</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Filtri avanzati -->
            <div class="mt-3">
                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filtriAvanzati">
                    <iconify-icon icon="solar:settings-bold-duotone" class="me-1"></iconify-icon>
                    Filtri Avanzati
                </button>
                <button class="btn btn-sm btn-secondary" wire:click="resetFilters">
                    <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                    Reset
                </button>
            </div>

            <div class="collapse mt-3" id="filtriAvanzati">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="form-label small fw-semibold">Data Completamento Da</label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="dataFrom">
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label small fw-semibold">Data Completamento A</label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="dataTo">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabella Prodotti Finiti -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="text-primary me-2"></iconify-icon>
                Elenco Prodotti Finiti
            </h5>
            <a href="{{ route('prodotti-finiti.nuovo') }}" class="btn btn-primary btn-sm">
                <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                Nuovo Prodotto
            </a>
        </div>

        <div class="card-body p-0">
            @if($prodotti->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-centered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="cursor: pointer;" wire:click="sortBy('codice')" width="120">
                                    <div class="d-flex align-items-center gap-1">
                                        Codice
                                        @if($sortField === 'codice')
                                            <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}-bold"></iconify-icon>
                                        @endif
                                    </div>
                                </th>
                                <th>Descrizione</th>
                                <th>Tipologia</th>
                                <th>Componenti</th>
                                <th>Costo Tot.</th>
                                <th>Stato</th>
                                <th style="cursor: pointer;" wire:click="sortBy('data_completamento')">
                                    <div class="d-flex align-items-center gap-1">
                                        Completato
                                        @if($sortField === 'data_completamento')
                                            <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}-bold"></iconify-icon>
                                        @endif
                                    </div>
                                </th>
                                <th>Giacenza</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($prodotti as $prodotto)
                                <tr wire:key="pf-row-{{ $prodotto->id }}">
                                    <td>
                                        <strong class="text-primary">{{ $prodotto->codice }}</strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $prodotto->descrizione }}</strong>
                                        </div>
                                        @if($prodotto->oro_totale || $prodotto->brillanti_totali || $prodotto->pietre_totali)
                                            <small class="text-muted">
                                                @if($prodotto->oro_totale)Oro: {{ $prodotto->oro_totale }} @endif
                                                @if($prodotto->brillanti_totali)Brill: {{ $prodotto->brillanti_totali }} @endif
                                                @if($prodotto->pietre_totali)Pietre: {{ $prodotto->pietre_totali }} @endif
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($prodotto->tipologia === 'prodotto_finito')
                                            <span class="badge bg-primary">Finito</span>
                                        @elseif($prodotto->tipologia === 'semilavorato')
                                            <span class="badge bg-info">Semilavorato</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($prodotto->tipologia) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $prodotto->componentiArticoli->count() }} pz
                                        </span>
                                    </td>
                                    <td>
                                        <strong>€ {{ number_format($prodotto->costo_totale ?? 0, 2, ',', '.') }}</strong>
                                    </td>
                                    <td>
                                        @switch($prodotto->stato)
                                            @case('in_lavorazione')
                                                <span class="badge bg-warning">In Lavorazione</span>
                                                @break
                                            @case('completato')
                                                <span class="badge bg-success">Completato</span>
                                                @break
                                            @case('venduto')
                                                <span class="badge bg-info">Venduto</span>
                                                @break
                                            @case('scartato')
                                                <span class="badge bg-danger">Scartato</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $prodotto->data_completamento ? $prodotto->data_completamento->format('d/m/Y') : '-' }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($prodotto->articoloRisultante && $prodotto->articoloRisultante->giacenza)
                                            @if($prodotto->articoloRisultante->giacenza->quantita_residua > 0)
                                                <span class="badge bg-success">
                                                    {{ $prodotto->articoloRisultante->giacenza->quantita_residua }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger">Venduto</span>
                                            @endif
                                        @else
                                            <span class="badge bg-light text-muted">-</span>
                                        @endif
                                    </td>
                                    @php
                                        $modificabile = in_array($prodotto->stato, ['in_lavorazione', 'completato']) 
                                            && !$prodotto->in_conto_deposito;
                                        if ($prodotto->in_conto_deposito) {
                                            $motivoModifica = 'In conto deposito';
                                        } elseif (in_array($prodotto->stato, ['venduto', 'scartato', 'annullato'])) {
                                            $motivoModifica = 'Stato: ' . $prodotto->stato;
                                        } else {
                                            $motivoModifica = 'Non modificabile';
                                        }

                                    @endphp
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('prodotti-finiti.dettaglio', $prodotto->id) }}" 
                                               class="btn btn-light" 
                                               title="Dettaglio">
                                                <iconify-icon icon="solar:eye-bold-duotone" class="text-primary"></iconify-icon>
                                            </a>
                                            @if($modificabile)
                                                <a href="{{ route('prodotti-finiti.modifica', $prodotto->id) }}" 
                                                   class="btn btn-light" 
                                                   title="Modifica">
                                                    <iconify-icon icon="solar:pen-bold-duotone" class="text-warning"></iconify-icon>
                                                </a>
                                            @else
                                                <button class="btn btn-light" type="button" disabled title="{{ $motivoModifica }}">
                                                    <iconify-icon icon="solar:pen-bold-duotone" class="text-muted"></iconify-icon>
                                                </button>
                                                @if($prodotto->in_conto_deposito)
                                                    <a class="btn btn-warning"
                                                       href="{{ route('prodotti-finiti.modifica', $prodotto->id) }}?force=1"
                                                       onclick="return confirm('Sbloccare la modifica per questo PF in conto deposito?')"
                                                       title="Sblocca modifica solo per questo PF">
                                                        Sblocca
                                                    </a>
                                                @endif
                                            @endif
                                            @if($modificabile)
                                                <form method="POST"
                                                      action="{{ route('prodotti-finiti.smonta', $prodotto->id) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-light"
                                                            type="submit"
                                                            onclick="return confirm('Confermi lo smontaggio del PF?')"
                                                            title="Smonta / Disfa">
                                                        <iconify-icon icon="solar:undo-left-bold" class="text-danger"></iconify-icon>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="fs-1 text-muted mb-3 d-block"></iconify-icon>
                    <h5 class="text-muted">Nessun prodotto finito trovato</h5>
                    <p class="text-muted mb-3">Inizia creando il tuo primo prodotto finito</p>
                    <a href="{{ route('prodotti-finiti.nuovo') }}" class="btn btn-primary">
                        <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                        Nuovo Prodotto Finito
                    </a>
                </div>
            @endif
        </div>

        <!-- Paginazione -->
        @if($prodotti->hasPages())
            <div class="card-footer border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-0 small">
                            <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                            Mostrando <strong>{{ $prodotti->firstItem() }}</strong> - <strong>{{ $prodotti->lastItem() }}</strong> 
                            di <strong>{{ number_format($prodotti->total()) }}</strong> prodotti
                        </p>
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $prodotti->links() }}
                    </nav>
                </div>
            </div>
        @endif
    </div>
</div>

@if($showSmontaModal && $prodottoDaSmontare)
    <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:undo-left-bold" class="text-danger me-2"></iconify-icon>
                        Smonta prodotto finito
                    </h5>
                    <button type="button" class="btn-close" wire:click="chiudiSmontaModal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                        Questa operazione annulla l’assemblaggio e ripristina le giacenze dei componenti.
                    </div>
                    <div class="mb-2">
                        <strong>Codice:</strong> {{ $prodottoDaSmontare->codice }}
                    </div>
                    <div class="mb-2">
                        <strong>Descrizione:</strong> {{ $prodottoDaSmontare->descrizione }}
                    </div>
                    <div class="mb-2">
                        <strong>Componenti:</strong> {{ $prodottoDaSmontare->componentiArticoli->count() }}
                    </div>
                    <div class="text-muted small">
                        Il prodotto finito verrà marcato come annullato e non sarà più visibile in elenco.
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" wire:click="chiudiSmontaModal">Annulla</button>
                    <button class="btn btn-danger" wire:click="confermaSmonta">
                        <iconify-icon icon="solar:undo-left-bold" class="me-1"></iconify-icon>
                        Smonta
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif

</div>