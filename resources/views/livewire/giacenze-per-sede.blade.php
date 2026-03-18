<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Giacenze per sede</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" class="form-control" wire:model.live.debounce.400ms="search" placeholder="Cerca per codice, descrizione o seriale">
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
                                <input class="form-check-input" type="checkbox" id="soloCritiche" wire:model.live="soloCritiche">
                                <label class="form-check-label" for="soloCritiche">Solo critiche</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        @foreach($riepilogoSedi as $riga)
            <div class="col-xl-3 col-md-6 mb-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $riga->sede_nome }}</div>
                        <div class="fw-semibold">Righe: {{ number_format($riga->righe_giacenza, 0, ',', '.') }}</div>
                        <div class="small">Qta totale: {{ number_format($riga->quantita_totale, 0, ',', '.') }}</div>
                        <div class="small">Qta residua: {{ number_format($riga->quantita_residua_totale, 0, ',', '.') }}</div>
                        <div class="small {{ $riga->righe_critiche > 0 ? 'text-danger' : 'text-success' }}">
                            Critiche: {{ number_format($riga->righe_critiche, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Dettaglio giacenze</h5>
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
                            <th class="text-end">Minima</th>
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
                                        {{ $giacenza->ubicazione->codice }}
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">Non assegnata</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((int) $giacenza->quantita, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <span class="{{ (int)$giacenza->quantita_residua <= (int)$giacenza->quantita_minima ? 'text-danger fw-semibold' : '' }}">
                                        {{ number_format((int) $giacenza->quantita_residua, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format((int) $giacenza->quantita_minima, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary" wire:click="apriRettifica({{ $giacenza->id }})">
                                        Rettifica
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nessuna giacenza trovata.</td>
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

    @if($showRettificaModal && $giacenzaDaRettificare)
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rettifica giacenza</h5>
                        <button type="button" class="btn-close" wire:click="chiudiRettifica"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <div class="small text-muted">Articolo</div>
                            <div class="fw-semibold">{{ $giacenzaDaRettificare->articolo->codice ?? ('#'.$giacenzaDaRettificare->articolo_id) }}</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Quantita totale</label>
                            <input type="number" min="0" class="form-control" wire:model.defer="nuovaQuantita">
                            @error('nuovaQuantita') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Quantita residua</label>
                            <input type="number" min="0" class="form-control" wire:model.defer="nuovaQuantitaResidua">
                            @error('nuovaQuantitaResidua') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" rows="2" wire:model.defer="noteRettifica"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="chiudiRettifica">Annulla</button>
                        <button class="btn btn-primary" wire:click="salvaRettifica">Salva rettifica</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>

