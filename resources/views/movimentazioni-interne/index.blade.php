@extends('layouts.vertical', ['title' => 'Movimentazioni Interne'])

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <iconify-icon icon="solar:transfer-horizontal-bold" class="me-2"></iconify-icon>
                        Movimentazioni Interne
                    </h1>
                    <p class="text-muted mb-0">Gestisci trasferimenti articoli tra sedi</p>
                </div>
                <div>
                    <a class="btn btn-outline-primary" href="{{ route('movimentazioni-interne.elenco') }}">
                        <iconify-icon icon="solar:list-bold" class="me-1"></iconify-icon>
                        Elenco movimentazioni
                    </a>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Movimentazioni</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:danger-triangle-bold" class="me-1"></iconify-icon>
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Componente Livewire -->
    <div class="row">
        <div class="col-12">
            @livewire('movimentazione-interna-new')
        </div>
    </div>
</div>
@endsection
