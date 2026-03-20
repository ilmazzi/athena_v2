<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <iconify-icon icon="solar:printer-bold" class="me-2"></iconify-icon>
            Gestione Stampanti
        </h5>
        <button wire:click="createStampante" class="btn btn-primary">
            <iconify-icon icon="solar:add-circle-bold"></iconify-icon>
            Nuova Stampante
        </button>
    </div>
    
    <div class="card-body">
        <!-- Filtri -->
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" 
                       wire:model.live="search" 
                       class="form-control" 
                       placeholder="Cerca stampanti...">
            </div>
            <div class="col-md-3">
                <select wire:model.live="perPage" class="form-select">
                    <option value="5">5 per pagina</option>
                    <option value="10">10 per pagina</option>
                    <option value="25">25 per pagina</option>
                </select>
            </div>
        </div>

        <!-- Tabella -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th wire:click="sortBy('nome')" style="cursor: pointer;">
                            Nome 
                            @if($sortField === 'nome')
                                <iconify-icon icon="solar:alt-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-bold"></iconify-icon>
                            @endif
                        </th>
                        <th>IP Address</th>
                        <th>Modello</th>
                        <th>Categorie</th>
                        <th>Sedi</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stampanti as $stampante)
                        <tr>
                            <td>
                                <strong>{{ $stampante->nome }}</strong>
                                <br>
                                <small class="text-muted">Porta: {{ $stampante->port }}</small>
                            </td>
                            <td>
                                <code>{{ $stampante->ip_address }}</code>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $stampante->modello }}</span>
                            </td>
                            <td>
                                @foreach($stampante->categorie_permesse ?? [] as $catId)
                                    @php
                                        $categoria = $categorieDisponibili->find($catId);
                                    @endphp
                                    @if($categoria)
                                        <span class="badge bg-secondary me-1">{{ $categoria->nome }}</span>
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                @foreach($stampante->sedi_permesse ?? [] as $sedeId)
                                    @php
                                        $sede = $sediDisponibili->find($sedeId);
                                    @endphp
                                    @if($sede)
                                        <span class="badge bg-primary me-1">{{ $sede->nome }}</span>
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                @if($stampante->attiva)
                                    <span class="badge bg-success">Attiva</span>
                                @else
                                    <span class="badge bg-danger">Disattiva</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button wire:click="testConnessione({{ $stampante->id }})" 
                                            class="btn btn-sm btn-info" 
                                            title="Test Connessione">
                                        <iconify-icon icon="solar:wifi-router-bold"></iconify-icon>
                                    </button>
                                    <button wire:click="editStampante({{ $stampante->id }})" 
                                            class="btn btn-sm btn-primary" 
                                            title="Modifica">
                                        <iconify-icon icon="solar:pen-bold"></iconify-icon>
                                    </button>
                                    <button wire:click="toggleAttiva({{ $stampante->id }})" 
                                            class="btn btn-sm btn-{{ $stampante->attiva ? 'warning' : 'success' }}" 
                                            title="{{ $stampante->attiva ? 'Disattiva' : 'Attiva' }}">
                                        <iconify-icon icon="solar:{{ $stampante->attiva ? 'eye-closed' : 'eye' }}-bold"></iconify-icon>
                                    </button>
                                    <button wire:click="deleteStampante({{ $stampante->id }})" 
                                            class="btn btn-sm btn-danger" 
                                            title="Elimina"
                                            onclick="return confirm('Sei sicuro di voler eliminare questa stampante?')">
                                        <iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                <iconify-icon icon="solar:printer-bold" class="fs-1"></iconify-icon>
                                <br>
                                Nessuna stampante trovata
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginazione -->
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Mostrando {{ $stampanti->firstItem() }} - {{ $stampanti->lastItem() }} 
                di {{ $stampanti->total() }} stampanti
            </div>
            <div>
                {{ $stampanti->links() }}
            </div>
        </div>
    </div>
    
    <!-- Modal Form -->
    @if($showModal)
<div class="modal show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <iconify-icon icon="solar:printer-bold"></iconify-icon>
                    {{ $editingStampante ? 'Modifica' : 'Nuova' }} Stampante
                </h5>
                <button wire:click="closeModal" class="btn-close"></button>
            </div>
            <div class="modal-body">
                <form wire:submit.prevent="saveStampante">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nome Stampante</label>
                                <input type="text" wire:model="nome" class="form-control" required>
                                @error('nome') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Modello</label>
                                <select wire:model="modello" class="form-select" required>
                                    <option value="ZT230">ZT230 (Piccola)</option>
                                    <option value="ZT421">ZT421 (Lecco)</option>
                                    <option value="ZT420">ZT420 (Media)</option>
                                    <option value="ZT620">ZT620 (Grande)</option>
                                </select>
                                @error('modello') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" wire:model="ip_address" class="form-control" required>
                                @error('ip_address') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Porta</label>
                                <input type="number" wire:model="port" class="form-control" min="1" max="65535" required>
                                @error('port') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Categorie Permesse</label>
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($categorieDisponibili as $categoria)
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   wire:model="categorie_permesse" 
                                                   value="{{ $categoria->id }}" 
                                                   class="form-check-input" 
                                                   id="cat_{{ $categoria->id }}">
                                            <label class="form-check-label" for="cat_{{ $categoria->id }}">
                                                {{ $categoria->nome }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('categorie_permesse') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sedi Permesse</label>
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($sediDisponibili as $sede)
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   wire:model="sedi_permesse" 
                                                   value="{{ $sede->id }}" 
                                                   class="form-check-input" 
                                                   id="sede_{{ $sede->id }}">
                                            <label class="form-check-label" for="sede_{{ $sede->id }}">
                                                {{ $sede->nome }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('sedi_permesse') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" wire:model="attiva" class="form-check-input" id="attiva">
                            <label class="form-check-label" for="attiva">
                                Stampante Attiva
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button wire:click="closeModal" class="btn btn-secondary">Annulla</button>
                <button wire:click="saveStampante" class="btn btn-primary">
                    <iconify-icon icon="solar:check-circle-bold"></iconify-icon>
                    {{ $editingStampante ? 'Aggiorna' : 'Crea' }}
                </button>
            </div>
        </div>
    </div>
</div>
    @endif
</div>
