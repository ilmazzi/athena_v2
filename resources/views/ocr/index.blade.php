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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Elenco documenti</h5>
                <span class="text-muted small">Totale: {{ $documents->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Documento</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Confidence</th>
                                <th class="text-center">Stato</th>
                                <th>Fornitore</th>
                                <th>Caricato il</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $document)
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
                                    <td class="text-center">
                                        @php
                                            $statusColors = [
                                                'processing' => 'info',
                                                'completed' => 'primary',
                                                'validated' => 'success',
                                                'rejected' => 'danger',
                                                'pending' => 'warning',
                                            ];
                                            $statusLabel = strtoupper($document->status ?? 'pending');
                                            $color = $statusColors[$document->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-light-{{ $color }} text-{{ $color }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        {{ $document->fornitore->ragione_sociale ?? '—' }}
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $document->created_at?->format('d/m/Y H:i') }}</small>
                                    </td>
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
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        Nessun documento caricato.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($documents->hasPages())
                <div class="card-footer">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection



