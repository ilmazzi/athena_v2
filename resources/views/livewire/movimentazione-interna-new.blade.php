<div>
    <!-- Filtri e Configurazione -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <iconify-icon icon="solar:settings-bold" class="me-1"></iconify-icon>
                Configurazione Movimentazione
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Sede Origine -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">Sede Origine *</label>
                    <select wire:model.live="sedeOrigineId" class="form-select">
                        <option value="">Seleziona sede...</option>
                        @foreach($sedi as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                        @endforeach
                    </select>
                    @error('sedeOrigineId') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <!-- Sede Destinazione -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">Sede Destinazione *</label>
                    <select wire:model.live="sedeDestinazioneId" class="form-select">
                        <option value="">Seleziona sede...</option>
                        @foreach($sedi as $sede)
                            @if($sede->id != $sedeOrigineId)
                                <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('sedeDestinazioneId') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <!-- Categoria -->
                <div class="col-md-2">
                    <label class="form-label">Magazzino</label>
                    <select wire:model.live="categoriaId" class="form-select">
                        <option value="">Tutti i magazzini</option>
                        @foreach($categorie as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Tipo Item -->
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select wire:model.live="tipoItem" class="form-select">
                        <option value="articoli">Articoli</option>
                        <option value="prodotti_finiti">Prodotti Finiti</option>
                    </select>
                </div>

                <!-- Ricerca -->
                <div class="col-md-2">
                    <label class="form-label">Ricerca</label>
                    <input type="text" wire:model.live.debounce.300ms="search" 
                           class="form-control" placeholder="Codice o descrizione...">
                </div>
            </div>
        </div>
    </div>

    <!-- Selezioni Attive -->
    @if($this->getTotaleSelezionati() > 0)
    <div class="card mb-4 border-success">
        <div class="card-header bg-light-success">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 text-success">
                    <iconify-icon icon="solar:bag-check-bold" class="me-1"></iconify-icon>
                    Articoli Selezionati ({{ $this->getTotaleSelezionati() }})
                </h6>
                <div>
                    <button type="button" class="btn btn-success btn-sm" 
                            wire:click="apriMovimentazioneModal"
                            @if(!$sedeDestinazioneId) disabled @endif>
                        <iconify-icon icon="solar:transfer-horizontal-bold" class="me-1"></iconify-icon>
                        Esegui Movimentazione
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Articoli Selezionati -->
                @foreach($articoliSelezionati as $articoloId => $data)
                <div class="col-md-6 mb-2">
                    <div class="d-flex align-items-center p-2 border rounded @if($data['in_vetrina'] ?? false) border-warning @endif">
                        <div class="me-2">
                            <span class="badge bg-light-primary text-primary">ART</span>
                            @if($data['in_vetrina'] ?? false)
                                <br><span class="badge bg-warning text-dark mt-1" title="In vetrina - sarà rimosso">
                                    <iconify-icon icon="solar:eye-bold" style="font-size: 10px;"></iconify-icon>
                                </span>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <strong>{{ $data['codice'] }}</strong><br>
                            <small class="text-muted">{{ Str::limit($data['descrizione'], 30) }}</small>
                            @if($data['warning_vetrina'] ?? false)
                                <br><small class="text-warning">⚠️ {{ $data['warning_vetrina'] }}</small>
                            @endif
                        </div>
                        <div class="text-end">
                            <input type="number" class="form-control form-control-sm" 
                                   style="width: 70px; display: inline-block;"
                                   wire:model="articoliSelezionati.{{ $articoloId }}.quantita"
                                   min="1" max="{{ $data['max_quantita'] }}">
                            <br><small class="text-muted">Max: {{ $data['max_quantita'] }}</small>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        wire:click="rimuoviArticoloSelezionato({{ $articoloId }})">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Prodotti Finiti Selezionati -->
                @foreach($prodottiFinitiSelezionati as $pfId => $data)
                <div class="col-md-6 mb-2">
                    <div class="d-flex align-items-center p-2 border rounded">
                        <span class="badge bg-light-warning text-warning me-2">PF</span>
                        <div class="flex-grow-1">
                            <strong>{{ $data['codice'] }}</strong><br>
                            <small class="text-muted">{{ Str::limit($data['descrizione'], 30) }}</small>
                            @if(!empty($data['componenti']))
                                <div class="mt-1">
                                    @foreach($data['componenti'] as $componente)
                                        <div class="small text-muted">
                                            • {{ $componente['codice'] }} - {{ Str::limit($componente['descrizione'], 30) }}
                                            (x{{ $componente['quantita'] }})
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light-success text-success">Q.tà: 1</span>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        wire:click="rimuoviProdottoFinitoSelezionato({{ $pfId }})">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Lista Articoli/PF Disponibili -->
    @if($sedeOrigineId)
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                @if($tipoItem === 'articoli')
                    <iconify-icon icon="solar:box-bold" class="me-1"></iconify-icon>
                    Articoli Disponibili
                @else
                    <iconify-icon icon="solar:star-bold" class="me-1"></iconify-icon>
                    Prodotti Finiti Disponibili
                @endif
            </h5>
        </div>
        <div class="card-body">
            @if($tipoItem === 'articoli')
                <!-- Lista Articoli -->
                @if($articoliDisponibili->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">Sel.</th>
                                    <th>Codice</th>
                                    <th>Descrizione</th>
                                    <th>Magazzino</th>
                                    <th>Giacenza</th>
                                    <th>Stato</th>
                                    <th>Prezzo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($articoliDisponibili as $articolo)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input"
                                               wire:click="toggleArticolo({{ $articolo->id }})"
                                               @if(isset($articoliSelezionati[$articolo->id])) checked @endif>
                                    </td>
                                    <td><strong>{{ $articolo->codice }}</strong></td>
                                    <td>{{ Str::limit($articolo->descrizione, 50) }}</td>
                                    <td>{{ $articolo->categoriaMerceologica->nome ?? 'N/A' }}</td>
                                    <td>
                                        @if($articolo->giacenza)
                                            <span class="badge bg-light-info text-info">
                                                {{ $articolo->giacenza->quantita_residua }}
                                            </span>
                                        @else
                                            <span class="badge bg-light-secondary">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($articolo->isInVetrina())
                                            <span class="badge bg-warning text-dark" title="In vetrina">
                                                <iconify-icon icon="solar:eye-bold" style="font-size: 10px;"></iconify-icon>
                                                Vetrina
                                            </span>
                                        @else
                                            <span class="badge bg-success text-white">
                                                <iconify-icon icon="solar:check-circle-bold" style="font-size: 10px;"></iconify-icon>
                                                Disponibile
                                            </span>
                                        @endif
                                    </td>
                                    <td>€{{ number_format($articolo->prezzo_acquisto, 2, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $articoliDisponibili->links() }}
                @else
                    <div class="text-center py-4 text-muted">
                        <iconify-icon icon="solar:box-outline" style="font-size: 3rem;"></iconify-icon>
                        <p class="mt-2">Nessun articolo disponibile</p>
                    </div>
                @endif
            @else
                <!-- Lista Prodotti Finiti -->
                @if($prodottiFinitiDisponibili->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">Sel.</th>
                                    <th>Codice</th>
                                    <th>Descrizione</th>
                                    <th>Componenti</th>
                                    <th>Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prodottiFinitiDisponibili as $pf)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input"
                                               wire:click="toggleProdottoFinito({{ $pf->id }})"
                                               @if(isset($prodottiFinitiSelezionati[$pf->id])) checked @endif>
                                    </td>
                                    <td><strong>{{ $pf->codice }}</strong></td>
                                    <td>{{ Str::limit($pf->descrizione, 50) }}</td>
                                    <td>
                                        <span class="badge bg-light-secondary">
                                            {{ $pf->componentiArticoli->count() }} comp.
                                        </span>
                                        <div class="small text-muted mt-1">
                                            @foreach($pf->componentiArticoli as $componente)
                                                <div>
                                                    • {{ $componente->articolo->codice ?? 'N/A' }} - {{ Str::limit($componente->articolo->descrizione ?? 'N/A', 30) }}
                                                    (x{{ $componente->quantita }})
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>€{{ number_format($pf->costo_totale, 2, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $prodottiFinitiDisponibili->links() }}
                @else
                    <div class="text-center py-4 text-muted">
                        <iconify-icon icon="solar:star-outline" style="font-size: 3rem;"></iconify-icon>
                        <p class="mt-2">Nessun prodotto finito disponibile</p>
                    </div>
                @endif
            @endif
        </div>
    </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <iconify-icon icon="solar:buildings-outline" style="font-size: 3rem;"></iconify-icon>
                <p class="mt-2">Seleziona la sede origine per visualizzare gli articoli disponibili</p>
            </div>
        </div>
    @endif

    <!-- Modal Movimentazione -->
    @if($showMovimentazioneModal)
    <div class="modal fade show" style="display: block;" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:transfer-horizontal-bold" class="me-1"></iconify-icon>
                        Conferma Movimentazione
                    </h5>
                    <button type="button" class="btn-close" wire:click="chiudiMovimentazioneModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Da:</strong> 
                            @php $sedeOrigine = $sedi->find($sedeOrigineId); @endphp
                            {{ $sedeOrigine?->nome }}
                        </div>
                        <div class="col-md-6">
                            <strong>A:</strong>
                            @php $sedeDestinazione = $sedi->find($sedeDestinazioneId); @endphp
                            {{ $sedeDestinazione?->nome }}
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Data Movimentazione *</label>
                            <input type="date" wire:model="dataMovimentazione" class="form-control">
                            @error('dataMovimentazione') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Note</label>
                            <textarea wire:model="noteMovimentazione" class="form-control" rows="2" 
                                      placeholder="Note opzionali..."></textarea>
                            @error('noteMovimentazione') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3">Articoli da Trasferire ({{ $this->getTotaleSelezionati() }})</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Codice</th>
                                    <th>Descrizione</th>
                                    <th>Quantità</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($articoliSelezionati as $data)
                                <tr>
                                    <td><span class="badge bg-light-primary text-primary">ART</span></td>
                                    <td>{{ $data['codice'] }}</td>
                                    <td>{{ $data['descrizione'] }}</td>
                                    <td>{{ $data['quantita'] }}</td>
                                </tr>
                                @endforeach
                                @foreach($prodottiFinitiSelezionati as $data)
                                <tr>
                                    <td><span class="badge bg-light-warning text-warning">PF</span></td>
                                    <td>{{ $data['codice'] }}</td>
                                    <td>{{ $data['descrizione'] }}</td>
                                    <td>1 (comp. inclusi)</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="chiudiMovimentazioneModal">
                        <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                        Annulla
                    </button>
                    <button type="button" class="btn btn-success" 
                            wire:click="eseguiMovimentazione"
                            wire:loading.attr="disabled"
                            wire:target="eseguiMovimentazione">
                        <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                        Conferma Movimentazione
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif
</div>
