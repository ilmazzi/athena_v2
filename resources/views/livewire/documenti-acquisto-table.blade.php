<div>
    <!-- Statistiche -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">Documenti Totali</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['totali']) }}</h4>
                            <small class="text-muted">DDT + Fatture</small>
                        </div>
                        <div class="bg-primary-subtle rounded p-2">
                            <iconify-icon icon="solar:file-text-bold-duotone" class="fs-4 text-primary"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">DDT</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['ddt']) }}</h4>
                            <small class="text-muted">Documenti di trasporto</small>
                        </div>
                        <div class="bg-info-subtle rounded p-2">
                            <iconify-icon icon="solar:delivery-bold-duotone" class="fs-4 text-info"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">Fatture</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['fatture']) }}</h4>
                            <small class="text-muted">Fatture di acquisto</small>
                        </div>
                        <div class="bg-success-subtle rounded p-2">
                            <iconify-icon icon="solar:bill-list-bold-duotone" class="fs-4 text-success"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">OCR</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['ocr']) }}</h4>
                            <small class="text-muted">{{ $stats['manuali'] }} manuali</small>
                        </div>
                        <div class="bg-warning-subtle rounded p-2">
                            <iconify-icon icon="solar:document-text-bold-duotone" class="fs-4 text-warning"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri e Ricerca -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label small fw-semibold">Ricerca</label>
                    <input type="text" 
                           class="form-control" 
                           placeholder="Numero documento, fornitore..." 
                           wire:model.live.debounce.300ms="search">
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Tipo Documento</label>
                    <select class="form-select" wire:model.live="tipoDocumento">
                        <option value="">Tutti</option>
                        <option value="ddt">DDT</option>
                        <option value="fattura">Fatture</option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Tipo Carico</label>
                    <select class="form-select" wire:model.live="tipoCarico">
                        <option value="">Tutti</option>
                        <option value="ocr">OCR</option>
                        <option value="manuale">Manuale</option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Stato</label>
                    <select class="form-select" wire:model.live="statoFilter">
                        <option value="">Tutti</option>
                        <option value="bozza">Bozza</option>
                        <option value="validato">Validato</option>
                        <option value="completato">Completato</option>
                        <option value="annullato">Annullato</option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small fw-semibold">Per Pagina</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Filtri avanzati collassabili -->
            <div class="mt-3">
                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filtriAvanzati">
                    <iconify-icon icon="solar:settings-bold-duotone" class="me-1"></iconify-icon>
                    Filtri Avanzati
                </button>
                <button class="btn btn-sm btn-secondary" wire:click="resetFilters">
                    <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                    Reset
                </button>
            </div>

            <div class="collapse mt-3" id="filtriAvanzati">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="form-label small fw-semibold">Fornitore</label>
                        <select class="form-select form-select-sm" wire:model.live="fornitoreFilter">
                            <option value="">Tutti</option>
                            @foreach($fornitori as $fornitore)
                                <option value="{{ $fornitore->id }}">{{ $fornitore->ragione_sociale }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Magazzino</label>
                        <select class="form-select form-select-sm" wire:model.live="categoriaFilter">
                            <option value="">Tutte</option>
                            @foreach($categorie as $categoria)
                                <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Sede</label>
                        <select class="form-select form-select-sm" wire:model.live="sedeFilter">
                            <option value="">Tutte</option>
                            @foreach($sedi as $sede)
                                <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Data Da</label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="dataFrom">
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small fw-semibold">Data A</label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="dataTo">
                    </div>

                    <div class="col-lg-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model.live="nascondiVuoti" id="nascondiVuoti">
                            <label class="form-check-label small" for="nascondiVuoti">
                                Nascondi documenti senza articoli
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabella Documenti -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <iconify-icon icon="solar:file-text-bold-duotone" class="text-primary me-2"></iconify-icon>
                Elenco Documenti
            </h5>
            <a href="{{ route('documenti-acquisto.nuovo') }}" class="btn btn-primary btn-sm">
                <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                Nuovo Documento
            </a>
        </div>

        <div class="card-body p-0">
            @if($documenti->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-centered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="cursor: pointer;" wire:click="sortBy('id')" width="80">
                                    <div class="d-flex align-items-center gap-1">
                                        ID
                                        @if($sortField === 'id')
                                            <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}-bold"></iconify-icon>
                                        @endif
                                    </div>
                                </th>
                                <th>Tipo Carico</th>
                                <th style="cursor: pointer;" wire:click="sortBy('numero')">
                                    <div class="d-flex align-items-center gap-1">
                                        Documento
                                        @if($sortField === 'numero')
                                            <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}-bold"></iconify-icon>
                                        @endif
                                    </div>
                                </th>
                                <th style="cursor: pointer;" wire:click="sortBy('data_documento')">
                                    <div class="d-flex align-items-center gap-1">
                                        Data
                                        @if($sortField === 'data_documento')
                                            <iconify-icon icon="solar:{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}-bold"></iconify-icon>
                                        @endif
                                    </div>
                                </th>
                                <th>Fornitore</th>
                                <th>Sede</th>
                                <th>Magazzino</th>
                                <th>Art.</th>
                                <th>Pz.</th>
                                <th>Stato</th>
                                <th>Utente</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documenti as $doc)
                                <tr>
                                    <td>
                                        <strong>#{{ $doc->id }}</strong>
                                    </td>
                                    <td>
                                        @if($doc->tipo_carico === 'manuale')
                                            <span class="badge bg-primary">
                                                <iconify-icon icon="solar:pen-bold"></iconify-icon> Manuale
                                            </span>
                                        @else
                                            <span class="badge bg-success">
                                                <iconify-icon icon="solar:document-text-bold"></iconify-icon> OCR
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $doc->numero }}</strong>
                                        </div>
                                        <small class="text-muted">
                                            {{ strtoupper($doc->tipo_documento) }}
                                        </small>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($doc->data_documento)->format('d/m/Y') }}
                                    </td>
                                    <td>
                                        {{ $doc->fornitore->ragione_sociale ?? '-' }}
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $doc->sede->nome ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $doc->magazzino_logico ? 'Magazzino ' . $doc->magazzino_logico : '-' }}</small>
                                    </td>
                                    <td>
                                        @if($doc->numero_articoli > 0)
                                            <span class="badge bg-secondary">{{ $doc->numero_articoli }}</span>
                                        @else
                                            <span class="badge bg-light text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($doc->quantita_totale > 0)
                                            <span class="badge bg-info">{{ $doc->quantita_totale }}</span>
                                        @else
                                            <span class="badge bg-light text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @switch($doc->stato)
                                            @case('bozza')
                                                <span class="badge bg-warning">Bozza</span>
                                                @break
                                            @case('validato')
                                                <span class="badge bg-primary">Validato</span>
                                                @break
                                            @case('completato')
                                                <span class="badge bg-success">Completato</span>
                                                @break
                                            @case('annullato')
                                                <span class="badge bg-danger">Annullato</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $doc->userCarico->name ?? '-' }}
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-light" 
                                                    wire:click="editDocument('{{ $doc->tipo_documento }}', {{ $doc->id }})"
                                                    title="Modifica Documento">
                                                <iconify-icon icon="solar:pen-bold-duotone" class="text-primary"></iconify-icon>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-light"
                                                    wire:click="openPrintModal('{{ $doc->tipo_documento }}', {{ $doc->id }})"
                                                    title="Ristampa etichette">
                                                <iconify-icon icon="solar:printer-bold-duotone" class="text-success"></iconify-icon>
                                            </button>
                                            @if($doc->tipo_carico === 'ocr' && $doc->ocrDocument)
                                                <a href="{{ route('ocr.documents.pdf', $doc->ocrDocument) }}" 
                                                   class="btn btn-light" 
                                                   target="_blank"
                                                   title="Vedi PDF">
                                                    <iconify-icon icon="solar:file-text-bold-duotone" class="text-danger"></iconify-icon>
                                                </a>
                                            @elseif(!empty($doc->allegato_path))
                                                <a href="{{ $doc->tipo_documento === 'fattura'
                                                        ? route('documenti-acquisto.fattura.pdf', $doc)
                                                        : route('documenti-acquisto.ddt.pdf', $doc) }}"
                                                   class="btn btn-light"
                                                   target="_blank"
                                                   title="Vedi PDF">
                                                    <iconify-icon icon="solar:file-text-bold-duotone" class="text-danger"></iconify-icon>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <iconify-icon icon="solar:inbox-bold-duotone" class="fs-1 text-muted mb-3 d-block"></iconify-icon>
                    <h5 class="text-muted">Nessun documento trovato</h5>
                    <p class="text-muted mb-3">Inizia creando il tuo primo documento di acquisto</p>
                    <a href="{{ route('documenti-acquisto.nuovo') }}" class="btn btn-primary">
                        <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                        Nuovo Documento
                    </a>
                </div>
            @endif
        </div>

        <!-- Paginazione -->
        @if($documenti->hasPages())
            <div class="card-footer border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-0 small">
                            <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                            Mostrando <strong>{{ $documenti->firstItem() }}</strong> - <strong>{{ $documenti->lastItem() }}</strong> 
                            di <strong>{{ number_format($documenti->total()) }}</strong> documenti
                        </p>
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $documenti->links() }}
                    </nav>
                </div>
            </div>
        @endif
    </div>

    @if($showPrintModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.6);">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ristampa Etichette</h5>
                        <button type="button" class="btn-close" wire:click="closePrintModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Stampante</label>
                                <select class="form-select form-select-sm" wire:model="stampanteId">
                                    <option value="">Automatica</option>
                                    @foreach($stampantiDisponibili as $stampante)
                                        <option value="{{ $stampante->id }}">
                                            {{ $stampante->nome }} ({{ $stampante->modello }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Codice prezzo automatico</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">X</span>
                                    <select class="form-select" wire:model="codicePrezzoTipo">
                                        <option value="G">G</option>
                                        <option value="P">P</option>
                                    </select>
                                    <input type="text" class="form-control" wire:model="codicePrezzoSuffix" placeholder="N (es. 3.5)">
                                    <button class="btn btn-outline-primary" type="button" wire:click="applicaCodicePrezzoTutti">
                                        Applica a tutte
                                    </button>
                                </div>
                                <small class="text-muted">Formato: X + costo unitario + G/P + N</small>
                            </div>
                            <div class="col-md-2 text-md-end">
                                <div class="small text-muted">Etichette da stampare</div>
                                <div class="fw-semibold">{{ $etichetteTotali }}</div>
                                <button class="btn btn-link btn-sm p-0" type="button" wire:click="ricalcolaEtichetteTotali">
                                    Ricalcola
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="180">Referenza</th>
                                        <th width="160">Codice</th>
                                        <th>Descrizione</th>
                                        <th width="90" class="text-center">Qta</th>
                                        <th width="140" class="text-end">Costo Unit.</th>
                                        <th width="180">Prezzo Etichetta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($printRows as $index => $row)
                                        <tr>
                                            <td><strong>{{ $row['referenza'] }}</strong></td>
                                            <td><strong>{{ $row['codice'] }}</strong></td>
                                            <td>{{ $row['descrizione'] }}</td>
                                            <td class="text-center">{{ $row['quantita'] }}</td>
                                            <td class="text-end">
                                                {{ is_null($row['prezzo_unitario']) ? '-' : number_format((float) $row['prezzo_unitario'], 2, ',', '.') }}
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="text"
                                                           class="form-control"
                                                           wire:model.defer="printRows.{{ $index }}.prezzo_etichetta"
                                                           placeholder="Prezzo etichetta">
                                                    <button class="btn btn-outline-secondary" type="button" wire:click="applicaCodicePrezzoRiga({{ $index }})">
                                                        Auto
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">Nessun articolo nel documento</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Verranno stampate solo le righe con prezzo etichetta compilato.</small>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" wire:click="closePrintModal">Chiudi</button>
                        <button class="btn btn-success" type="button" wire:click="stampaEtichetteDocumento">
                            Stampa Etichette
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Modifica Documento -->
    <div class="modal fade" id="editModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="updateDocument">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:pen-bold-duotone" class="text-primary me-2"></iconify-icon>
                            Modifica Documento #{{ $editingDocId }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <iconify-icon icon="solar:info-circle-bold-duotone" class="me-2"></iconify-icon>
                            Puoi modificare solo i dati del documento. Per modificare gli articoli, vai nella sezione Magazzino.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo Documento</label>
                            <input type="text" class="form-control" value="{{ strtoupper($editingDocTipo ?? '') }}" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Numero Documento *</label>
                            <input type="text" wire:model="editForm.numero_documento" class="form-control @error('editForm.numero_documento') is-invalid @enderror" required>
                            @error('editForm.numero_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data Documento *</label>
                            <input type="date" wire:model="editForm.data_documento" class="form-control @error('editForm.data_documento') is-invalid @enderror" required>
                            @error('editForm.data_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Fornitore</label>
                            <select wire:model="editForm.fornitore_id" class="form-select @error('editForm.fornitore_id') is-invalid @enderror">
                                <option value="">Nessuno</option>
                                @foreach($fornitori as $fornitore)
                                    <option value="{{ $fornitore->id }}">{{ $fornitore->ragione_sociale }}</option>
                                @endforeach
                            </select>
                            @error('editForm.fornitore_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        @if($editingDocTipo === 'fattura')
                        <div class="mb-3">
                            <label class="form-label">Partita IVA</label>
                            <input type="text" wire:model="editForm.partita_iva" class="form-control @error('editForm.partita_iva') is-invalid @enderror">
                            @error('editForm.partita_iva') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Importo Totale</label>
                            <input type="number" step="0.01" wire:model="editForm.importo_totale" class="form-control @error('editForm.importo_totale') is-invalid @enderror">
                            @error('editForm.importo_totale') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @endif
                        
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea wire:model="editForm.note" class="form-control @error('editForm.note') is-invalid @enderror" rows="3"></textarea>
                            @error('editForm.note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <iconify-icon icon="solar:diskette-bold-duotone" class="me-1"></iconify-icon>
                            Salva Modifiche
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('open-edit-modal', () => {
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });
        
        Livewire.on('close-edit-modal', () => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
            if (modal) {
                modal.hide();
            }
        });
        
        Livewire.on('show-toast', (data) => {
            // Puoi integrare qui un sistema di toast/notifiche
            if (data.type === 'success') {
                alert(data.message);
            } else {
                alert('Errore: ' + data.message);
            }
        });
    });
</script>
@endpush

