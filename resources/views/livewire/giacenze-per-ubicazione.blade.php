<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Totale righe giacenza</div>
                    <div class="h4 mb-0">{{ number_format($statistiche['totale'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Con ubicazione assegnata</div>
                    <div class="h4 mb-0 text-success">{{ number_format($statistiche['coperte'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Senza ubicazione</div>
                    <div class="h4 mb-0 text-warning">{{ number_format($statistiche['senza_ubicazione'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Filtri</h5>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" wire:model.live.debounce.400ms="search" placeholder="Cerca articolo o ubicazione">
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="sedeId">
                        <option value="">Tutte le sedi</option>
                        @foreach($sedi as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="perPage">
                        <option value="25">25 righe</option>
                        <option value="50">50 righe</option>
                        <option value="100">100 righe</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="soloSenzaUbicazione" wire:model.live="soloSenzaUbicazione">
                        <label class="form-check-label" for="soloSenzaUbicazione">Solo senza</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Dettaglio per ubicazione</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Articolo</th>
                            <th>Sede</th>
                            <th>Ubicazione</th>
                            <th class="text-end">Qta</th>
                            <th class="text-end">Residua</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($giacenze as $giacenza)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $giacenza->articolo->codice ?? ('#'.$giacenza->articolo_id) }}</div>
                                    <div class="text-muted small">{{ $giacenza->articolo->descrizione ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $giacenza->sede->nome ?? 'N/D' }}</td>
                                <td>
                                    @if($giacenza->ubicazione)
                                        <div class="fw-semibold">{{ $giacenza->ubicazione->codice }}</div>
                                        <div class="small text-muted">{{ $giacenza->ubicazione->descrizione }}</div>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">Non assegnata</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((int) $giacenza->quantita, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((int) $giacenza->quantita_residua, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary" wire:click="apriAssegnazioneUbicazione({{ $giacenza->id }})">
                                        Assegna
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nessuna giacenza trovata.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">
                {{ $giacenze->links() }}
            </div>
        </div>
    </div>

    @if($showUbicazioneModal && $giacenzaDaAssegnare)
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Assegna ubicazione</h5>
                        <button type="button" class="btn-close" wire:click="chiudiAssegnazioneUbicazione"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <div class="small text-muted">Articolo</div>
                            <div class="fw-semibold">{{ $giacenzaDaAssegnare->articolo->codice ?? ('#'.$giacenzaDaAssegnare->articolo_id) }}</div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Ubicazione</label>
                            <select class="form-select" wire:model.defer="ubicazioneId">
                                <option value="">Nessuna</option>
                                @foreach($ubicazioniDisponibili as $ubicazione)
                                    <option value="{{ $ubicazione->id }}">
                                        {{ $ubicazione->codice }} - {{ $ubicazione->descrizione }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="chiudiAssegnazioneUbicazione">Annulla</button>
                        <button class="btn btn-primary" wire:click="salvaAssegnazioneUbicazione">Salva</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>

