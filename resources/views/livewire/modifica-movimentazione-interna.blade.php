<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <iconify-icon icon="solar:transfer-horizontal-bold" class="me-2"></iconify-icon>
                        Modifica Movimentazione
                    </h1>
                    <p class="text-muted mb-0">Aggiorna dati DDT movimentazione interna</p>
                </div>
                <div>
                    <a class="btn btn-outline-secondary" href="{{ route('movimentazioni-interne.elenco') }}">
                        <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                        Torna all'elenco
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <iconify-icon icon="solar:document-text-bold" class="me-1"></iconify-icon>
                Dati movimentazione
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Data Movimentazione *</label>
                    <input type="date" wire:model="dataMovimentazione" class="form-control">
                    @error('dataMovimentazione') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9">
                    <label class="form-label">Note</label>
                    <input type="text" wire:model="noteMovimentazione" class="form-control" placeholder="Note opzionali...">
                    @error('noteMovimentazione') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trasporto a mezzo</label>
                    <input type="text" wire:model="trasportoMezzo" class="form-control">
                    @error('trasportoMezzo') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Aspetto beni</label>
                    <input type="text" wire:model="aspettoBeni" class="form-control">
                    @error('aspettoBeni') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Colli</label>
                    <input type="text" wire:model="colli" class="form-control">
                    @error('colli') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vettore</label>
                    <input type="text" wire:model="vettore" class="form-control">
                    @error('vettore') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary" wire:click="salvaModifiche">
                    <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>
                    Salva modifiche
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <iconify-icon icon="solar:list-bold" class="me-1"></iconify-icon>
                Articoli movimentati
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th class="text-center">Q.tà</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movimentazione->dettagli as $dettaglio)
                            <tr>
                                <td><strong>{{ $dettaglio->articolo->codice ?? 'N/D' }}</strong></td>
                                <td>{{ $dettaglio->articolo->descrizione ?? 'N/D' }}</td>
                                <td class="text-center">{{ $dettaglio->quantita }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-muted small">
                Modifica articoli disabilitata in questa schermata (test: solo dati DDT).
            </div>
        </div>
    </div>
</div>
