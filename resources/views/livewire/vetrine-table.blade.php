<div>
    <!-- Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">
                    <iconify-icon icon="solar:shop-bold-duotone" class="me-2"></iconify-icon>
                    Gestione Vetrine
                </h4>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <iconify-icon icon="solar:list-bold" class="me-2"></iconify-icon>
                Elenco Vetrine
            </h5>
            <button wire:click="createVetrina" class="btn btn-primary">
                <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                Nuova Vetrina
            </button>
        </div>

        <div class="card-body">
            <!-- Filtri -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Cerca</label>
                    <input type="text" 
                           class="form-control" 
                           placeholder="Codice, nome, ubicazione..." 
                           wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Tipologia</label>
                    <select class="form-select" wire:model.live="tipologiaFilter">
                        <option value="">Tutte le Tipologie</option>
                        <option value="gioielleria">Gioielleria</option>
                        <option value="orologeria">Orologeria</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Stato</label>
                    <select class="form-select" wire:model.live="attivaFilter">
                        <option value="">Tutte</option>
                        <option value="1">Attive</option>
                        <option value="0">Non Attive</option>
                    </select>
                </div>
            </div>

            <!-- Tabella Vetrine -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>Nome</th>
                            <th>Tipologia</th>
                            <th>Sede</th>
                            <th>Ubicazione</th>
                            <th>Articoli</th>
                            <th>Stato</th>
                            <th class="text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vetrine as $vetrina)
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">{{ $vetrina->codice }}</span>
                                </td>
                                <td>{{ $vetrina->nome }}</td>
                                <td>
                                    <span class="badge bg-light-info text-info">
                                        {{ $vetrina->getTipologiaLabel() }}
                                    </span>
                                </td>
                                <td>{{ $vetrina->sede?->nome ?? '-' }}</td>
                                <td>{{ $vetrina->ubicazione ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-light-primary text-primary">
                                        {{ $vetrina->articoli_count }} articoli
                                    </span>
                                </td>
                                <td>
                                    @if($vetrina->attiva)
                                        <span class="badge bg-light-success text-success">Attiva</span>
                                    @else
                                        <span class="badge bg-light-secondary text-secondary">Non Attiva</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('vetrine.show', $vetrina->id) }}" 
                                           class="btn btn-light btn-sm" 
                                           title="Gestisci Articoli">
                                            <i class="bx bx-store text-primary"></i>
                                        </a>
                                        <button class="btn btn-light btn-sm" 
                                                wire:click="editVetrina({{ $vetrina->id }})"
                                                title="Modifica">
                                            <i class="bx bx-edit text-warning"></i>
                                        </button>
                                        @if($vetrina->articoli_count == 0)
                                            <button class="btn btn-light btn-sm" 
                                                    wire:click="deleteVetrina({{ $vetrina->id }})"
                                                    title="Elimina"
                                                    onclick="return confirm('Sei sicuro di voler eliminare questa vetrina?')">
                                                <i class="bx bx-trash text-danger"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <iconify-icon icon="solar:shop-bold" class="fs-48 text-muted mb-3"></iconify-icon>
                                    <p class="text-muted mb-0">Nessuna vetrina trovata</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Mostrando {{ $vetrine->firstItem() ?? 0 }} - {{ $vetrine->lastItem() ?? 0 }} 
                    di {{ $vetrine->total() }} vetrine
                </div>
                {{ $vetrine->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Creazione/Modifica Vetrina -->
    @if($showModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title">
                            <i class="bx bx-store me-2"></i>
                            {{ $editingVetrina ? 'Modifica Vetrina' : 'Nuova Vetrina' }}
                        </h5>
                        <button type="button" wire:click="closeModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="saveVetrina">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Codice *</label>
                                        <input type="text" 
                                               class="form-control @error('codice') is-invalid @enderror" 
                                               wire:model="codice"
                                               placeholder="Es: VET001">
                                        @error('codice')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Nome *</label>
                                        <input type="text" 
                                               class="form-control @error('nome') is-invalid @enderror" 
                                               wire:model="nome"
                                               placeholder="Nome vetrina">
                                        @error('nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Tipologia *</label>
                                        <select class="form-select @error('tipologia') is-invalid @enderror" 
                                                wire:model="tipologia">
                                            <option value="gioielleria">Gioielleria</option>
                                            <option value="orologeria">Orologeria</option>
                                        </select>
                                        @error('tipologia')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Sede</label>
                                        <select class="form-select @error('sede_id') is-invalid @enderror"
                                                wire:model="sede_id">
                                            <option value="">Seleziona...</option>
                                            @foreach(\App\Models\Sede::orderBy('nome')->get() as $sede)
                                                <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                            @endforeach
                                        </select>
                                        @error('sede_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Ubicazione</label>
                                        <input type="text" 
                                               class="form-control @error('ubicazione') is-invalid @enderror" 
                                               wire:model="ubicazione"
                                               placeholder="Es: Piano terra, Ingresso">
                                        @error('ubicazione')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           wire:model="attiva" 
                                           id="attiva">
                                    <label class="form-check-label fw-semibold" for="attiva">
                                        Vetrina Attiva
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Note</label>
                                <textarea class="form-control @error('note') is-invalid @enderror" 
                                          wire:model="note" 
                                          rows="3"
                                          placeholder="Note aggiuntive..."></textarea>
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="saveVetrina">
                            <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                            {{ $editingVetrina ? 'Aggiorna' : 'Crea' }} Vetrina
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
