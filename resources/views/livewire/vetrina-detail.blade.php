<div class="vetrina-detail">
    {{-- Header --}}
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="page-title">
                        <i class="bx bx-store me-2"></i>
                        {{ $vetrina->nome }}
                    </h4>
                    <div class="text-muted">
                        <span class="badge bg-light-info text-info me-2">{{ $vetrina->codice }}</span>
                        <span class="badge bg-light-secondary text-secondary me-2">{{ $vetrina->getTipologiaLabel() }}</span>
                        @if($vetrina->sede)
                            <span class="badge bg-light-primary text-primary me-2">{{ $vetrina->sede->nome }}</span>
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

    {{-- Flash Messages --}}
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

    {{-- Statistiche Vetrina --}}
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
                            <th></th>
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
                    <tbody class="js-sortable">
                        @forelse($articoliInVetrina as $articoloVetrina)
                            <tr data-id="{{ $articoloVetrina->id }}">
                                <td class="text-center">
                                    <span class="text-muted drag-handle" style="cursor: grab;">
                                        <i class="bx bx-grid-alt"></i>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light-primary text-primary">{{ $articoloVetrina->posizione ?: '-' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                @php
                                    $fotoPrincipale = $articoloVetrina->foto_display;
                                    $fotoUrl = null;
                                    if (!empty($fotoPrincipale)) {
                                        $fotoUrl = Str::startsWith($fotoPrincipale, ['http://', 'https://'])
                                            ? $fotoPrincipale
                                            : asset('storage/' . ltrim($fotoPrincipale, '/'));
                                    }
                                @endphp
                                        @if($fotoUrl)
                                            <img src="{{ $fotoUrl }}"
                                                 alt="Foto {{ $articoloVetrina->codice_display }}"
                                                 class="rounded border"
                                                 style="width: 36px; height: 36px; object-fit: cover; cursor: pointer;"
                                                 data-bs-toggle="modal"
                                                 data-bs-target="#vetrinaFotoModal"
                                                 data-foto-url="{{ $fotoUrl }}"
                                                 data-foto-alt="Foto {{ $articoloVetrina->codice_display }}">
                                        @endif
                                        <div>
                                            <span class="fw-bold text-primary">{{ $articoloVetrina->codice_display }}</span>
                                            @if($articoloVetrina->is_esterno)
                                                <span class="badge bg-light-warning text-warning ms-1">NC</span>
                                            @elseif($articoloVetrina->is_prodotto_finito)
                                                <span class="badge bg-light-info text-info ms-1">PF</span>
                                            @endif
                                            <br>
                                            <small class="text-muted">{{ $articoloVetrina->categoria_display }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ Str::limit($articoloVetrina->descrizione_display, 40) }}</div>
                                    <small class="text-muted">{{ $articoloVetrina->sede_display }}</small>
                                    @php
                                        $pfId = $articoloVetrina->prodotto_finito_id
                                            ?? $articoloVetrina->articolo?->prodotto_finito_id;
                                        $pfComponenti = $pfId
                                            ? ($componentiByPfId[$pfId] ?? collect())
                                            : collect();
                                        $componenti = $pfComponenti
                                            ->map(function ($c) {
                                                return $c->articolo_codice
                                                    ?: $c->articolo_descrizione
                                                    ?: ('ID ' . $c->articolo_id);
                                            })
                                            ->filter()
                                            ->take(6)
                                            ->implode(', ');
                                    @endphp
                                    @if($pfId)
                                        <div class="mt-2">
                                            <span class="badge bg-light-info text-info">Componenti</span>
                                            <span class="ms-1 fw-semibold text-dark">{{ $componenti ?: 'N/D' }}</span>
                                        </div>
                                    @endif
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
                                    @if($articoloVetrina->giorni_in_vetrina !== null)
                                        <span class="badge bg-primary text-white px-2 py-1" title="Giorni trascorsi in vetrina">
                                            {{ $articoloVetrina->giorni_in_vetrina }} gg
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-light btn-sm"
                                                wire:click="openEditModal({{ $articoloVetrina->id }})"
                                                title="Modifica articolo in vetrina">
                                            <i class="bx bx-pencil text-primary"></i>
                                        </button>
                                        <button class="btn btn-light btn-sm" 
                                                wire:click="openMoveModal({{ $articoloVetrina->id }})"
                                                title="Sposta in altra vetrina">
                                            <i class="bx bx-transfer-alt text-warning"></i>
                                        </button>
                                        <button class="btn btn-light btn-sm" 
                                                wire:click="removeArticoloFromVetrina({{ $articoloVetrina->id }})"
                                                title="Rimuovi da vetrina"
                                                onclick="return confirm('Rimuovere l\'articolo dalla vetrina?')">
                                            <i class="bx bx-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <iconify-icon icon="solar:box-bold" class="fs-48 text-muted mb-3"></iconify-icon>
                                    <p class="text-muted mb-0">Nessun articolo in vetrina</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginazione --}}
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Mostrando {{ $articoliInVetrina->firstItem() ?? 0 }} - {{ $articoliInVetrina->lastItem() ?? 0 }} 
                    di {{ $articoliInVetrina->total() }} articoli
                </div>
                {{ $articoliInVetrina->links() }}
            </div>
        </div>
    </div>

    {{-- Modal Aggiunta Articolo --}}
    @if($showAddModal)
        <div class="vetrina-detail__modal-layer">
            <div class="modal fade show d-block" style="z-index: 1055;" tabindex="-1" role="dialog" aria-modal="true">
                <div class="modal-backdrop fade show" style="z-index: 1040; pointer-events: none;"></div>
                <div class="modal-dialog modal-lg modal-dialog-centered" style="z-index: 1056;">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="me-2"></iconify-icon>
                            Aggiungi Articolo alla Vetrina
                        </h5>
                        <button type="button" wire:click="closeAddModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="btn-group" role="group">
                                <button type="button"
                                        class="btn btn-outline-primary @if($addMode === 'interno') active @endif"
                                        wire:click="setAddMode('interno')">
                                    Magazzino
                                </button>
                                <button type="button"
                                        class="btn btn-outline-secondary @if($addMode === 'esterno') active @endif"
                                        wire:click="setAddMode('esterno')">
                                    Articolo NC
                                </button>
                            </div>
                        </div>

                        @if($addMode === 'interno' && !$selectedArticolo)
                            {{-- Selezione Articolo --}}
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
                                        @forelse($articoliDisponibili as $item)
                                            <tr>
                                                <td><span class="badge bg-light-success text-success">Articolo</span></td>
                                                <td>
                                                    <span class="fw-bold text-primary">{{ $item->codice }}</span>
                                                    @if(!empty($item->vetrina_corrente_id) && (int) $item->vetrina_corrente_id !== (int) $vetrina->id)
                                                        <div class="small text-warning">
                                                            In vetrina: {{ $item->vetrina_corrente_nome ?? $item->vetrina_corrente_id }}
                                                        </div>
                                                    @endif
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
                        @elseif($addMode === 'interno')
                            {{-- Form Dettagli Vetrina --}}
                            <div class="alert alert-info">
                                <strong>Articolo Selezionato:</strong> {{ $selectedArticolo->codice ?? '' }} - {{ $selectedArticolo->descrizione ?? '' }}
                                @if($selectedArticoloVetrina && $selectedArticoloVetrina->vetrina_id !== $vetrina->id)
                                    <div class="small text-warning mt-2">
                                        Attenzione: l'articolo è già in vetrina
                                        <strong>{{ $selectedArticoloVetrina->vetrina->nome ?? $selectedArticoloVetrina->vetrina_id }}</strong>.
                                        Se confermi, verrà rimosso dalla vetrina attuale e aggiunto qui.
                                    </div>
                                @endif
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
                        @elseif($addMode === 'esterno')
                            <form wire:submit.prevent="addArticoloToVetrina">
                                <div class="alert alert-warning">
                                    <strong>Articolo NC:</strong> inserisci i dati manualmente (senza codice/QR).
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Descrizione *</label>
                                            <input type="text"
                                                   class="form-control @error('descrizione_esterno') is-invalid @enderror"
                                                   wire:model="descrizione_esterno"
                                                   placeholder="Descrizione articolo NC">
                                            @error('descrizione_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Categoria</label>
                                            <select class="form-select @error('categoria_merceologica_id_esterno') is-invalid @enderror"
                                                    wire:model="categoria_merceologica_id_esterno">
                                                <option value="">Seleziona...</option>
                                                @foreach($categorieDisponibili as $categoria)
                                                    <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                                                @endforeach
                                            </select>
                                            @error('categoria_merceologica_id_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Sede</label>
                                            <select class="form-select @error('sede_id_esterno') is-invalid @enderror"
                                                    wire:model="sede_id_esterno">
                                                <option value="">Seleziona...</option>
                                                @foreach($sediDisponibili as $sede)
                                                    <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                                @endforeach
                                            </select>
                                            @error('sede_id_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Materiale</label>
                                            <input type="text"
                                                   class="form-control @error('materiale_esterno') is-invalid @enderror"
                                                   wire:model="materiale_esterno"
                                                   placeholder="Es: Oro, Argento">
                                            @error('materiale_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Titolo</label>
                                            <input type="text"
                                                   class="form-control @error('titolo_esterno') is-invalid @enderror"
                                                   wire:model="titolo_esterno"
                                                   placeholder="Es: 750">
                                            @error('titolo_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Caratura</label>
                                            <input type="text"
                                                   class="form-control @error('caratura_esterno') is-invalid @enderror"
                                                   wire:model="caratura_esterno"
                                                   placeholder="Es: 1.25">
                                            @error('caratura_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Colore</label>
                                            <input type="text"
                                                   class="form-control @error('colore_esterno') is-invalid @enderror"
                                                   wire:model="colore_esterno"
                                                   placeholder="Es: Bianco">
                                            @error('colore_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Peso Lordo (g)</label>
                                            <input type="number"
                                                   step="0.01"
                                                   class="form-control @error('peso_lordo_esterno') is-invalid @enderror"
                                                   wire:model="peso_lordo_esterno"
                                                   placeholder="0.00">
                                            @error('peso_lordo_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Peso Netto (g)</label>
                                            <input type="number"
                                                   step="0.01"
                                                   class="form-control @error('peso_netto_esterno') is-invalid @enderror"
                                                   wire:model="peso_netto_esterno"
                                                   placeholder="0.00">
                                            @error('peso_netto_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Prezzo Acquisto</label>
                                            <input type="number"
                                                   step="0.01"
                                                   class="form-control @error('prezzo_acquisto_esterno') is-invalid @enderror"
                                                   wire:model="prezzo_acquisto_esterno"
                                                   placeholder="0.00">
                                            @error('prezzo_acquisto_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Prezzo Fornitore</label>
                                            <input type="number"
                                                   step="0.01"
                                                   class="form-control @error('prezzo_fornitore_esterno') is-invalid @enderror"
                                                   wire:model="prezzo_fornitore_esterno"
                                                   placeholder="0.00">
                                            @error('prezzo_fornitore_esterno')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Foto Principale (URL o path)</label>
                                    <input type="text"
                                           class="form-control @error('foto_principale_esterno') is-invalid @enderror"
                                           wire:model="foto_principale_esterno"
                                           placeholder="https://... o percorso storage">
                                    @error('foto_principale_esterno')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Note</label>
                                    <textarea class="form-control @error('note_esterno') is-invalid @enderror"
                                              wire:model="note_esterno"
                                              rows="2"></textarea>
                                    @error('note_esterno')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <hr class="my-3">

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
                        @if($addMode === 'interno' && $selectedArticolo)
                            <button type="button" class="btn btn-primary" wire:click="addArticoloToVetrina">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                                Aggiungi alla Vetrina
                            </button>
                        @elseif($addMode === 'esterno')
                            <button type="button" class="btn btn-primary" wire:click="addArticoloToVetrina">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                                Aggiungi Articolo NC
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Modifica Articolo --}}
    @if($showEditModal && $editingArticoloVetrina)
        <div class="vetrina-detail__modal-layer">
            <div class="modal fade show d-block" style="z-index: 1055;" tabindex="-1" role="dialog" aria-modal="true">
                <div class="modal-backdrop fade show" style="z-index: 1040; pointer-events: none;"></div>
                <div class="modal-dialog modal-lg modal-dialog-centered" style="z-index: 1056;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <iconify-icon icon="solar:pen-bold-duotone" class="me-2"></iconify-icon>
                                Modifica Articolo in Vetrina
                            </h5>
                            <button type="button" wire:click="closeEditModal" class="btn-close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>Articolo:</strong> {{ $editingArticoloVetrina->codice_display }} - {{ $editingArticoloVetrina->descrizione_display }}
                                @if($editingArticoloVetrina->is_esterno)
                                    <span class="badge bg-light-warning text-warning ms-2">NC</span>
                                @elseif($editingArticoloVetrina->is_prodotto_finito)
                                    <span class="badge bg-light-info text-info ms-2">PF</span>
                                @endif
                            </div>

                            <form wire:submit.prevent="updateArticoloVetrina">
                                @if($editingArticoloVetrina->is_esterno)
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Descrizione *</label>
                                                <input type="text"
                                                       class="form-control @error('edit_descrizione_esterno') is-invalid @enderror"
                                                       wire:model="edit_descrizione_esterno">
                                                @error('edit_descrizione_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Categoria</label>
                                                <select class="form-select @error('edit_categoria_merceologica_id_esterno') is-invalid @enderror"
                                                        wire:model="edit_categoria_merceologica_id_esterno">
                                                    <option value="">Seleziona...</option>
                                                    @foreach($categorieDisponibili as $categoria)
                                                        <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                                                    @endforeach
                                                </select>
                                                @error('edit_categoria_merceologica_id_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Sede</label>
                                                <select class="form-select @error('edit_sede_id_esterno') is-invalid @enderror"
                                                        wire:model="edit_sede_id_esterno">
                                                    <option value="">Seleziona...</option>
                                                    @foreach($sediDisponibili as $sede)
                                                        <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                                    @endforeach
                                                </select>
                                                @error('edit_sede_id_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Materiale</label>
                                                <input type="text"
                                                       class="form-control @error('edit_materiale_esterno') is-invalid @enderror"
                                                       wire:model="edit_materiale_esterno">
                                                @error('edit_materiale_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Titolo</label>
                                                <input type="text"
                                                       class="form-control @error('edit_titolo_esterno') is-invalid @enderror"
                                                       wire:model="edit_titolo_esterno">
                                                @error('edit_titolo_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Caratura</label>
                                                <input type="text"
                                                       class="form-control @error('edit_caratura_esterno') is-invalid @enderror"
                                                       wire:model="edit_caratura_esterno">
                                                @error('edit_caratura_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Colore</label>
                                                <input type="text"
                                                       class="form-control @error('edit_colore_esterno') is-invalid @enderror"
                                                       wire:model="edit_colore_esterno">
                                                @error('edit_colore_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Peso Lordo (g)</label>
                                                <input type="number"
                                                       step="0.01"
                                                       class="form-control @error('edit_peso_lordo_esterno') is-invalid @enderror"
                                                       wire:model="edit_peso_lordo_esterno">
                                                @error('edit_peso_lordo_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Peso Netto (g)</label>
                                                <input type="number"
                                                       step="0.01"
                                                       class="form-control @error('edit_peso_netto_esterno') is-invalid @enderror"
                                                       wire:model="edit_peso_netto_esterno">
                                                @error('edit_peso_netto_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Prezzo Acquisto</label>
                                                <input type="number"
                                                       step="0.01"
                                                       class="form-control @error('edit_prezzo_acquisto_esterno') is-invalid @enderror"
                                                       wire:model="edit_prezzo_acquisto_esterno">
                                                @error('edit_prezzo_acquisto_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Prezzo Fornitore</label>
                                                <input type="number"
                                                       step="0.01"
                                                       class="form-control @error('edit_prezzo_fornitore_esterno') is-invalid @enderror"
                                                       wire:model="edit_prezzo_fornitore_esterno">
                                                @error('edit_prezzo_fornitore_esterno')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Foto Principale (URL o path)</label>
                                        <input type="text"
                                               class="form-control @error('edit_foto_principale_esterno') is-invalid @enderror"
                                               wire:model="edit_foto_principale_esterno">
                                        @error('edit_foto_principale_esterno')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Note</label>
                                        <textarea class="form-control @error('edit_note_esterno') is-invalid @enderror"
                                                  wire:model="edit_note_esterno"
                                                  rows="2"></textarea>
                                        @error('edit_note_esterno')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <hr class="my-3">
                                @endif

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Prezzo Vetrina *</label>
                                            <input type="text"
                                                   class="form-control @error('edit_prezzo_vetrina') is-invalid @enderror"
                                                   wire:model="edit_prezzo_vetrina">
                                            @error('edit_prezzo_vetrina')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Posizione</label>
                                            <input type="number"
                                                   min="0"
                                                   class="form-control @error('edit_posizione') is-invalid @enderror"
                                                   wire:model="edit_posizione">
                                            @error('edit_posizione')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ripiano</label>
                                            <input type="text"
                                                   class="form-control @error('edit_ripiano') is-invalid @enderror"
                                                   wire:model="edit_ripiano">
                                            @error('edit_ripiano')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label fw-semibold">Testo Vetrina *</label>
                                    <textarea class="form-control @error('edit_testo_vetrina') is-invalid @enderror"
                                              wire:model="edit_testo_vetrina"
                                              rows="3"></textarea>
                                    @error('edit_testo_vetrina')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeEditModal">
                                <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                                Annulla
                            </button>
                            <button type="button" class="btn btn-primary" wire:click="updateArticoloVetrina">
                                <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>
                                Salva Modifiche
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Spostamento Articolo --}}
    @if($showMoveModal && $articoloToMove)
        <div class="vetrina-detail__modal-layer">
            <div class="modal fade show d-block" style="z-index: 1055;" tabindex="-1" role="dialog" aria-modal="true">
                <div class="modal-backdrop fade show" style="z-index: 1040; pointer-events: none;"></div>
                <div class="modal-dialog modal-dialog-centered" style="z-index: 1056;">
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
                            <strong>Articolo:</strong> {{ $articoloToMove->codice_display }} - {{ $articoloToMove->descrizione_display }}
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
    @endif

    @once
        <div class="modal fade" id="vetrinaFotoModal" tabindex="-1" aria-labelledby="vetrinaFotoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="vetrinaFotoModalLabel">Foto articolo in vetrina</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="vetrinaFotoModalImg" src="" alt="" class="img-fluid rounded">
                    </div>
                </div>
            </div>
        </div>
    @endonce
</div>

@push('scripts')
    <script>
        function initVetrinaPhotoModal() {
            const modalEl = document.getElementById('vetrinaFotoModal');
            if (!modalEl || modalEl.dataset.inited === '1') {
                return;
            }

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }

                const img = modalEl.querySelector('#vetrinaFotoModalImg');
                if (!img) {
                    return;
                }

                const fotoUrl = trigger.getAttribute('data-foto-url') || '';
                const fotoAlt = trigger.getAttribute('data-foto-alt') || 'Foto articolo';
                img.src = fotoUrl;
                img.alt = fotoAlt;
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                const img = modalEl.querySelector('#vetrinaFotoModalImg');
                if (img) {
                    img.src = '';
                    img.alt = '';
                }
            });

            modalEl.dataset.inited = '1';
        }

        function ensureSortableReady(callback) {
            if (window.Sortable) {
                callback();
                return;
            }
            const existing = document.querySelector('script[data-sortable]');
            if (existing) {
                existing.addEventListener('load', callback, { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
            script.async = true;
            script.setAttribute('data-sortable', '1');
            script.addEventListener('load', callback, { once: true });
            document.head.appendChild(script);
        }

        function initVetrinaSortable() {
            const el = document.querySelector('.js-sortable');
            if (!el || !window.Sortable) return;

            if (el._sortable) {
                el._sortable.destroy();
            }

            el._sortable = new window.Sortable(el, {
                handle: '.drag-handle',
                animation: 150,
                draggable: 'tr[data-id]',
                onEnd: function () {
                    const orderedIds = Array.from(el.querySelectorAll('tr[data-id]'))
                        .map(row => row.getAttribute('data-id'));
                    if (orderedIds.length) {
                        @this.call('updateOrdine', orderedIds);
                    }
                }
            });
        }

        const setupVetrinaSortable = () => ensureSortableReady(initVetrinaSortable);

        document.addEventListener('livewire:init', function () {
            initVetrinaPhotoModal();
            setupVetrinaSortable();
            if (window.Livewire && typeof Livewire.hook === 'function') {
                Livewire.hook('message.processed', function () {
                    initVetrinaPhotoModal();
                    setupVetrinaSortable();
                });
            }
        });

        document.addEventListener('livewire:navigated', function () {
            initVetrinaPhotoModal();
            setupVetrinaSortable();
        });

        document.addEventListener('livewire:load', function () {
            initVetrinaPhotoModal();
            setupVetrinaSortable();
        });

        document.addEventListener('DOMContentLoaded', function () {
            initVetrinaPhotoModal();
        });
    </script>
@endpush
