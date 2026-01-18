@extends('layouts.vertical', ['title' => 'Dettaglio Proforma Deposito'])

@section('title', 'Proforma Deposito - ' . $proforma->numero)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 d-flex align-items-center gap-2">
                        <iconify-icon icon="solar:document-text-bold-duotone"></iconify-icon>
                        Proforma {{ $proforma->numero }}
                        @if($proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA)
                            <span class="badge bg-success">Fatturata</span>
                        @else
                            <span class="badge bg-warning text-dark">Da fatturare</span>
                        @endif
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('conti-deposito.index') }}">Conti Deposito</a></li>
                            @if($proforma->contoDeposito)
                                <li class="breadcrumb-item"><a href="{{ route('conti-deposito.gestisci', $proforma->contoDeposito->id) }}">{{ $proforma->contoDeposito->codice }}</a></li>
                            @endif
                            <li class="breadcrumb-item active">Proforma {{ $proforma->numero }}</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('proforme-deposito.stampa', ['proformaDeposito' => $proforma->id]) }}" class="btn btn-primary" target="_blank">
                        <iconify-icon icon="solar:printer-bold" class="me-1"></iconify-icon>
                        Stampa
                    </a>
                    @if($proforma->fattura_pdf_url)
                        <a href="{{ $proforma->fattura_pdf_url }}" target="_blank" class="btn btn-success">
                            <iconify-icon icon="solar:download-bold" class="me-1"></iconify-icon>
                            Scarica fattura
                        </a>
                    @endif
                    @if($proforma->contoDeposito)
                        <a href="{{ route('conti-deposito.gestisci', $proforma->contoDeposito->id) }}" class="btn btn-outline-secondary">
                            <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                            Torna al deposito
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:document-bold" class="me-2"></iconify-icon>
                        Informazioni Proforma
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-5"><strong>Numero:</strong></div>
                        <div class="col-7">{{ $proforma->numero }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Data documento:</strong></div>
                        <div class="col-7">{{ $proforma->data_documento->format('d/m/Y') }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Anno:</strong></div>
                        <div class="col-7">{{ $proforma->anno }}</div>
                    </div>
                    @if($proforma->sede)
                        <div class="row mb-2">
                            <div class="col-5"><strong>Sede:</strong></div>
                            <div class="col-7">{{ $proforma->sede->nome }}</div>
                        </div>
                    @endif
                    @if($proforma->contoDeposito)
                        <div class="row mb-2">
                            <div class="col-5"><strong>Conto Deposito:</strong></div>
                            <div class="col-7">
                                <a href="{{ route('conti-deposito.gestisci', $proforma->contoDeposito->id) }}">
                                    {{ $proforma->contoDeposito->codice }}
                                </a>
                            </div>
                        </div>
                    @endif
                    @if($proforma->ddtInvio)
                        <div class="row mb-2">
                            <div class="col-5"><strong>DDT Invio:</strong></div>
                            <div class="col-7">
                                <a href="{{ route('ddt-deposito.show', $proforma->ddtInvio->id) }}">
                                    {{ $proforma->ddtInvio->numero }}
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:user-bold" class="me-2"></iconify-icon>
                        Cliente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-5"><strong>Nome:</strong></div>
                        <div class="col-7">{{ $proforma->cliente_nome }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Cognome:</strong></div>
                        <div class="col-7">{{ $proforma->cliente_cognome }}</div>
                    </div>
                    @if($proforma->cliente_telefono)
                        <div class="row mb-2">
                            <div class="col-5"><strong>Telefono:</strong></div>
                            <div class="col-7">{{ $proforma->cliente_telefono }}</div>
                        </div>
                    @endif
                    @if($proforma->cliente_email)
                        <div class="row mb-2">
                            <div class="col-5"><strong>Email:</strong></div>
                            <div class="col-7">{{ $proforma->cliente_email }}</div>
                        </div>
                    @endif
                </div>
            </div>

            @if($proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA)
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
                            Dati fatturazione
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-5"><strong>Numero fattura:</strong></div>
                            <div class="col-7">{{ $proforma->fattura_numero ?? '—' }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5"><strong>Data fattura:</strong></div>
                            <div class="col-7">{{ $proforma->fattura_data ? $proforma->fattura_data->format('d/m/Y') : '—' }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5"><strong>Registrata da:</strong></div>
                            <div class="col-7">{{ $proforma->fatturataDa?->name ?? '—' }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5"><strong>Il:</strong></div>
                            <div class="col-7">{{ $proforma->fatturata_il?->format('d/m/Y H:i') ?? '—' }}</div>
                        </div>
                        @if($proforma->fattura_note)
                            <div class="mt-2">
                                <strong>Note:</strong>
                                <p class="mb-0 text-muted">{{ $proforma->fattura_note }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Importi --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:wallet-bold" class="me-2"></iconify-icon>
                        Importi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Imponibile</small>
                            <div class="h5">€{{ number_format($proforma->imponibile, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">IVA</small>
                            <div class="h5">€{{ number_format($proforma->iva, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Totale</small>
                            <div class="h4 text-primary">€{{ number_format($proforma->totale, 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Movimenti --}}
    @php
        $movimenti = $proforma->movimenti;
    @endphp
    @if($movimenti && $movimenti->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:box-bold" class="me-2"></iconify-icon>
                            Articoli e prodotti venduti
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Codice</th>
                                        <th>Descrizione</th>
                                        <th class="text-center">Quantità</th>
                                        <th class="text-end">Costo unitario</th>
                                        <th class="text-end">Totale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($movimenti as $movimento)
                                        <tr>
                                            <td>
                                                <span class="badge bg-light-{{ $movimento->isArticolo() ? 'primary' : 'warning' }} text-{{ $movimento->isArticolo() ? 'primary' : 'warning' }}">
                                                    {{ $movimento->isArticolo() ? 'Articolo' : 'Prodotto Finito' }}
                                                </span>
                                            </td>
                                            <td>{{ $movimento->getCodiceItem() }}</td>
                                            <td>{{ $movimento->getDescrizioneItem() }}</td>
                                            <td class="text-center">{{ $movimento->quantita }}</td>
                                            <td class="text-end">€{{ number_format($movimento->costo_unitario, 2, ',', '.') }}</td>
                                            <td class="text-end">€{{ number_format($movimento->costo_totale, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($proforma->note)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:notes-bold" class="me-2"></iconify-icon>
                            Note
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ $proforma->note }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

