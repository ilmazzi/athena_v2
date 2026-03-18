@extends('layouts.vertical', ['title' => $title])

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        @foreach($breadcrumbs as $breadcrumb)
                            @if($loop->last)
                                <li class="breadcrumb-item active">{{ $breadcrumb['title'] }}</li>
                            @else
                                <li class="breadcrumb-item">
                                    <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['title'] }}</a>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </div>
                <h4 class="page-title">{{ $pageTitle }}</h4>
            </div>
        </div>
    </div>

    <!-- Dettaglio Articolo -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:box-bold-duotone" class="me-2"></iconify-icon>
                        Dettaglio Articolo: {{ $articolo->codice }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-8">
                            <h6 class="fw-bold text-primary mb-3">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                Informazioni Base
                            </h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-semibold" width="40%">Codice:</td>
                                    <td>{{ $articolo->codice }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Descrizione:</td>
                                    <td>{{ $articolo->descrizione }}</td>
                                </tr>
                                @if($articolo->descrizione_estesa)
                                <tr>
                                    <td class="fw-semibold">Descrizione Estesa:</td>
                                    <td>{{ $articolo->descrizione_estesa }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td class="fw-semibold">Categoria:</td>
                                    <td>
                                        <span class="badge bg-light-primary text-primary">
                                            {{ $articolo->categoriaMerceologica->nome ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Sede:</td>
                                    <td>
                                        <span class="badge bg-light-info text-info">
                                            {{ $articolo->sede->nome ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Stato:</td>
                                    <td>
                                        @if($articolo->stato_articolo === 'disponibile')
                                            <span class="badge bg-light-success text-success">Disponibile</span>
                                        @elseif($articolo->stato_articolo === 'scaricato')
                                            <span class="badge bg-light-danger text-danger">Scaricato</span>
                                        @else
                                            <span class="badge bg-light-secondary text-secondary">{{ $articolo->stato_articolo }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Foto Articolo -->
                        <div class="col-md-4">
                            <h6 class="fw-bold text-secondary mb-3">
                                <iconify-icon icon="solar:gallery-bold" class="me-2"></iconify-icon>
                                Immagine
                            </h6>
                            @php
                                $fotoPrincipale = $articolo->foto_principale;
                                $fotoUrl = null;
                                if (!empty($fotoPrincipale)) {
                                    $fotoUrl = Str::startsWith($fotoPrincipale, ['http://', 'https://'])
                                        ? $fotoPrincipale
                                        : asset('storage/' . ltrim($fotoPrincipale, '/'));
                                }
                            @endphp
                            @if($fotoUrl)
                                <a href="{{ $fotoUrl }}" target="_blank" class="d-block">
                                    <img src="{{ $fotoUrl }}"
                                         alt="Foto {{ $articolo->codice }}"
                                         class="img-fluid rounded border"
                                         style="max-height: 260px; object-fit: cover;">
                                </a>
                            @else
                                <div class="text-muted">Nessuna immagine disponibile</div>
                            @endif
                        </div>
                        
                        <!-- Giacenza e Prezzi -->
                        <div class="col-md-12 mt-4">
                            <h6 class="fw-bold text-success mb-3">
                                <iconify-icon icon="solar:box-storage-bold" class="me-2"></iconify-icon>
                                Giacenza e Prezzi
                            </h6>
                            <table class="table table-borderless">
                                @if($articolo->giacenza)
                                <tr>
                                    <td class="fw-semibold" width="40%">Quantità Totale:</td>
                                    <td>{{ $articolo->giacenza->quantita }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Quantità Residua:</td>
                                    <td>
                                        <span class="badge {{ $articolo->giacenza->quantita_residua > 0 ? 'bg-light-success text-success' : 'bg-light-danger text-danger' }}">
                                            {{ $articolo->giacenza->quantita_residua }}
                                        </span>
                                    </td>
                                </tr>
                                @endif
                                @if($articolo->prezzo_acquisto)
                                <tr>
                                    <td class="fw-semibold">Prezzo Acquisto:</td>
                                    <td>€ {{ number_format($articolo->prezzo_acquisto, 2, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if($articolo->peso_lordo)
                                <tr>
                                    <td class="fw-semibold">Peso Lordo:</td>
                                    <td>{{ $articolo->peso_lordo }}g</td>
                                </tr>
                                @endif
                                @if($articolo->peso_netto)
                                <tr>
                                    <td class="fw-semibold">Peso Netto:</td>
                                    <td>{{ $articolo->peso_netto }}g</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- Caratteristiche Tecniche -->
                    @if($articolo->titolo || $articolo->caratura || $articolo->materiale || $articolo->colore)
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="fw-bold text-warning mb-3">
                                <iconify-icon icon="solar:settings-bold" class="me-2"></iconify-icon>
                                Caratteristiche Tecniche
                            </h6>
                            <div class="row">
                                @if($articolo->titolo)
                                <div class="col-md-3">
                                    <strong>Titolo:</strong> {{ $articolo->titolo }}
                                </div>
                                @endif
                                @if($articolo->caratura)
                                <div class="col-md-3">
                                    <strong>Caratura:</strong> {{ $articolo->caratura }}
                                </div>
                                @endif
                                @if($articolo->materiale)
                                <div class="col-md-3">
                                    <strong>Materiale:</strong> {{ $articolo->materiale }}
                                </div>
                                @endif
                                @if($articolo->colore)
                                <div class="col-md-3">
                                    <strong>Colore:</strong> {{ $articolo->colore }}
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Note -->
                    @if($articolo->note)
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="fw-bold text-secondary mb-3">
                                <iconify-icon icon="solar:notes-bold" class="me-2"></iconify-icon>
                                Note
                            </h6>
                            <p class="text-muted">{{ $articolo->note }}</p>
                        </div>
                    </div>
                    @endif

                    <!-- Azioni -->
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <a href="{{ route('articoli.index') }}" class="btn btn-secondary">
                                    <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                                    Torna all'Elenco
                                </a>
                                <button class="btn btn-primary">
                                    <iconify-icon icon="solar:pen-bold" class="me-1"></iconify-icon>
                                    Modifica
                                </button>
                                <button class="btn btn-success">
                                    <iconify-icon icon="solar:printer-bold" class="me-1"></iconify-icon>
                                    Stampa Etichetta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

