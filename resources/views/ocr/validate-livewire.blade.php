@extends('layouts.vertical', ['title' => 'Validazione OCR'])

@php
    $structured = $document->ocr_structured_data ?? [];
    $rawText = $document->ocr_raw_data['text'] ?? '';
    $articoli = $structured['articoli'] ?? [];
@endphp

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Validazione documento OCR</h4>
                <small class="text-muted">{{ $document->pdf_original_name }} · ID #{{ $document->id }}</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('ocr.documents.pdf', $document) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <iconify-icon icon="solar:document-bold" class="me-1"></iconify-icon>
                    Apri PDF
                </a>
                <a href="{{ route('ocr.download', $document) }}" class="btn btn-outline-secondary btn-sm">
                    <iconify-icon icon="solar:download-minimalistic-bold" class="me-1"></iconify-icon>
                    Scarica
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form action="{{ route('ocr.validate.store', $document) }}" method="POST" class="row g-3">
            @csrf
            @method('POST')

            <div class="col-12 col-xl-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Dati documento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Numero documento</label>
                                <input type="text" name="numero" class="form-control @error('numero') is-invalid @enderror"
                                       value="{{ old('numero', $structured['numero'] ?? '') }}" required>
                                @error('numero')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data</label>
                                <input type="date" name="data" class="form-control @error('data') is-invalid @enderror"
                                       value="{{ old('data', $structured['data'] ?? '') }}" required>
                                @error('data')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fornitore</label>
                                <select name="fornitore_id" class="form-select @error('fornitore_id') is-invalid @enderror" required>
                                    <option value="">Seleziona fornitore...</option>
                                    @foreach($fornitori as $fornitore)
                                        <option value="{{ $fornitore->id }}"
                                            {{ (string) old('fornitore_id', $document->fornitore_id) === (string) $fornitore->id ? 'selected' : '' }}>
                                            {{ $fornitore->ragione_sociale }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('fornitore_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Partita IVA</label>
                                <input type="text" name="partita_iva" class="form-control @error('partita_iva') is-invalid @enderror"
                                       value="{{ old('partita_iva', $structured['partita_iva'] ?? '') }}">
                                @error('partita_iva')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Importo totale</label>
                                <input type="number" step="0.01" name="importo_totale" class="form-control @error('importo_totale') is-invalid @enderror"
                                       value="{{ old('importo_totale', $structured['importo_totale'] ?? '') }}">
                                @error('importo_totale')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quantità totale articoli</label>
                                <input type="number" name="quantita_articoli" class="form-control @error('quantita_articoli') is-invalid @enderror"
                                       value="{{ old('quantita_articoli', $structured['quantita_articoli'] ?? '') }}">
                                @error('quantita_articoli')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Note</label>
                                <textarea name="note" rows="3" class="form-control @error('note') is-invalid @enderror">{{ old('note', $document->notes) }}</textarea>
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Articoli riconosciuti</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="add-article-row">
                            <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                            Aggiungi riga
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="articoli-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 180px;">Codice</th>
                                        <th>Descrizione</th>
                                        <th style="width: 110px;">Qtà</th>
                                        <th style="width: 140px;">Costo Unit.</th>
                                        <th style="width: 140px;">Totale Riga</th>
                                        <th style="width: 180px;">Seriale</th>
                                        <th style="width: 180px;">EAN</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $index = 0; @endphp
                                    @foreach(old('articoli', $articoli) as $articolo)
                                        <tr>
                                            <td>
                                                <input type="text" name="articoli[{{ $index }}][codice]" class="form-control"
                                                       value="{{ $articolo['codice'] ?? '' }}" required>
                                            </td>
                                            <td>
                                                <input type="text" name="articoli[{{ $index }}][descrizione]" class="form-control"
                                                       value="{{ $articolo['descrizione'] ?? '' }}">
                                            </td>
                                            <td>
                                                <input type="number" min="1" name="articoli[{{ $index }}][quantita]" class="form-control"
                                                       value="{{ $articolo['quantita'] ?? 1 }}" required>
                                            </td>
                                            <td>
                                                <input type="text" name="articoli[{{ $index }}][prezzo_unitario]" class="form-control"
                                                       value="{{ $articolo['prezzo_unitario'] ?? '' }}" placeholder="0,00">
                                            </td>
                                            <td>
                                                <input type="text" name="articoli[{{ $index }}][prezzo_totale]" class="form-control"
                                                       value="{{ $articolo['prezzo_totale'] ?? '' }}" placeholder="0,00">
                                            </td>
                                            <td>
                                                <input type="text" name="articoli[{{ $index }}][numero_seriale]" class="form-control"
                                                       value="{{ $articolo['numero_seriale'] ?? '' }}">
                                            </td>
                                            <td>
                                                <input type="text" name="articoli[{{ $index }}][ean]" class="form-control"
                                                       value="{{ $articolo['ean'] ?? '' }}">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-link text-danger p-0 remove-article-row" title="Rimuovi">
                                                    <iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon>
                                                </button>
                                            </td>
                                        </tr>
                                        @php $index++; @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-5">
                    <a href="{{ route('ocr.index') }}" class="btn btn-outline-secondary">Indietro</a>
                    <button type="submit" class="btn btn-primary">
                        <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                        Salva validazione
                    </button>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Testo OCR grezzo</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-raw-text">
                            <iconify-icon icon="solar:copy-bold" class="me-1"></iconify-icon>
                            Copia
                        </button>
                    </div>
                    <div class="card-body">
                        <textarea id="raw-text" class="form-control" rows="20" readonly>{{ $rawText }}</textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const tableBody = document.querySelector('#articoli-table tbody');
            const addRowBtn = document.querySelector('#add-article-row');
            const copyBtn = document.querySelector('#copy-raw-text');
            let currentIndex = {{ count(old('articoli', $articoli)) }};

            if (addRowBtn) {
                addRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input type="text" name="articoli[${currentIndex}][codice]" class="form-control" required></td>
                        <td><input type="text" name="articoli[${currentIndex}][descrizione]" class="form-control"></td>
                        <td><input type="number" min="1" name="articoli[${currentIndex}][quantita]" class="form-control" value="1" required></td>
                        <td><input type="text" name="articoli[${currentIndex}][prezzo_unitario]" class="form-control" placeholder="0,00"></td>
                        <td><input type="text" name="articoli[${currentIndex}][prezzo_totale]" class="form-control" placeholder="0,00"></td>
                        <td><input type="text" name="articoli[${currentIndex}][numero_seriale]" class="form-control"></td>
                        <td><input type="text" name="articoli[${currentIndex}][ean]" class="form-control"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-link text-danger p-0 remove-article-row" title="Rimuovi">
                                <iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon>
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                    currentIndex++;
                });
            }

            tableBody?.addEventListener('click', function(event) {
                if (event.target.closest('.remove-article-row')) {
                    const row = event.target.closest('tr');
                    if (row) {
                        row.remove();
                    }
                }
            });

            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    const textarea = document.getElementById('raw-text');
                    textarea.removeAttribute('readonly');
                    textarea.select();
                    document.execCommand('copy');
                    textarea.setAttribute('readonly', true);
                    copyBtn.innerHTML = '<iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon> Copiato';
                    setTimeout(() => {
                        copyBtn.innerHTML = '<iconify-icon icon="solar:copy-bold" class="me-1"></iconify-icon> Copia';
                    }, 2000);
                });
            }
        })();
    </script>
@endpush
