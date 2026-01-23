<div>
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            @if($forceEdit)
                <div class="alert alert-warning">
                    <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                    Modifica forzata abilitata per questo PF in conto deposito.
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Modifica Prodotto Finito</h1>
                    <p class="text-muted mb-0">Modifica le informazioni del prodotto finito</p>
                </div>
                <div>
                    <a href="{{ route('prodotti-finiti.index') }}" class="btn btn-secondary">
                        <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                        Torna all'elenco
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Informazioni Base -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <iconify-icon icon="solar:document-text-bold-duotone" class="text-primary me-2"></iconify-icon>
                        Informazioni Base
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Descrizione Prodotto *</label>
                            <input type="text" 
                                   class="form-control @error('descrizione') is-invalid @enderror" 
                                   wire:model="descrizione"
                                   placeholder="Es: Collana oro bianco con brillanti">
                            @error('descrizione') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipologia *</label>
                            <select class="form-select @error('tipologia') is-invalid @enderror" wire:model="tipologia">
                                <option value="prodotto_finito">Prodotto Finito</option>
                                <option value="semilavorato">Semilavorato</option>
                                <option value="componente">Componente</option>
                            </select>
                            @error('tipologia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Categoria Finale *</label>
                            <select class="form-select @error('categoriaId') is-invalid @enderror" wire:model="categoriaId">
                                @foreach($categorie as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                                @endforeach
                            </select>
                            @error('categoriaId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Sede Assemblaggio *</label>
                            <select class="form-select @error('sedeId') is-invalid @enderror" wire:model="sedeId" disabled>
                                @foreach($sedi as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">⚠️ La sede non può essere modificata dopo la creazione</small>
                            @error('sedeId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Costo Lavorazione (€)</label>
                            <input type="number" 
                                   step="0.01" 
                                   class="form-control" 
                                   wire:model.blur="costoLavorazione">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" wire:model="note" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Componenti -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <iconify-icon icon="solar:bag-4-bold-duotone" class="text-primary me-2"></iconify-icon>
                        Componenti Utilizzati
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Ricerca articoli -->
                    <div class="row g-2 mb-3">
                        <div class="col-lg-6">
                            <input type="text" 
                                   class="form-control" 
                                   wire:model.live.debounce.300ms="searchArticoli"
                                   placeholder="Cerca articolo...">
                        </div>
                        <div class="col-lg-3">
                            <select class="form-select" wire:model.live="categoriaComponentiFilter">
                                <option value="">Tutte categorie</option>
                                @foreach($categorie as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-check form-switch h-100 d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" wire:model.live="soloDisponibili" id="soloDisponibili">
                                <label class="form-check-label ms-2" for="soloDisponibili">Solo disponibili</label>
                            </div>
                        </div>
                    </div>

                    <!-- Lista articoli -->
                    <div style="max-height: 300px; overflow-y: auto;" class="mb-3">
                        @forelse($articoliDisponibili as $articolo)
                            @php
                                $giacenza = $articolo->giacenza->quantita_residua ?? 0;
                                $disponibile = $giacenza > 0;
                            @endphp
                            <div class="border rounded p-2 mb-2 {{ $disponibile ? 'hover-shadow' : 'bg-light' }}" 
                                 style="cursor: {{ $disponibile ? 'pointer' : 'not-allowed' }}; {{ !$disponibile ? 'opacity: 0.6;' : '' }}" 
                                 @if($disponibile) wire:click="aggiungiComponente({{ $articolo->id }})" @endif>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <strong class="{{ $disponibile ? 'text-primary' : 'text-muted' }}">{{ $articolo->codice }}</strong>
                                        <p class="mb-0 small">{{ $articolo->descrizione }}</p>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <small class="text-muted">{{ $articolo->categoria->nome ?? '' }}</small>
                                            <span class="badge {{ $giacenza > 0 ? 'bg-success' : 'bg-danger' }}">
                                                <iconify-icon icon="solar:box-bold" class="me-1"></iconify-icon>
                                                Giacenza: {{ $giacenza }}
                                            </span>
                                            @if($articolo->prezzo_acquisto)
                                                <small class="text-muted">€ {{ number_format($articolo->prezzo_acquisto, 2, ',', '.') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    @if($disponibile)
                                        <iconify-icon icon="solar:add-circle-bold" class="fs-3 text-success"></iconify-icon>
                                    @else
                                        <iconify-icon icon="solar:close-circle-bold" class="fs-3 text-danger"></iconify-icon>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <iconify-icon icon="solar:box-minimalistic-bold" class="fs-1 mb-2"></iconify-icon>
                                <p>Nessun articolo disponibile</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Componenti selezionati -->
                    <div class="border-top pt-3">
                        <h6 class="mb-3">Componenti Selezionati ({{ count($componenti) }})</h6>
                        @forelse($componenti as $comp)
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <strong class="small">{{ $comp['articolo']->codice }}</strong>
                                        <p class="mb-1 small text-muted">{{ $comp['articolo']->descrizione }}</p>
                                        <div class="d-flex align-items-center gap-2">
                                            <label class="small mb-0">Qta:</label>
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   style="width: 60px;"
                                                   min="1"
                                                   wire:model.blur="componenti.{{ $comp['articolo_id'] }}.quantita"
                                                   wire:change="aggiornaQuantita({{ $comp['articolo_id'] }}, $event.target.value)">
                                        </div>
                                    </div>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger" 
                                            wire:click="rimuoviComponente({{ $comp['articolo_id'] }})">
                                        <iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <iconify-icon icon="solar:cart-large-minimalistic-bold" class="fs-1 mb-2"></iconify-icon>
                                <p class="small">Nessun componente aggiunto</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Riepilogo Costi -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <iconify-icon icon="solar:calculator-bold-duotone" class="text-success me-2"></iconify-icon>
                        Riepilogo Costi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="text-muted mb-3">Dati Gioielleria Calcolati</h6>
                            <table class="table table-sm table-borderless">
                                @if($oroTotale)
                                <tr>
                                    <td width="150"><strong>Oro Totale:</strong></td>
                                    <td><span class="badge bg-warning">{{ $oroTotale }}</span></td>
                                </tr>
                                @endif
                                @if($brillantiTotali)
                                <tr>
                                    <td><strong>Brillanti Totali:</strong></td>
                                    <td><span class="badge bg-info">{{ $brillantiTotali }}</span></td>
                                </tr>
                                @endif
                                @if($pietreTotali)
                                <tr>
                                    <td><strong>Pietre Totali:</strong></td>
                                    <td><span class="badge bg-success">{{ $pietreTotali }}</span></td>
                                </tr>
                                @endif
                            </table>
                        </div>

                        <div class="col-lg-6">
                            <h6 class="text-muted mb-3">Costi</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td width="150"><strong>Materiali:</strong></td>
                                    <td class="text-end">€ {{ number_format($costoMaterialiTotale, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Lavorazione:</strong></td>
                                    <td class="text-end">€ {{ number_format($costoLavorazione, 2, ',', '.') }}</td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>TOTALE:</strong></td>
                                    <td class="text-end text-primary fw-bold">€ {{ number_format($costoTotale, 2, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer con pulsanti -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-footer bg-white border-top">
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('prodotti-finiti.index') }}" class="btn btn-secondary">
                            <iconify-icon icon="solar:arrow-left-bold"></iconify-icon>
                            Annulla
                        </a>
                        <button type="button" class="btn btn-success" wire:click="salva" wire:loading.attr="disabled" wire:loading.class="disabled">
                            <iconify-icon icon="solar:diskette-bold-duotone" class="me-1" wire:loading.remove wire:target="salva"></iconify-icon>
                            <span class="spinner-border spinner-border-sm me-1" wire:loading wire:target="salva"></span>
                            <span wire:loading.remove wire:target="salva">Salva Modifiche</span>
                            <span wire:loading wire:target="salva">Salvataggio in corso...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Listener per toast notifications
    window.addEventListener('show-toast', event => {
        const data = event.detail[0] || event.detail;
        const type = data.type || 'info';
        const message = data.message || 'Operazione completata';
        
        let icon = 'info';
        let title = 'Informazione';
        
        if (type === 'success') {
            icon = 'success';
            title = 'Successo';
        } else if (type === 'error') {
            icon = 'error';
            title = 'Errore';
        } else if (type === 'warning') {
            icon = 'warning';
            title = 'Attenzione';
        }
        
        Swal.fire({
            icon: icon,
            title: title,
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    });
</script>
@endpush
