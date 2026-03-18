<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Giacenze e inventario</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('inventario.dashboard') }}" class="btn btn-sm btn-outline-primary">Dashboard Inventario</a>
                        <a href="{{ route('inventario.sessioni') }}" class="btn btn-sm btn-outline-primary">Sessioni</a>
                        <a href="{{ route('inventario.monitor') }}" class="btn btn-sm btn-outline-primary">Monitor</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Filtra sede</label>
                            <select class="form-select" wire:model.live="sedeId">
                                <option value="">Tutte le sedi</option>
                                @foreach($sedi as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8 text-md-end">
                            <a href="{{ route('giacenze.sedi') }}" class="btn btn-primary me-2">Gestisci per sede</a>
                            <a href="{{ route('giacenze.ubicazioni') }}" class="btn btn-primary">Gestisci per ubicazione</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Riepilogo giacenze</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th class="text-end">Righe</th>
                                    <th class="text-end">Residua</th>
                                    <th class="text-end">Critiche</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($riepilogoGiacenze as $riga)
                                    <tr>
                                        <td>{{ $riga->sede_nome }}</td>
                                        <td class="text-end">{{ number_format($riga->righe, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($riga->residua, 0, ',', '.') }}</td>
                                        <td class="text-end">
                                            <span class="{{ $riga->critiche > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                                {{ number_format($riga->critiche, 0, ',', '.') }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Nessun dato disponibile.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Ultime sessioni inventario</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Sessione</th>
                                    <th>Sede</th>
                                    <th>Stato</th>
                                    <th class="text-end">Progresso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sessioni as $sessione)
                                    <tr>
                                        <td>{{ $sessione->nome }}</td>
                                        <td>{{ $sessione->sede->nome ?? 'N/D' }}</td>
                                        <td>
                                            @if($sessione->stato === 'attiva')
                                                <span class="badge bg-success-subtle text-success">Attiva</span>
                                            @elseif($sessione->stato === 'chiusa')
                                                <span class="badge bg-primary-subtle text-primary">Chiusa</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($sessione->stato) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($sessione->progresso, 2, ',', '.') }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Nessuna sessione disponibile.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

