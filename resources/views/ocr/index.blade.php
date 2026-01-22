@extends('layouts.vertical', ['title' => 'Documenti OCR'])

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h4 class="mb-0">Documenti OCR</h4>
                <small class="text-muted">Storico dei PDF caricati e processati</small>
            </div>
            <a href="{{ route('ocr.upload') }}" class="btn btn-primary">
                <iconify-icon icon="solar:upload-bold" class="me-1"></iconify-icon>
                Carica nuovo PDF
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                @if($documents->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Fornitore</th>
                                    <th>Numero</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th class="text-end">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documents as $document)
                                    <tr>
                                        <td>#{{ $document->id }}</td>
                                        <td>{{ strtoupper($document->tipo) }}</td>
                                        <td>{{ $document->fornitore->ragione_sociale ?? '-' }}</td>
                                        <td>{{ $document->ocr_structured_data['numero'] ?? '-' }}</td>
                                        <td>{{ $document->ocr_structured_data['data'] ?? '-' }}</td>
                                        <td>{{ $document->status }}</td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('ocr.validate', $document) }}" class="btn btn-outline-secondary" title="Valida">
                                                    <iconify-icon icon="solar:edit-bold"></iconify-icon>
                                                </a>
                                                <a href="{{ route('ocr.documents.pdf', $document) }}" target="_blank" class="btn btn-outline-primary" title="Apri PDF">
                                                    <iconify-icon icon="solar:document-bold"></iconify-icon>
                                                </a>
                                                <a href="{{ route('ocr.download', $document) }}" class="btn btn-outline-success" title="Scarica">
                                                    <iconify-icon icon="solar:download-bold"></iconify-icon>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <p class="text-muted mb-0">Nessun documento presente</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-3">
            {{ $documents->links() }}
        </div>
    </div>
@endsection
