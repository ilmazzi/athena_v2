@extends('layouts.vertical', ['title' => 'Dashboard OCR'])

@section('content')
    <div class="container-fluid">
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase">Documenti totali</h6>
                        <h2 class="fw-bold mb-0">{{ number_format($stats['totali']) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase">Da validare</h6>
                        <h2 class="fw-bold mb-0">{{ number_format($stats['da_validare']) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase">Validati oggi</h6>
                        <h2 class="fw-bold mb-0">{{ number_format($stats['validati_oggi']) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase">Confidence medio</h6>
                        <h2 class="fw-bold mb-0">{{ $stats['avg_confidence'] ? number_format($stats['avg_confidence'], 2) . '%' : '—' }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Documenti da validare</h5>
                        <a href="{{ route('ocr.index') }}" class="btn btn-sm btn-outline-primary">Vai all'elenco</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Documento</th>
                                        <th class="text-center">Tipo</th>
                                        <th class="text-center">Confidence</th>
                                        <th>Caricato il</th>
                                        <th class="text-end">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingDocuments as $document)
                                        <tr>
                                            <td class="text-truncate" style="max-width: 220px;">
                                                <div class="fw-semibold">{{ $document->pdf_original_name }}</div>
                                                <small class="text-muted">#{{ $document->id }}</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light-primary text-primary">{{ strtoupper($document->tipo) }}</span>
                                            </td>
                                            <td class="text-center">
                                                {{ $document->confidence_score ? number_format($document->confidence_score, 1) . '%' : '—' }}
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $document->created_at?->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('ocr.validate', $document) }}" class="btn btn-sm btn-outline-secondary">
                                                    Valida
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                Nessun documento in attesa di validazione.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Ultimi validati</h5>
                        <a href="{{ route('ocr.index') }}" class="btn btn-sm btn-outline-primary">Vedi tutto</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @forelse($recentValidated as $document)
                                <a href="{{ route('ocr.validate', $document) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                                    <div class="me-3">
                                        <div class="fw-semibold text-truncate" style="max-width: 220px;">
                                            {{ $document->pdf_original_name }}
                                        </div>
                                        <small class="text-muted d-block">
                                            Validato da {{ $document->validator?->name ?? '—' }} il {{ optional($document->validated_at)->format('d/m/Y H:i') }}
                                        </small>
                                        @if($document->fornitore)
                                            <small class="text-muted">Fornitore: {{ $document->fornitore->ragione_sociale }}</small>
                                        @endif
                                    </div>
                                    <span class="badge bg-success-subtle text-success">OK</span>
                                </a>
                            @empty
                                <div class="text-center text-muted py-4">
                                    Nessun documento validato di recente.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection



