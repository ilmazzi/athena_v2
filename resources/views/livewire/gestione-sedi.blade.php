<div>
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">Gestione Sedi</h4>
                    <p class="text-muted mb-0">Gestisci le sedi del sistema</p>
                </div>
                <button class="btn btn-primary" wire:click="apriModalCreazione">
                    <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                    Nuova Sede
                </button>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:close-circle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filtri e Ricerca --}}
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">
                    <iconify-icon icon="solar:magnifer-bold"></iconify-icon>
                </span>
                <input type="text" 
                       class="form-control" 
                       placeholder="Cerca per codice, nome, città..." 
                       wire:model.debounce.300ms="search">
            </div>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model="filtroAttivo">
                <option value="">Tutti gli stati</option>
                <option value="si">Attive</option>
                <option value="no">Non attive</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model="filtroSocieta">
                <option value="">Tutte le società</option>
                @foreach($societaList as $soc)
                    <option value="{{ $soc->id }}">{{ $soc->codice }} - {{ $soc->ragione_sociale }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 text-end">
            <span class="text-muted small">Totale: {{ $sedi->total() }} sedi</span>
        </div>
    </div>

    {{-- Tabella Sedi --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="80">Codice</th>
                            <th>Nome</th>
                            <th width="150">Città</th>
                            <th width="150">Società</th>
                            <th width="100">Tipo</th>
                            <th width="100">Stato</th>
                            <th width="150" class="text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sedi as $sede)
                            <tr>
                                <td>
                                    <span class="badge bg-primary">{{ $sede->codice }}</span>
                                </td>
                                <td>
                                    <strong>{{ $sede->nome }}</strong>
                                    @if($sede->indirizzo)
                                        <br><small class="text-muted">{{ $sede->indirizzo }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $sede->citta ?? '-' }}
                                    @if($sede->provincia)
                                        <small class="text-muted">({{ $sede->provincia }})</small>
                                    @endif
                                </td>
                                <td>
                                    @if($sede->societa)
                                        <span class="badge bg-light-primary text-primary">
                                            {{ $sede->societa->codice }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light-secondary text-secondary">
                                        {{ ucfirst($sede->tipo) }}
                                    </span>
                                </td>
                                <td>
                                    @if($sede->attivo)
                                        <span class="badge bg-success">Attiva</span>
                                    @else
                                        <span class="badge bg-secondary">Non attiva</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light" 
                                                wire:click="apriModalModifica({{ $sede->id }})"
                                                title="Modifica">
                                            <iconify-icon icon="solar:pen-bold"></iconify-icon>
                                        </button>
                                        <button class="btn btn-light text-danger" 
                                                wire:click="apriModalEliminazione({{ $sede->id }})"
                                                title="Elimina">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <iconify-icon icon="solar:document-text-bold" class="fs-1 text-muted"></iconify-icon>
                                    <p class="text-muted mt-2 mb-0">Nessuna sede trovata</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($sedi->hasPages())
            <div class="card-footer">
                {{ $sedi->links() }}
            </div>
        @endif
    </div>

    {{-- Modale Creazione/Modifica --}}
    @if($showModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:map-point-bold-duotone" class="me-2"></iconify-icon>
                            {{ $modalMode === 'create' ? 'Nuova Sede' : 'Modifica Sede' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            {{-- Codice --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Codice *</label>
                                <input type="text" 
                                       class="form-control @error('codice') is-invalid @enderror" 
                                       wire:model="codice"
                                       placeholder="CAV, JOL, ROM...">
                                @error('codice') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Nome --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" 
                                       class="form-control @error('nome') is-invalid @enderror" 
                                       wire:model="nome">
                                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Indirizzo --}}
                            <div class="col-12 mb-3">
                                <label class="form-label">Indirizzo</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="indirizzo">
                            </div>

                            {{-- Città, Provincia, CAP --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Città</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="citta">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Provincia</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="provincia"
                                       maxlength="2"
                                       placeholder="RM, LC...">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">CAP</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="cap">
                            </div>

                            {{-- Contatti --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefono</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="telefono">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       wire:model="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Dati legali --}}
                            <div class="col-12 mb-2">
                                <hr>
                                <h6 class="fw-bold text-primary mb-2">Dati legali sede</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sede legale</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="sede_legale"
                                       placeholder="Ragione sociale / sede legale">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Indirizzo sede legale</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="sede_legale_indirizzo">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Città sede legale</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="sede_legale_citta">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Provincia sede legale</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="sede_legale_provincia"
                                       maxlength="2">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">CAP sede legale</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="sede_legale_cap">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Partita IVA</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="partita_iva">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Codice Fiscale</label>
                                <input type="text" 
                                       class="form-control" 
                                       wire:model="codice_fiscale">
                            </div>

                            {{-- Tipo --}}
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo *</label>
                                <select class="form-select @error('tipo') is-invalid @enderror" wire:model="tipo">
                                    <option value="negozio">Negozio</option>
                                    <option value="deposito">Deposito</option>
                                    <option value="ufficio">Ufficio</option>
                                </select>
                                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Società --}}
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Società *</label>
                                <select class="form-select @error('societa_id') is-invalid @enderror" wire:model="societa_id">
                                    <option value="">Seleziona società...</option>
                                    @foreach($societaList as $soc)
                                        <option value="{{ $soc->id }}">{{ $soc->codice }} - {{ $soc->ragione_sociale }}</option>
                                    @endforeach
                                </select>
                                @error('societa_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Note --}}
                            <div class="col-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea class="form-control" 
                                          rows="3" 
                                          wire:model="note"></textarea>
                            </div>

                            {{-- Attivo --}}
                            <div class="col-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="attivo"
                                           wire:model="attivo">
                                    <label class="form-check-label" for="attivo">
                                        Sede attiva
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="salva">
                            <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                            {{ $modalMode === 'create' ? 'Crea' : 'Salva' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modale Eliminazione --}}
    @if($showDeleteModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:danger-triangle-bold-duotone" class="me-2 text-danger"></iconify-icon>
                            Conferma Eliminazione
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModalEliminazione"></button>
                    </div>
                    <div class="modal-body">
                        <p>Sei sicuro di voler eliminare questa sede?</p>
                        <p class="text-muted small">Verifica che non ci siano articoli o magazzini associati prima di procedere.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiModalEliminazione">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="elimina">
                            <iconify-icon icon="solar:trash-bin-minimalistic-bold" class="me-1"></iconify-icon>
                            Elimina
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        @endif
    </div>
</div>
