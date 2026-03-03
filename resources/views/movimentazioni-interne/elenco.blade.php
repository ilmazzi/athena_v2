@extends('layouts.vertical', ['title' => 'Elenco Movimentazioni Interne'])

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <iconify-icon icon="solar:transfer-horizontal-bold" class="me-2"></iconify-icon>
                        Elenco Movimentazioni Interne
                    </h1>
                    <p class="text-muted mb-0">Storico movimentazioni tra sedi</p>
                </div>
                <div>
                    <a class="btn btn-primary" href="{{ route('movimentazioni-interne.index') }}">
                        <iconify-icon icon="solar:plus-circle-bold" class="me-1"></iconify-icon>
                        Nuova movimentazione
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('movimentazioni-interne.elenco') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Ricerca</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                           placeholder="Numero, causale o note">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sede</label>
                    <select name="sede_id" class="form-select">
                        <option value="">Tutte</option>
                        @foreach($sedi as $sede)
                            <option value="{{ $sede->id }}" @selected((string) $sede->id === request('sede_id'))>
                                {{ $sede->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stato</label>
                    <select name="stato" class="form-select">
                        <option value="">Tutti</option>
                        @foreach($stati as $stato)
                            <option value="{{ $stato }}" @selected($stato === request('stato'))>
                                {{ ucfirst($stato) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Da</label>
                    <input type="date" name="da" class="form-control" value="{{ request('da') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">A</label>
                    <input type="date" name="a" class="form-control" value="{{ request('a') }}">
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary">
                        <iconify-icon icon="solar:magnifer-bold" class="me-1"></iconify-icon>
                        Cerca
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella -->
    <div class="card">
        <div class="card-body">
            @if($movimentazioni->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Numero</th>
                                <th>Stato</th>
                                <th>Da</th>
                                <th>A</th>
                                <th>Articoli</th>
                                <th>Note</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movimentazioni as $movimentazione)
                                <tr>
                                    <td>{{ $movimentazione->data_movimentazione?->format('d/m/Y') ?? '—' }}</td>
                                    <td><strong>{{ $movimentazione->numero_documento ?? '—' }}</strong></td>
                                    <td>
                                        @php
                                            $statoClass = match ($movimentazione->stato) {
                                                'confermata' => 'info',
                                                'completata' => 'success',
                                                'annullata' => 'danger',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $statoClass }}">
                                            {{ ucfirst($movimentazione->stato ?? 'bozza') }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $movimentazione->magazzinoPartenza?->nome_completo ?? '—' }}
                                    </td>
                                    <td>
                                        {{ $movimentazione->magazzinoDestinazione?->nome_completo ?? '—' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light-primary text-primary">
                                            {{ $movimentazione->dettagli_count }}
                                        </span>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($movimentazione->note, 40) }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-warning"
                                           href="{{ route('movimentazioni-interne.modifica', $movimentazione->id) }}">
                                            <iconify-icon icon="solar:pen-bold"></iconify-icon>
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="{{ route('movimentazioni-interne.stampa', $movimentazione->id) }}"
                                           target="_blank" rel="noopener">
                                            <iconify-icon icon="solar:printer-minimalistic-bold"></iconify-icon>
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('movimentazioni-interne.download', $movimentazione->id) }}">
                                            <iconify-icon icon="solar:download-minimalistic-bold"></iconify-icon>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $movimentazioni->links() }}
            @else
                <div class="text-center py-5 text-muted">
                    <iconify-icon icon="solar:box-outline" style="font-size: 3rem;"></iconify-icon>
                    <p class="mt-2">Nessuna movimentazione trovata</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
