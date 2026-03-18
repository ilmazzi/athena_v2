<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">Gestione Fornitori</h4>
                    <p class="text-muted mb-0">CRUD completo fornitori</p>
                </div>
                <button class="btn btn-primary" wire:click="apriModalCreazione">
                    <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                    Nuovo Fornitore
                </button>
            </div>
        </div>
    </div>

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

    <div class="row mb-3">
        <div class="col-md-8">
            <div class="input-group">
                <span class="input-group-text">
                    <iconify-icon icon="solar:magnifer-bold"></iconify-icon>
                </span>
                <input type="text"
                       class="form-control"
                       placeholder="Cerca per codice, ragione sociale, P.IVA, CF, email..."
                       wire:model.debounce.300ms="search">
            </div>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model.live="filtroAttivo">
                <option value="">Tutti gli stati</option>
                <option value="si">Attivi</option>
                <option value="no">Non attivi</option>
            </select>
        </div>
        <div class="col-md-1 text-end">
            <span class="text-muted small">{{ $fornitori->total() }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Codice</th>
                            <th>Fornitore</th>
                            <th width="150">P.IVA / CF</th>
                            <th width="220">Contatti</th>
                            <th width="100">Stato</th>
                            <th width="150" class="text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fornitori as $fornitore)
                            <tr>
                                <td><span class="badge bg-primary">{{ $fornitore->codice }}</span></td>
                                <td>
                                    <strong>{{ $fornitore->ragione_sociale }}</strong>
                                    @if($fornitore->indirizzo || $fornitore->citta)
                                        <br>
                                        <small class="text-muted">
                                            {{ $fornitore->indirizzo ? $fornitore->indirizzo . ' - ' : '' }}{{ $fornitore->citta ?? '' }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <small class="d-block">P.IVA: {{ $fornitore->partita_iva ?: '-' }}</small>
                                    <small class="d-block">CF: {{ $fornitore->codice_fiscale ?: '-' }}</small>
                                </td>
                                <td>
                                    <small class="d-block">Tel: {{ $fornitore->telefono ?: '-' }}</small>
                                    <small class="d-block">Email: {{ $fornitore->email ?: '-' }}</small>
                                    <small class="d-block">PEC: {{ $fornitore->pec ?: '-' }}</small>
                                </td>
                                <td>
                                    @if($fornitore->attivo)
                                        <span class="badge bg-success">Attivo</span>
                                    @else
                                        <span class="badge bg-secondary">Non attivo</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light"
                                                wire:click="apriModalModifica({{ $fornitore->id }})"
                                                title="Modifica">
                                            <iconify-icon icon="solar:pen-bold"></iconify-icon>
                                        </button>
                                        <button class="btn btn-light text-danger"
                                                wire:click="apriModalEliminazione({{ $fornitore->id }})"
                                                title="Elimina">
                                            <iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <iconify-icon icon="solar:document-text-bold" class="fs-1 text-muted"></iconify-icon>
                                    <p class="text-muted mt-2 mb-0">Nessun fornitore trovato</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($fornitori->hasPages())
            <div class="card-footer">
                {{ $fornitori->links() }}
            </div>
        @endif
    </div>

    @if($showModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:shop-bold-duotone" class="me-2"></iconify-icon>
                            {{ $modalMode === 'create' ? 'Nuovo Fornitore' : 'Modifica Fornitore' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Codice *</label>
                                <input type="text" class="form-control @error('codice') is-invalid @enderror" wire:model="codice">
                                @error('codice') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Ragione sociale *</label>
                                <input type="text" class="form-control @error('ragione_sociale') is-invalid @enderror" wire:model="ragione_sociale">
                                @error('ragione_sociale') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Partita IVA</label>
                                <input type="text" class="form-control @error('partita_iva') is-invalid @enderror" wire:model="partita_iva">
                                @error('partita_iva') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Codice fiscale</label>
                                <input type="text" class="form-control @error('codice_fiscale') is-invalid @enderror" wire:model="codice_fiscale">
                                @error('codice_fiscale') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Indirizzo</label>
                                <input type="text" class="form-control @error('indirizzo') is-invalid @enderror" wire:model="indirizzo">
                                @error('indirizzo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Citta</label>
                                <input type="text" class="form-control @error('citta') is-invalid @enderror" wire:model="citta">
                                @error('citta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Prov.</label>
                                <input type="text" class="form-control @error('provincia') is-invalid @enderror" maxlength="2" wire:model="provincia">
                                @error('provincia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">CAP</label>
                                <input type="text" class="form-control @error('cap') is-invalid @enderror" wire:model="cap">
                                @error('cap') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nazione</label>
                                <input type="text" class="form-control @error('nazione') is-invalid @enderror" wire:model="nazione">
                                @error('nazione') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Telefono</label>
                                <input type="text" class="form-control @error('telefono') is-invalid @enderror" wire:model="telefono">
                                @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">PEC</label>
                                <input type="email" class="form-control @error('pec') is-invalid @enderror" wire:model="pec">
                                @error('pec') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea class="form-control @error('note') is-invalid @enderror" rows="3" wire:model="note"></textarea>
                                @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 mb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="fornitoreAttivo" wire:model="attivo">
                                    <label class="form-check-label" for="fornitoreAttivo">Fornitore attivo</label>
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
                        <p>Sei sicuro di voler eliminare questo fornitore?</p>
                        <p class="text-muted small">L'eliminazione e consentita solo se non ci sono articoli/documenti collegati.</p>
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
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>

