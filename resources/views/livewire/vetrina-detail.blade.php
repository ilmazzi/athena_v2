<div>
    <!-- Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="page-title">
                        <iconify-icon icon="solar:shop-bold-duotone" class="me-2"></iconify-icon>
                        {{ $vetrina->nome }}
                    </h4>
                    <div class="text-muted">
                        <span class="badge bg-light-info text-info me-2">{{ $vetrina->codice }}</span>
                        <span class="badge bg-light-secondary text-secondary me-2">{{ $vetrina->getTipologiaLabel() }}</span>
                        @if($vetrina->ubicazione)
                            <span class="badge bg-light-primary text-primary">{{ $vetrina->ubicazione }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <a href="{{ route('vetrine.index') }}" class="btn btn-secondary me-2">
                        <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                        Torna alle Vetrine
                    </a>
                    <button wire:click="openAddModal" class="btn btn-primary">
                        <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                        Aggiungi Articolo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistiche Vetrina -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <iconify-icon icon="solar:box-bold-duotone" class="fs-36 text-primary mb-2"></iconify-icon>
                    <h4 class="mb-1">{{ $articoliInVetrina->total() }}</h4>
                    <p class="text-muted mb-0">Articoli in Vetrina</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <iconify-icon icon="solar:tag-bold-duotone" class="fs-36 text-success mb-2"></iconify-icon>
                    @php
                        $prezziCodificati = $articoliInVetrina->getCollection()
                            ->filter(fn($item) => !empty($item->prezzo_vetrina))
                            ->count();
                    @endphp
                    <h4 class="mb-1">{{ $prezziCodificati }}</h4>
                    <p class="text-muted mb-0">Prezzi Codificati</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <iconify-icon icon="solar:calendar-bold-duotone" class="fs-36 text-warning mb-2"></iconify-icon>
                    <h4 class="mb-1">{{ $articoliInVetrina->avg('giorni_esposizione') ? round($articoliInVetrina->avg('giorni_esposizione')) : 0 }}</h4>
                    <p class="text-muted mb-0">Giorni Medi</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
                <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('vetrine.stampa', $vetrina->id) }}" 
                       target="_blank" 
                       class="btn btn-light-primary w-100 mb-2">
                        <iconify-icon icon="solar:printer-bold" class="me-2"></iconify-icon>
                        Stampa Vetrina
                    </a>
                    <a href="{{ route('vetrine.pdf', $vetrina->id) }}" 
                       class="btn btn-light-secondary w-100">
                        <iconify-icon icon="solar:download-bold" class="me-2"></iconify-icon>
                        Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <iconify-icon icon="solar:list-bold" class="me-2"></iconify-icon>
                Articoli in Vetrina
            </h5>
            <div class="d-flex gap-2">
                <input type="text" 
                       class="form-control" 
                       placeholder="Cerca articoli..." 
                       wire:model.live.debounce.300ms="search"
                       style="width: 250px;">
            </div>
        </div>

        <div class="card-body">
            <!-- Tabella Articoli -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Pos.</th>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Testo Vetrina</th>
                            <th>Prezzo Vetrina</th>
                            <th>Ripiano</th>
                            <th>Giorni</th>
                            <th class="text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($articoliInVetrina as $articoloVetrina)
                            <tr>
                                <td>
                                    <span class="badge bg-light-primary text-primary">{{ $articoloVetrina->posizione ?: '-' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @php
                                            $fotoPrincipale = $articoloVetrina->articolo->foto_principale;
                                            $fotoUrl = null;
                                            if (!empty($fotoPrincipale)) {
                                                $fotoUrl = Str::startsWith($fotoPrincipale, ['http://', 'https://'])
                                                    ? $fotoPrincipale
                                                    : asset('storage/' . ltrim($fotoPrincipale, '/'));
                                            }
                                        @endphp
                                        @if($fotoUrl)
                                            <img src="{{ $fotoUrl }}"
                                                 alt="Foto {{ $articoloVetrina->articolo->codice }}"
                                                 class="rounded border"
                                                 style="width: 36px; height: 36px; object-fit: cover;">
                                        @endif
                                        <div>
                                            <span class="fw-bold text-primary">{{ $articoloVetrina->articolo->codice }}</span>
                                            <br>
                                            <small class="text-muted">{{ $articoloVetrina->articolo->categoriaMerceologica->nome ?? 'N/A' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ Str::limit($articoloVetrina->articolo->descrizione, 40) }}</div>
                                    <small class="text-muted">{{ $articoloVetrina->articolo->sede->nome ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    <div class="text-wrap" style="max-width: 200px;">
                                        {{ Str::limit($articoloVetrina->testo_vetrina, 60) }}
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm" style="width: 140px;">
                                        <input type="text" 
                                               class="form-control" 
                                               value="{{ $articoloVetrina->prezzo_vetrina }}"
                                               wire:change="updatePrezzo({{ $articoloVetrina->id }}, $event.target.value)"
                                               placeholder="Codice prezzo">
                                    </div>
                                </td>
                                <td>{{ $articoloVetrina->ripiano ?: '-' }}</td>
                                <td>
                                    @php
                                        $giorni = \Carbon\Carbon::parse($articoloVetrina->data_inserimento)->diffInDays(now());
                                    @endphp
                                    <span class="badge bg-light-info text-info">{{ $giorni }} gg</span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-light btn-sm" 
                                                wire:click="openMoveModal({{ $articoloVetrina->id }})"
                                                title="Sposta in altra vetrina">
                                            <iconify-icon icon="solar:transfer-horizontal-bold" class="text-warning"></iconify-icon>
                                        </button>
                                        <button class="btn btn-light btn-sm" 
                                                wire:click="removeArticoloFromVetrina({{ $articoloVetrina->id }})"
                                                title="Rimuovi da vetrina"
                                                onclick="return confirm('Rimuovere l\'articolo dalla vetrina?')">
                                            <iconify-icon icon="solar:trash-bin-bold" class="text-danger"></iconify-icon>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <iconify-icon icon="solar:box-bold" class="fs-48 text-muted mb-3"></iconify-icon>
                                    <p class="text-muted mb-0">Nessun articolo in vetrina</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Mostrando {{ $articoliInVetrina->firstItem() ?? 0 }} - {{ $articoliInVetrina->lastItem() ?? 0 }} 
                    di {{ $articoliInVetrina->total() }} articoli
                </div>
                {{ $articoliInVetrina->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Aggiunta Articolo -->
    @if($showAddModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="me-2"></iconify-icon>
                            Aggiungi Articolo alla Vetrina
                        </h5>
                        <button type="button" wire:click="closeAddModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        @if(!$selectedArticolo)
                            <!-- Selezione Articolo -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cerca e Seleziona Articolo</label>
                                <input type="text" 
                                       class="form-control mb-3" 
                                       placeholder="Cerca per codice o descrizione..." 
                                       wire:model.live.debounce.300ms="search">
                            </div>

                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-hover table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Codice</th>
                                            <th>Descrizione</th>
                                            <th>Categoria</th>
                                            <th class="text-center">Azione</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($articoliDisponibili->concat($prodottiFinitiDisponibili) as $item)
                                            <tr>
                                                <td>
                                                    @if($item instanceof \App\Models\Articolo)
                                                        <span class="badge bg-light-success text-success">Articolo</span>
                                                    @else
                                                        <span class="badge bg-light-warning text-warning">PF</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-primary">{{ $item->codice }}</span>
                                                </td>
                                                <td>{{ Str::limit($item->descrizione, 40) }}</td>
                                                <td>
                                                    <span class="badge bg-light-info text-info">
                                                        {{ $item->categoriaMerceologica->nome ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-primary btn-sm" 
                                                            wire:click="selectArticolo({{ $item->id }})">
                                                        <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                                                        Seleziona
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-3">
                                                    <p class="text-muted mb-0">Nessun articolo o prodotto finito disponibile</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <!-- Form Dettagli Vetrina -->
                            <div class="alert alert-info">
                                <strong>Articolo Selezionato:</strong> {{ $selectedArticolo->codice }} - {{ $selectedArticolo->descrizione }}
                            </div>

                            <form wire:submit.prevent="addArticoloToVetrina">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Prezzo Vetrina (codice) *</label>
                                            <input type="text" 
                                                   class="form-control @error('prezzo_vetrina') is-invalid @enderror" 
                                                   wire:model="prezzo_vetrina"
                                                   placeholder="Es: X773G16">
                                            @error('prezzo_vetrina')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Posizione</label>
                                            <input type="number" 
                                                   class="form-control @error('posizione') is-invalid @enderror" 
                                                   wire:model="posizione"
                                                   min="0"
                                                   placeholder="0">
                                            @error('posizione')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ripiano</label>
                                            <input type="text" 
                                                   class="form-control @error('ripiano') is-invalid @enderror" 
                                                   wire:model="ripiano"
                                                   placeholder="Es: Alto, Basso">
                                            @error('ripiano')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Testo Vetrina *</label>
                                    <textarea class="form-control @error('testo_vetrina') is-invalid @enderror" 
                                              wire:model="testo_vetrina" 
                                              rows="3"
                                              placeholder="Descrizione per la vetrina..."></textarea>
                                    @error('testo_vetrina')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </form>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeAddModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        @if($selectedArticolo)
                            <button type="button" class="btn btn-primary" wire:click="addArticoloToVetrina">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                                Aggiungi alla Vetrina
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    <!-- Modal Spostamento Articolo -->
    @if($showMoveModal && $articoloToMove)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:transfer-horizontal-bold-duotone" class="me-2"></iconify-icon>
                            Sposta Articolo
                        </h5>
                        <button type="button" wire:click="closeMoveModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Articolo:</strong> {{ $articoloToMove->articolo->codice }} - {{ $articoloToMove->articolo->descrizione }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Vetrina di Destinazione</label>
                            <select class="form-select @error('targetVetrinaId') is-invalid @enderror" 
                                    wire:model="targetVetrinaId">
                                <option value="">Seleziona vetrina...</option>
                                @foreach($altreVetrine as $vetrina)
                                    <option value="{{ $vetrina->id }}">{{ $vetrina->nome }} ({{ $vetrina->codice }})</option>
                                @endforeach
                            </select>
                            @error('targetVetrinaId')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeMoveModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" class="btn btn-warning" wire:click="moveArticolo">
                            <iconify-icon icon="solar:transfer-horizontal-bold" class="me-1"></iconify-icon>
                            Sposta Articolo
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
