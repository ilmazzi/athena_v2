@extends('layouts.vertical', ['title' => 'Dashboard OCR'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-xl-6">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Documenti da validare</h5>
                        <a href="{{ route('ocr.index') }}" class="btn btn-sm btn-outline-primary">Vai all'elenco</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse($pending as $document)
                                <a href="{{ route('ocr.validate', $document) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                                    <div class="me-3">
                                        <div class="fw-semibold text-truncate" style="max-width: 220px;">
                                            {{ $document->pdf_original_name }}
                                        </div>
                                        <small class="text-muted">#{{ $document->id }} · {{ strtoupper($document->tipo) }}</small>
                                    </div>
                                    <span class="badge bg-warning">pending</span>
                                </a>
                            @empty
                                <div class="text-center text-muted py-4">Nessun documento in attesa</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card mb-3">
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
                                        <small class="text-muted">#{{ $document->id }} · {{ strtoupper($document->tipo) }}</small>
                                    </div>
                                    <span class="badge bg-success">validated</span>
                                </a>
                            @empty
                                <div class="text-center text-muted py-4">Nessun documento validato</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
