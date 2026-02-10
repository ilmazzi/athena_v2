@extends('layouts.vertical', ['title' => 'Dettaglio DDT Deposito'])

@section('title', 'Dettagli DDT Deposito - ' . $ddtDeposito->numero)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <iconify-icon icon="solar:document-bold-duotone" class="me-2"></iconify-icon>
                        DDT Deposito {{ $ddtDeposito->numero }}
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('conti-deposito.index') }}">Conti Deposito</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('conti-deposito.gestisci', $ddtDeposito->contoDeposito->id) }}">{{ $ddtDeposito->contoDeposito->codice }}</a></li>
                            <li class="breadcrumb-item active">DDT {{ $ddtDeposito->numero }}</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('ddt-deposito.stampa', $ddtDeposito->id) }}"
                       class="btn btn-primary"
                       target="_blank">
                        <iconify-icon icon="solar:printer-bold" class="me-1"></iconify-icon>
                        Stampa DDT
                    </a>
                    <a href="{{ route('ddt-deposito.stampa', ['ddtDeposito' => $ddtDeposito->id, 'include_costi' => 1]) }}"
                       class="btn btn-outline-primary"
                       target="_blank">
                        <iconify-icon icon="solar:printer-bold" class="me-1"></iconify-icon>
                        Stampa DDT con costi
                    </a>
                    <a href="{{ route('conti-deposito.gestisci', $ddtDeposito->contoDeposito->id) }}" 
                       class="btn btn-outline-secondary">
                        <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                        Torna al Deposito
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Alert Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:close-circle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- Colonna principale --}}
        <div class="col-md-8">
            {{-- Informazioni DDT --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:document-text-bold" class="me-2"></iconify-icon>
                        Informazioni DDT
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Numero:</td>
                                    <td>{{ $ddtDeposito->numero }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Data Documento:</td>
                                    <td>{{ $ddtDeposito->data_documento->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Tipo:</td>
                                    <td>
                                        <span class="badge bg-light-{{ $ddtDeposito->tipo === 'invio' ? 'primary' : ($ddtDeposito->tipo === 'reso' ? 'warning' : 'success') }} text-{{ $ddtDeposito->tipo === 'invio' ? 'primary' : ($ddtDeposito->tipo === 'reso' ? 'warning' : 'success') }}">
                                            {{ $ddtDeposito->tipo_label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Stato:</td>
                                    <td>
                                        <span class="badge bg-light-{{ $ddtDeposito->stato_color }} text-{{ $ddtDeposito->stato_color }}">
                                            {{ $ddtDeposito->stato_label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Causale:</td>
                                    <td>{{ $ddtDeposito->causale }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Sede Mittente:</td>
                                    <td>{{ $ddtDeposito->sedeMittente->nome }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Sede Destinataria:</td>
                                    <td>{{ $ddtDeposito->sedeDestinataria->nome }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Creato da:</td>
                                    <td>{{ $ddtDeposito->creatoDa->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Creato il:</td>
                                    <td>{{ $ddtDeposito->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                                @if($ddtDeposito->numero_colli)
                                    <tr>
                                        <td class="fw-bold">Numero Colli:</td>
                                        <td>{{ $ddtDeposito->numero_colli }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dettagli Articoli --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:box-bold" class="me-2"></iconify-icon>
                        Articoli nel DDT
                        <span class="badge bg-light-primary text-primary ms-2">{{ $ddtDeposito->articoli_totali }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($ddtDeposito->dettagli->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Codice</th>
                                        <th>Descrizione</th>
                                        <th class="text-center">Quantità</th>
                                        <th class="text-end">Valore Unit.</th>
                                        <th class="text-end">Valore Tot.</th>
                                        <th class="text-center">Stato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ddtDeposito->dettagli as $dettaglio)
                                        <tr>
                                            <td>
                                                @if($dettaglio->isArticolo())
                                                    <span class="badge bg-light-primary text-primary">Articolo</span>
                                                @else
                                                    <span class="badge bg-light-warning text-warning">PF</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary">{{ $dettaglio->codice_item }}</span>
                                            </td>
                                            <td>{{ Str::limit($dettaglio->descrizione, 50) }}</td>
                                            <td class="text-center">
                                                <strong>{{ $dettaglio->quantita }}</strong>
                                                @if($dettaglio->quantita_ricevuta && $dettaglio->quantita_ricevuta != $dettaglio->quantita)
                                                    <br><small class="text-muted">Ricevuta: {{ $dettaglio->quantita_ricevuta }}</small>
                                                @endif
                                            </td>
                                            <td class="text-end">€{{ number_format($dettaglio->valore_unitario, 2, ',', '.') }}</td>
                                            <td class="text-end">
                                                <strong>€{{ number_format($dettaglio->valore_totale, 2, ',', '.') }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light-{{ $dettaglio->stato_riga_color }} text-{{ $dettaglio->stato_riga_color }}">
                                                    {{ $dettaglio->stato_riga }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="5" class="text-end">Totale Dichiarato:</th>
                                        <th class="text-end">€{{ number_format($ddtDeposito->valore_dichiarato, 2, ',', '.') }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <iconify-icon icon="solar:box-bold" class="fs-1 text-muted mb-2"></iconify-icon>
                            <p class="text-muted mb-0">Nessun articolo nel DDT</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Colonna laterale --}}
        <div class="col-md-4">
            {{-- Conto Deposito collegato --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:archive-bold" class="me-2"></iconify-icon>
                        Conto Deposito
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <iconify-icon icon="solar:box-bold-duotone" class="fs-1 text-primary me-3"></iconify-icon>
                        <div>
                            <h6 class="mb-0">{{ $ddtDeposito->contoDeposito->codice }}</h6>
                            <small class="text-muted">
                                Dal {{ $ddtDeposito->contoDeposito->data_invio->format('d/m/Y') }}
                                al {{ $ddtDeposito->contoDeposito->data_scadenza->format('d/m/Y') }}
                            </small>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <small class="text-muted">Stato:</small>
                        <span class="badge bg-light-{{ $ddtDeposito->contoDeposito->stato_color }} text-{{ $ddtDeposito->contoDeposito->stato_color }} ms-2">
                            {{ $ddtDeposito->contoDeposito->stato_label }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <a href="{{ route('conti-deposito.gestisci', $ddtDeposito->contoDeposito->id) }}" 
                           class="btn btn-outline-primary btn-sm w-100">
                            <iconify-icon icon="solar:settings-bold" class="me-1"></iconify-icon>
                            Gestisci Deposito
                        </a>
                    </div>
                </div>
            </div>

            {{-- Tracciamento --}}
            @if($ddtDeposito->corriere || $ddtDeposito->numero_tracking)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:delivery-bold" class="me-2"></iconify-icon>
                            Tracciamento
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($ddtDeposito->corriere)
                            <div class="mb-2">
                                <small class="text-muted">Corriere:</small>
                                <div class="fw-bold">{{ $ddtDeposito->corriere }}</div>
                            </div>
                        @endif
                        
                        @if($ddtDeposito->numero_tracking)
                            <div class="mb-2">
                                <small class="text-muted">Numero Tracking:</small>
                                <div class="fw-bold text-primary">{{ $ddtDeposito->numero_tracking }}</div>
                            </div>
                        @endif

                        @if($ddtDeposito->giorni_in_transito)
                            <div class="mb-2">
                                <small class="text-muted">Giorni in transito:</small>
                                <div class="fw-bold">{{ $ddtDeposito->giorni_in_transito }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Timeline Stato --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:history-bold" class="me-2"></iconify-icon>
                        Timeline
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Creato</h6>
                                <small class="text-muted">{{ $ddtDeposito->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                        </div>

                        @if($ddtDeposito->data_stampa)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Stampato</h6>
                                    <small class="text-muted">{{ $ddtDeposito->data_stampa->format('d/m/Y H:i') }}</small>
                                </div>
                            </div>
                        @endif

                        @if($ddtDeposito->data_spedizione)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-warning"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Spedito</h6>
                                    <small class="text-muted">{{ $ddtDeposito->data_spedizione->format('d/m/Y H:i') }}</small>
                                </div>
                            </div>
                        @endif

                        @if($ddtDeposito->data_ricezione)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Ricevuto</h6>
                                    <small class="text-muted">{{ $ddtDeposito->data_ricezione->format('d/m/Y H:i') }}</small>
                                </div>
                            </div>
                        @endif

                        @if($ddtDeposito->data_conferma)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Confermato</h6>
                                    <small class="text-muted">{{ $ddtDeposito->data_conferma->format('d/m/Y H:i') }}</small>
                                    @if($ddtDeposito->confermatoDa)
                                        <br><small class="text-muted">da {{ $ddtDeposito->confermatoDa->name }}</small>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Azioni --}}
            @if($ddtDeposito->stato === 'in_transito')
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:settings-bold" class="me-2"></iconify-icon>
                            Azioni
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('ddt-deposito.conferma-ricezione', $ddtDeposito->id) }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                                Conferma Ricezione
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Note --}}
            @if($ddtDeposito->note)
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:notes-bold" class="me-2"></iconify-icon>
                            Note
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $ddtDeposito->note }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    padding-left: 15px;
}
</style>
@endsection
