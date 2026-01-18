@extends('layouts.vertical', ['title' => 'Carica documento OCR'])

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Carica PDF per OCR</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('ocr.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipo documento</label>
                                <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                                    <option value="ddt" {{ old('tipo') === 'ddt' ? 'selected' : '' }}>DDT</option>
                                    <option value="fattura" {{ old('tipo') === 'fattura' ? 'selected' : '' }}>Fattura</option>
                                </select>
                                @error('tipo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">PDF</label>
                                <div id="pdf-dropzone" class="border border-dashed rounded-3 p-4 text-center bg-light bg-opacity-50">
                                    <iconify-icon icon="solar:document-add-bold-duotone" class="display-6 text-primary mb-2"></iconify-icon>
                                    <p class="mb-1">Trascina qui il file PDF oppure</p>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="pdf-browse-btn">Seleziona file</button>
                                    <input type="file" name="pdf" id="pdf-input" class="d-none @error('pdf') is-invalid @enderror" accept="application/pdf" required>
                                    <div class="mt-2 small text-muted" id="pdf-filename">Nessun file selezionato (Max 10 MB)</div>
                                </div>
                                @error('pdf')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('ocr.index') }}" class="btn btn-outline-secondary">Annulla</a>
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:cloud-upload-bold" class="me-1"></iconify-icon>
                                    Carica e processa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const dropzone = document.getElementById('pdf-dropzone');
            const input = document.getElementById('pdf-input');
            const browseBtn = document.getElementById('pdf-browse-btn');
            const filenameLabel = document.getElementById('pdf-filename');

            if (!dropzone || !input) {
                return;
            }

            const setFile = (file) => {
                if (!file) return;
                if (file.type !== 'application/pdf') {
                    filenameLabel.textContent = 'Il file deve essere un PDF';
                    filenameLabel.classList.add('text-danger');
                    return;
                }

                filenameLabel.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                filenameLabel.classList.remove('text-danger');
            };

            browseBtn?.addEventListener('click', () => input.click());

            input.addEventListener('change', (event) => {
                const file = event.target.files[0];
                setFile(file);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    dropzone.classList.add('border-primary', 'bg-white');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    dropzone.classList.remove('border-primary', 'bg-white');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                const file = event.dataTransfer.files[0];
                if (file) {
                    input.files = event.dataTransfer.files;
                    setFile(file);
                }
            });
        })();
    </script>
@endpush

