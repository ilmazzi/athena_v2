@extends('layouts.vertical')

@section('title') Categorie Merceologiche @endsection

@section('content')
<div class="container-fluid">
    <!-- Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Categorie Merceologiche</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Categorie Merceologiche</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-0">Categorie Totali</p>
                        </div>
                        <div class="flex-shrink-0">
                            <h5 class="text-success fs-14 mb-0">
                                <i class="bx bx-category align-middle"></i>
                            </h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-4">
                        <div>
                            <h4 class="fs-22 fw-semibold ff-secondary mb-0">
                                {{ $magazzini->count() }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-0">Articoli Totali</p>
                        </div>
                        <div class="flex-shrink-0">
                            <h5 class="text-info fs-14 mb-0">
                                <i class="bx bx-package align-middle"></i>
                            </h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-4">
                        <div>
                            <h4 class="fs-22 fw-semibold ff-secondary mb-0">
                                {{ $magazzini->sum('articoli_count') }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-0">Categorie Attive</p>
                        </div>
                        <div class="flex-shrink-0">
                            <h5 class="text-primary fs-14 mb-0">
                                <i class="bx bx-check-circle align-middle"></i>
                            </h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-4">
                        <div>
                            <h4 class="fs-22 fw-semibold ff-secondary mb-0">
                                {{ $magazzini->where('attivo', true)->count() }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <p class="text-uppercase fw-medium text-muted mb-0">Ultimo Aggiornamento</p>
                        </div>
                        <div class="flex-shrink-0">
                            <h5 class="text-warning fs-14 mb-0">
                                <i class="bx bx-time align-middle"></i>
                            </h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-4">
                        <div>
                            <p class="text-muted mb-0">
                                {{ $magazzini->max('updated_at')?->format('d/m/Y') ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categorie List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-0">
                    <div class="d-flex align-items-center">
                        <h5 class="card-title mb-0 flex-grow-1">Elenco Categorie Merceologiche</h5>
                        <div class="flex-shrink-0">
                            <a href="{{ route('articoli.index') }}" class="btn btn-primary">
                                <i class="bx bx-package me-1"></i> Vedi Tutti gli Articoli
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Nome Categoria</th>
                                    <th scope="col">Codice</th>
                                    <th scope="col">Articoli</th>
                                    <th scope="col">Stato</th>
                                    <th scope="col">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($magazzini as $magazzino)
                                <tr>
                                    <td class="fw-medium">{{ $magazzino->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-2">
                                                <div class="avatar-xs">
                                                    <div class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                        {{ substr($magazzino->nome, 0, 1) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0">{{ $magazzino->nome }}</h6>
                                                @if($magazzino->descrizione)
                                                <p class="text-muted mb-0 fs-12">{{ $magazzino->descrizione }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info-subtle text-info">{{ $magazzino->codice ?? 'N/A' }}</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <span class="badge bg-success fs-12">{{ $magazzino->articoli_count }} articoli</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($magazzino->attivo)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bx bx-check-circle align-middle"></i> Attivo
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">
                                                <i class="bx bx-x-circle align-middle"></i> Inattivo
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-3 flex-wrap">
                                            <a href="{{ route('articoli.index', ['magazzinoFilter' => $magazzino->id]) }}" 
                                               class="link-primary fs-15">
                                                <i class="bx bx-package"></i> Articoli
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-category fs-1 text-muted mb-2"></i>
                                            <h5 class="text-muted">Nessuna Categoria Merceologica</h5>
                                            <p class="text-muted mb-0">Non ci sono categorie merceologiche configurate</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



