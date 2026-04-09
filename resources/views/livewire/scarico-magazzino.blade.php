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

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Scarico Magazzino</h4>
                    <p class="text-muted mb-0">Gestisci lo scarico degli articoli dal magazzino</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary" wire:click="resetWizard">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Selezione Articoli -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Selezione Articoli</h5>
                            <div class="d-flex gap-2">
                                <span class="badge bg-primary">{{ $stats['selezionati'] }} selezionati</span>
                                <span class="badge bg-secondary">{{ $stats['totali_disponibili'] }} disponibili</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtri -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Ricerca</label>
                                <input type="text" class="form-control" wire:model.live="search" 
                                       placeholder="Cerca per codice, descrizione...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Magazzino</label>
                                <select class="form-select" wire:model.live="categoriaFilter">
                                    <option value="">Tutti</option>
                                    @foreach($categorie as $categoria)
                                        <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sede</label>
                                <select class="form-select" wire:model.live="sedeFilter">
                                    <option value="">Tutte</option>
                                    @foreach($sedi as $sede)
                                        <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Per Pagina</label>
                                <select class="form-select" wire:model.live="perPage">
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>

                        <!-- Selezione Multipla -->
                        <div class="alert alert-info mb-3">
                            <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                            <strong>Come funziona:</strong> Clicca su una riga per selezionarla, poi imposta la quantità da scaricare. 
                            Se un articolo ha giacenza = 1, verrà scaricato completamente.
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="selezionaTutti" id="selezionaTutti">
                                <label class="form-check-label" for="selezionaTutti">
                                    Seleziona Tutti
                                </label>
                            </div>
                            <div>
                                <button class="btn btn-danger" wire:click="confermaScaricoMultiplo" 
                                        @if(empty($articoliSelezionati)) disabled @endif>
                                    <iconify-icon icon="solar:box-remove-bold" class="me-1"></iconify-icon>
                                    Scarica Selezionati ({{ count($articoliSelezionati) }})
                                </button>
                            </div>
                        </div>

                        @if($articoliSelezionatiDettaglio->isNotEmpty())
                            <div class="card border-primary bg-primary-subtle mb-4">
                                <div class="card-header bg-transparent border-primary d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h6 class="mb-1 text-primary">Articoli già selezionati</h6>
                                        <small class="text-muted">Restano visibili qui mentre continui la ricerca nella tabella sotto.</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary">{{ $articoliSelezionatiDettaglio->count() }} selezionati</span>
                                        <button class="btn btn-sm btn-outline-danger" wire:click="svuotaSelezione">
                                            <iconify-icon icon="solar:trash-bin-trash-bold" class="me-1"></iconify-icon>
                                            Svuota selezione
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Codice</th>
                                                    <th>Descrizione</th>
                                                    <th>Magazzino</th>
                                                    <th>Sede</th>
                                                    <th class="text-center">Giacenza</th>
                                                    <th class="text-center">Q.tà da scaricare</th>
                                                    <th class="text-end">Prezzo</th>
                                                    <th class="text-center">Azioni</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($articoliSelezionatiDettaglio as $articoloSelezionato)
                                                    <tr>
                                                        <td class="fw-semibold">{{ $articoloSelezionato->codice }}</td>
                                                        <td>
                                                            <div class="fw-semibold">{{ Str::limit($articoloSelezionato->descrizione, 45) }}</div>
                                                            @if($articoloSelezionato->materiale)
                                                                <small class="text-muted">{{ $articoloSelezionato->materiale }}</small>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary">{{ $articoloSelezionato->categoriaMerceologica->nome ?? 'N/A' }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info">{{ $articoloSelezionato->sede->nome ?? 'N/A' }}</span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="fw-semibold text-success">{{ $articoloSelezionato->giacenza->quantita_residua ?? 0 }}</span>
                                                        </td>
                                                        <td class="text-center">
                                                            @if(($articoloSelezionato->giacenza->quantita_residua ?? 0) > 1)
                                                                <input type="number"
                                                                       class="form-control form-control-sm mx-auto"
                                                                       wire:model.live="quantitaArticoli.{{ $articoloSelezionato->id }}"
                                                                       min="1"
                                                                       max="{{ $articoloSelezionato->giacenza->quantita_residua ?? 1 }}"
                                                                       style="width: 90px;">
                                                            @else
                                                                <span class="badge bg-success">Completo</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end fw-semibold">€{{ number_format($articoloSelezionato->prezzo_acquisto ?? 0, 2) }}</td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-outline-danger" wire:click="rimuoviArticoloSelezionato({{ $articoloSelezionato->id }})">
                                                                <iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Tabella Articoli -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" 
                                                   wire:click="toggleSelezionaTutti"
                                                   @if($selezionaTutti) checked @endif>
                                        </th>
                                        <th>Codice</th>
                                        <th>Descrizione</th>
                                        <th>Magazzino</th>
                                        <th>Sede</th>
                                        <th>Giacenza Disponibile</th>
                                        <th>Quantità da Scaricare</th>
                                        <th>Prezzo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($articoli as $articolo)
                                        <tr class="{{ in_array($articolo->id, $articoliSelezionati) ? 'table-active border-primary' : '' }}" 
                                            style="cursor: pointer; {{ in_array($articolo->id, $articoliSelezionati) ? 'background-color: rgba(13, 110, 253, 0.1);' : '' }}" 
                                            onclick="toggleRow({{ $articolo->id }})">
                                            <td>
                                                <input type="checkbox" 
                                                       wire:click="toggleArticolo({{ $articolo->id }})"
                                                       @if(in_array($articolo->id, $articoliSelezionati)) checked @endif>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">{{ $articolo->codice }}</span>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold">{{ Str::limit($articolo->descrizione, 30) }}</div>
                                                    @if($articolo->materiale)
                                                        <small class="text-muted">{{ $articolo->materiale }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $articolo->categoriaMerceologica->nome ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $articolo->sede->nome ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-success">{{ $articolo->giacenza->quantita_residua ?? 0 }}</span>
                                                <small class="text-muted d-block">pezzi</small>
                                            </td>
                                            <td>
                                                @if(in_array($articolo->id, $articoliSelezionati))
                                                    @if(($articolo->giacenza->quantita_residua ?? 0) > 1)
                                                        <div class="d-flex align-items-center gap-1">
                                                            <input type="number" class="form-control form-control-sm" 
                                                                   wire:model.live="quantitaArticoli.{{ $articolo->id }}"
                                                                   wire:click.stop
                                                                   min="1" max="{{ $articolo->giacenza->quantita_residua ?? 1 }}"
                                                                   style="width: 60px;">
                                                            <small class="text-muted">pz</small>
                                                        </div>
                                                    @else
                                                        <span class="badge bg-success">Scarico completo</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted small">Seleziona per impostare</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="fw-semibold">€{{ number_format($articolo->prezzo_acquisto ?? 0, 2) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <iconify-icon icon="solar:magnifer-zoom-out-bold" class="fs-48 text-muted mb-3"></iconify-icon>
                                                <h5 class="text-muted">Nessun articolo trovato</h5>
                                                <p class="text-muted">Prova a modificare i filtri di ricerca</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginazione -->
                        @if($articoli->hasPages())
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <p class="text-muted mb-0 small">
                                        Mostrando {{ $articoli->firstItem() }} - {{ $articoli->lastItem() }} 
                                        di {{ number_format($articoli->total()) }} articoli
                                    </p>
                                </div>
                                <nav>
                                    {{ $articoli->links() }}
                                </nav>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
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

    <!-- Modal Conferma Scarico Multiplo -->
    @if($showModalConferma)
        <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:box-remove-bold" class="text-danger me-2"></iconify-icon>
                            Conferma Scarico Multiplo
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModalConferma"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                            <strong>Attenzione:</strong> Questa operazione non può essere annullata.
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Articolo</th>
                                        <th>Giacenza</th>
                                        <th>Quantità da Scaricare</th>
                                        <th>Residuo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($articoliSelezionatiDettaglio as $articolo)
                                        @if($articolo)
                                            <tr>
                                                <td>
                                                    <div>
                                                        <div class="fw-semibold">{{ $articolo->codice }}</div>
                                                        <small class="text-muted">{{ Str::limit($articolo->descrizione, 40) }}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">{{ $articolo->giacenza->quantita_residua ?? 0 }}</span>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm" 
                                                           wire:model.live="quantitaArticoli.{{ $articolo->id }}"
                                                           min="1" max="{{ $articolo->giacenza->quantita_residua ?? 1 }}"
                                                           style="width: 80px;">
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        {{ ($articolo->giacenza->quantita_residua ?? 0) - ($quantitaArticoli[$articolo->id] ?? 1) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalConferma">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="eseguiScaricoMultiplo">
                            <iconify-icon icon="solar:box-remove-bold" class="me-1"></iconify-icon>
                            Conferma Scarico
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
        
</div>

<script>
let isProcessing = false;

function toggleRow(articoloId) {
    if (isProcessing) return;
    
    // Trigger Livewire immediatamente senza delay
    isProcessing = true;
    @this.call('toggleArticolo', articoloId).then(() => {
        isProcessing = false;
    });
}

// Gestione checkbox per sincronizzazione
document.addEventListener('change', function(e) {
    if (e.target.type === 'checkbox' && e.target.closest('tbody')) {
        const row = e.target.closest('tr');
        if (e.target.checked) {
            row.classList.add('table-active');
        } else {
            row.classList.remove('table-active');
        }
    }
});

// Le selezioni vengono mantenute automaticamente da Livewire
</script>
