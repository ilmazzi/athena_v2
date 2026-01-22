@extends('layouts.vertical', ['title' => 'Carica documento OCR'])

@section('content')
    <div class="container-fluid">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Carica PDF per OCR</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('ocr.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Tipo documento</label>
                        <select name="tipo" class="form-select" required>
                            <option value="ddt">DDT</option>
                            <option value="fattura">Fattura</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">PDF</label>
                        <input type="file" name="pdf" class="form-control" accept=".pdf" required>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('ocr.index') }}" class="btn btn-outline-secondary">Annulla</a>
                        <button type="submit" class="btn btn-primary">
                            <iconify-icon icon="solar:cloud-upload-bold" class="me-1"></iconify-icon>
                            Carica
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
