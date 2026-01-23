<div>
    {{-- Messaggi Successo/Errore --}}
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
            {!! session('success') !!}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Modal Segna Fatturata --}}
    @if($showSegnaFatturataModal)
        <div class="modal fade show" style="display: block;" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:check-circle-bold-duotone" class="me-2"></iconify-icon>
                            Segna proforma come fatturata
                        </h5>
                        <button type="button" class="btn-close" wire:click="chiudiSegnaFatturataModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Numero fattura definitiva</label>
                            <input type="text" class="form-control @error('fatturaNumero') is-invalid @enderror" 
                                   wire:model="fatturaNumero" placeholder="Es. FAT-2025-123">
                            @error('fatturaNumero')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Data fattura</label>
                            <input type="date" class="form-control @error('fatturaData') is-invalid @enderror" 
                                   wire:model="fatturaData">
                            @error('fatturaData')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">File PDF fattura *</label>
                            <input type="file" class="form-control @error('fatturaPdf') is-invalid @enderror" 
                                   wire:model="fatturaPdf" accept="application/pdf">
                            <small class="text-muted">Carica il PDF della fattura emessa (max 20 MB).</small>
                            @error('fatturaPdf')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            @if($proformaSelezionataId)
                                @php
                                    $proformaPreview = $deposito->proforme->firstWhere('id', $proformaSelezionataId);
                                @endphp
                                @if($proformaPreview && $proformaPreview->fattura_pdf_url)
                                    <div class="mt-2">
                                        <a href="{{ $proformaPreview->fattura_pdf_url }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon>
                                            Visualizza PDF esistente
                                        </a>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold">Note interne</label>
                            <textarea class="form-control @error('fatturaNote') is-invalid @enderror" 
                                      wire:model="fatturaNote" rows="2" placeholder="Annotazioni utili..."></textarea>
                            @error('fatturaNote')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="chiudiSegnaFatturataModal">
                            Annulla
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="salvaFatturaProforma" wire:loading.attr="disabled" wire:target="salvaFatturaProforma,fatturaPdf">
                            <span wire:loading.remove wire:target="salvaFatturaProforma">Salva e chiudi</span>
                            <span wire:loading wire:target="salvaFatturaProforma">
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Salvataggio...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:danger-circle-bold" class="me-2"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $puoGestireMittente = $this->puoGestireMittente;
        $puoGestireDestinatario = $this->puoGestireDestinatario;
        $puoRinnovare = $this->puoRinnovare;
        $haContenutoDeposito = $articoliInDeposito->isNotEmpty() || $prodottiFinitiInDeposito->isNotEmpty();
        $ddtInvioGenerato = (bool) ($deposito->ddt_invio_id && $deposito->ddtInvio);
    @endphp
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 sticky-top bg-white py-2" style="z-index: 11; top: 64px;">
        <div class="d-flex align-items-center gap-3">
            <div>
                <h4 class="fw-bold mb-0">Deposito {{ $deposito->codice }}</h4>
                <small class="text-muted">{{ $deposito->sedeMittente->nome }} → {{ $deposito->sedeDestinataria->nome }}</small>
            </div>
            <span class="badge bg-light-{{ $deposito->stato_color }} text-{{ $deposito->stato_color }}">{{ $deposito->stato_label }}</span>
            <span class="badge bg-light {{ $deposito->isInScadenza(30) ? 'text-warning' : 'text-success' }}">Scade {{ $deposito->data_scadenza->format('d/m/Y') }} ({{ $deposito->getGiorniRimanenti() }}g)</span>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <div class="d-flex align-items-center gap-2 small">
                <span class="badge bg-success">1. Creato</span>
                <span class="badge {{ $haContenutoDeposito ? 'bg-success' : 'bg-secondary' }}">2. Aggiungi articoli</span>
                <span class="badge {{ $haContenutoDeposito ? 'bg-success' : 'bg-secondary' }}">3. Anteprima</span>
                <span class="badge {{ $ddtInvioGenerato ? 'bg-success' : 'bg-secondary' }}">4. Genera DDT</span>
                <span class="badge {{ $ddtInvioGenerato ? 'bg-success' : 'bg-secondary' }}">5. Invia</span>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('conti-deposito.index') }}" class="btn btn-light">
                    <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon> Lista
                </a>

                @if($puoGestireMittente)
                    @if(!$haContenutoDeposito)
                        <button class="btn btn-primary" wire:click="apriAggiungiArticoliModal">
                            <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon> Aggiungi Articoli
                        </button>
                    @elseif(!$ddtInvioGenerato)
                        <button class="btn btn-outline-primary" wire:click="apriAggiungiArticoliModal">
                            <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon> Aggiungi altri articoli
                        </button>
                        <button class="btn btn-success" wire:click="apriAnteprimaInvioModal">
                            <iconify-icon icon="solar:document-add-bold" class="me-1"></iconify-icon> Compila e genera DDT
                        </button>
                    @else
                        <a href="{{ route('ddt-deposito.show', $deposito->ddt_invio_id) }}" class="btn btn-info" target="_blank" rel="noopener">
                            <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon> Vedi DDT Invio
                        </a>
                    @endif
                @endif

                @if($deposito->ddt_invio_id && $this->puoAnnullareDdtInvio)
                    <button class="btn btn-outline-danger" wire:click="apriAnnullaDdtInvioModal">
                        <iconify-icon icon="solar:trash-bin-minimalistic-bold" class="me-1"></iconify-icon>
                        Annulla DDT Invio
                    </button>
                @endif

                @if($puoGestireDestinatario)
                    <button class="btn btn-success" wire:click="apriVenditaMultiplaModal">
                        <iconify-icon icon="solar:cart-check-bold" class="me-1"></iconify-icon> Vendita Multipla
                    </button>
                    <button class="btn btn-warning" wire:click="apriResoManualeModal">
                        <iconify-icon icon="solar:import-bold" class="me-1"></iconify-icon> Reso Manuale
                    </button>
                    @php $haMovimentiResoDisponibili = $this->anteprimaMovimentiReso->isNotEmpty(); @endphp
                    @if($haMovimentiResoDisponibili || $deposito->isScaduto())
                        <button class="btn btn-warning" wire:click="apriGeneraDdtResoModal">
                            <iconify-icon icon="solar:document-add-bold" class="me-1"></iconify-icon> Genera DDT Reso
                        </button>
                    @endif
                @endif

                @if($puoRinnovare)
                    <button class="btn btn-dark" wire:click="apriRinnovoModal" title="Rinnova per 1 anno">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon> Rinnova 1 anno
                    </button>
                @endif

                @if($deposito->ddt_invio_id && !$puoGestireMittente)
                    <a href="{{ route('ddt-deposito.show', $deposito->ddt_invio_id) }}" class="btn btn-info" target="_blank" rel="noopener">
                        <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon> Vedi DDT Invio
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- (RIMOSSO) pannello azioni duplicato --}}

    {{-- Layout a 2 colonne: contenuti + sidebar --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-9">
            {{-- Articoli in Deposito (spostata qui per riempire lo spazio centrale) --}}
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="card-title mb-0">Articoli in Deposito</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark">
                            Articoli: {{ $this->totaleQuantitaArticoliInDeposito }}
                        </span>
                        <span class="badge bg-light text-dark">
                            PF: {{ $this->totaleProdottiFinitiInDeposito }}
                        </span>
                        <span class="badge bg-primary">
                            Totale: {{ $this->totaleItemsInDeposito }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    @if($articoliInDeposito->count() > 0 || $prodottiFinitiInDeposito->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        @if($puoGestireDestinatario)
                                            <th width="30" title="Seleziona per reso">
                                                <iconify-icon icon="solar:import-bold" class="text-warning small"></iconify-icon>
                                            </th>
                                            <th width="30" title="Seleziona per vendita">
                                                <iconify-icon icon="solar:cart-check-bold" class="text-success small"></iconify-icon>
                                            </th>
                                        @endif
                                        <th>Tipo</th>
                                        <th>Codice</th>
                                        <th>Descrizione</th>
                                        <th>Quantità</th>
                                        <th>Costo Unit.</th>
                                        <th>Costo Tot.</th>
                                        <th class="text-center">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Articoli --}}
                                    @foreach($articoliInDeposito as $articoloData)
                                        <tr>
                                            @if($puoGestireDestinatario)
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:change="toggleArticoloReso({{ $articoloData['articolo']->id }})"
                                                           @if($this->isArticoloSelezionatoReso($articoloData['articolo']->id)) checked @endif>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:click="toggleArticoloVendita({{ $articoloData['articolo']->id }})"
                                                           @if($this->isArticoloSelezionatoVendita($articoloData['articolo']->id)) checked @endif>
                                                </td>
                                            @endif
                                            <td>
                                                <span class="badge bg-light-primary text-primary">Articolo</span>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary">{{ $articoloData['articolo']->codice }}</span>
                                            </td>
                                            <td>{{ Str::limit($articoloData['articolo']->descrizione, 40) }}</td>
                                            <td>{{ $articoloData['quantita'] }}</td>
                                            <td>€{{ number_format($articoloData['costo_unitario'], 2, ',', '.') }}</td>
                                            <td>€{{ number_format($articoloData['costo_unitario'] * $articoloData['quantita'], 2, ',', '.') }}</td>
                                            <td class="text-center">
                                                @if($puoGestireDestinatario)
                                                    <button class="btn btn-success btn-sm" 
                                                            wire:click="apriRegistraVenditaModal('articolo', {{ $articoloData['articolo']->id }})"
                                                            title="Registra vendita">
                                                        <iconify-icon icon="solar:cart-check-bold"></iconify-icon>
                                                    </button>
                                                @endif
                                                @if($puoGestireMittente && !$ddtInvioGenerato)
                                                    <button type="button"
                                                            class="btn btn-outline-danger btn-sm"
                                                            wire:click="rimuoviArticoloDaDeposito({{ $articoloData['articolo']->id }})"
                                                            onclick="return confirm('Rimuovere questo articolo dal deposito?')"
                                                            title="Rimuovi dal deposito">
                                                        <iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach

                                    {{-- Prodotti Finiti --}}
                                    @foreach($prodottiFinitiInDeposito as $pfData)
                                        <tr>
                                            @if($puoGestireDestinatario)
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:change="toggleProdottoFinitoReso({{ $pfData['prodotto_finito']->id }})"
                                                           @if($this->isProdottoFinitoSelezionatoReso($pfData['prodotto_finito']->id)) checked @endif>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:click="toggleProdottoFinitoVendita({{ $pfData['prodotto_finito']->id }})"
                                                           @if($this->isProdottoFinitoSelezionatoVendita($pfData['prodotto_finito']->id)) checked @endif>
                                                </td>
                                            @endif
                                            <td>
                                                <span class="badge bg-light-warning text-warning">PF</span>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary">{{ $pfData['prodotto_finito']->codice }}</span>
                                            </td>
                                            <td>{{ Str::limit($pfData['prodotto_finito']->descrizione, 40) }}</td>
                                            <td>1</td>
                                            <td>€{{ number_format($pfData['costo_unitario'], 2, ',', '.') }}</td>
                                            <td>€{{ number_format($pfData['costo_unitario'], 2, ',', '.') }}</td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    @if($pfData['componenti']->count() > 0)
                                                        <button class="btn btn-outline-info btn-sm" 
                                                                type="button" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#componenti-{{ $pfData['prodotto_finito']->id }}" 
                                                                title="Vedi componenti">
                                                            <iconify-icon icon="solar:list-bold"></iconify-icon>
                                                            <small>{{ $pfData['componenti']->count() }}</small>
                                                        </button>
                                                    @endif
                                                    @if($puoGestireDestinatario)
                                                        <button class="btn btn-success btn-sm" 
                                                                wire:click="apriRegistraVenditaModal('prodotto_finito', {{ $pfData['prodotto_finito']->id }})"
                                                                title="Registra vendita">
                                                            <iconify-icon icon="solar:cart-check-bold"></iconify-icon>
                                                        </button>
                                                    @endif
                                                    @if($puoGestireMittente && !$ddtInvioGenerato)
                                                        <button type="button"
                                                                class="btn btn-outline-danger btn-sm"
                                                                wire:click="rimuoviProdottoFinitoDaDeposito({{ $pfData['prodotto_finito']->id }})"
                                                                onclick="return confirm('Rimuovere questo prodotto finito dal deposito?')"
                                                                title="Rimuovi dal deposito">
                                                            <iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @if($pfData['componenti']->count() > 0)
                                            <tr class="collapse" id="componenti-{{ $pfData['prodotto_finito']->id }}">
                                                <td colspan="8" class="p-0 border-0">
                                                    <div class="bg-light-warning p-3 mx-3 mb-2 rounded">
                                                        <div class="table-responsive">
                                                            <table class="table table-sm mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Codice Articolo</th>
                                                                        <th>Descrizione</th>
                                                                        <th>Categoria</th>
                                                                        <th class="text-center">Q.tà</th>
                                                                        <th class="text-end">Costo Unit.</th>
                                                                        <th class="text-end">Costo Tot.</th>
                                                                        <th class="text-center">Stato</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($pfData['componenti'] as $componente)
                                                                        <tr>
                                                                            <td><span class="fw-bold text-primary">{{ $componente['articolo']->codice }}</span></td>
                                                                            <td>{{ Str::limit($componente['articolo']->descrizione, 30) }}</td>
                                                                            <td><span class="badge bg-light-info text-info">{{ $componente['articolo']->categoriaMerceologica->nome ?? 'N/A' }}</span></td>
                                                                            <td class="text-center">{{ $componente['quantita'] }}</td>
                                                                            <td class="text-end">€{{ number_format($componente['costo_unitario'], 2, ',', '.') }}</td>
                                                                            <td class="text-end">€{{ number_format($componente['costo_totale'], 2, ',', '.') }}</td>
                                                                            <td class="text-center"><span class="badge bg-light-{{ $componente['stato'] === 'utilizzato' ? 'success' : 'secondary' }} text-{{ $componente['stato'] === 'utilizzato' ? 'success' : 'secondary' }}">{{ ucfirst($componente['stato']) }}</span></td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <iconify-icon icon="solar:box-bold" class="fs-1 text-muted mb-2"></iconify-icon>
                            <p class="text-muted mb-0">Nessun articolo nel deposito</p>
                            @if($puoGestireMittente && !$deposito->ddt_invio_id)
                                <button class="btn btn-primary mt-2" wire:click="apriAggiungiArticoliModal">Aggiungi Articoli</button>
                            @elseif($puoGestireDestinatario)
                                <small class="text-muted d-block">In attesa di articoli dal mittente.</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="position-sticky" style="top: 120px;">
            @php
                $sedeMitt = $deposito->sedeMittente; $sedeDest = $deposito->sedeDestinataria;
                $socMitt = $sedeMitt->societa ?? null; $socDest = $sedeDest->societa ?? null;
            @endphp
            <div class="card mb-3">
                <div class="card-header"><h6 class="card-title mb-0">DDT Associati</h6></div>
                <div class="card-body py-2">
                    <div class="d-flex flex-column gap-2">
                        @if($deposito->ddt_invio_id)
                            <a href="{{ route('ddt-deposito.show', $deposito->ddt_invio_id) }}" class="d-flex align-items-center gap-2 text-primary">
                                <iconify-icon icon="solar:export-bold"></iconify-icon>
                                Invio: {{ $deposito->ddtInvio->numero ?? '' }}
                            </a>
                        @endif
                        @foreach($deposito->ddtResi as $ddtReso)
                            <a href="{{ route('ddt-deposito.show', $ddtReso->id) }}" class="d-flex align-items-center gap-2 text-warning">
                                <iconify-icon icon="solar:import-bold"></iconify-icon>
                                Reso: {{ $ddtReso->numero }}
                            </a>
                        @endforeach
                        @if($deposito->ddt_rimando_id)
                            <a href="{{ route('ddt-deposito.show', $deposito->ddt_rimando_id) }}" class="d-flex align-items-center gap-2 text-success">
                                <iconify-icon icon="solar:refresh-bold"></iconify-icon>
                                Rimando: {{ $deposito->ddtRimando->numero ?? '' }}
                            </a>
                        @endif
                        @if(!$deposito->ddt_invio_id && $deposito->ddtResi->count() == 0 && !$deposito->ddt_rimando_id)
                            <span class="text-muted small">Nessun DDT generato</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h6 class="card-title mb-0">Società Mittente</h6></div>
                <div class="card-body py-2">
                    @if($socMitt)
                        <div class="small"><strong>{{ $socMitt->ragione_sociale }}</strong> ({{ $socMitt->codice }})</div>
                        @if($socMitt->partita_iva)
                            <div class="small text-muted">P.IVA: {{ $socMitt->partita_iva }}</div>
                        @endif
                        @if($sedeMitt)
                            <div class="small mt-2">Sede: {{ $sedeMitt->nome }} ({{ $sedeMitt->codice }})</div>
                            <div class="small text-muted">{{ $sedeMitt->indirizzo }} {{ $sedeMitt->cap }} {{ $sedeMitt->citta }} {{ $sedeMitt->provincia }}</div>
                        @endif
                    @else
                        <span class="text-muted small">Dati società non disponibili</span>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Società Destinataria</h6></div>
                <div class="card-body py-2">
                    @if($socDest)
                        <div class="small"><strong>{{ $socDest->ragione_sociale }}</strong> ({{ $socDest->codice }})</div>
                        @if($socDest->partita_iva)
                            <div class="small text-muted">P.IVA: {{ $socDest->partita_iva }}</div>
                        @endif
                        @if($sedeDest)
                            <div class="small mt-2">Sede: {{ $sedeDest->nome }} ({{ $sedeDest->codice }})</div>
                            <div class="small text-muted">{{ $sedeDest->indirizzo }} {{ $sedeDest->cap }} {{ $sedeDest->citta }} {{ $sedeDest->provincia }}</div>
                        @endif
                    @else
                        <span class="text-muted small">Dati società non disponibili</span>
                    @endif
                </div>
            </div>
            </div>
        </div>
    </div>

    {{-- (RIMOSSA) Sezione DDT duplicata: ora nella sidebar a destra --}}

    {{-- Sezione Proforme --}}
    @if($deposito->proforme && $deposito->proforme->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 d-flex align-items-center gap-2">
                            <iconify-icon icon="solar:bill-list-bold" class="me-1"></iconify-icon>
                            Proforme deposito
                        </h6>
                        <span class="badge bg-light-secondary text-secondary">{{ $deposito->proforme->count() }} documenti</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Proforma</th>
                                        <th class="text-center">Stato</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Totale</th>
                                        <th class="text-center">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deposito->proforme->sortByDesc('data_documento') as $proforma)
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    @php
                                                        $isFatturata = $proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA;
                                                        $classeBadge = $isFatturata ? 'bg-light-success text-success' : 'bg-light-danger text-danger';
                                                        $icona = $isFatturata ? 'solar:document-text-bold' : 'solar:danger-circle-bold';
                                                    @endphp
                                                    <div class="d-flex align-items-center gap-2">
                                                        <a href="{{ route('proforme-deposito.show', ['proformaDeposito' => $proforma->id]) }}" class="badge {{ $classeBadge }} d-inline-flex align-items-center gap-1">
                                                            <iconify-icon icon="{{ $icona }}" class="small"></iconify-icon>
                                                            PF {{ $proforma->numero }}
                                                        </a>
                                                        @if($isFatturata && $proforma->fattura_pdf_url)
                                                            <a href="{{ $proforma->fattura_pdf_url }}" target="_blank" class="badge d-inline-flex align-items-center gap-1" style="background-color:#ede5ff;color:#5d3fd3;">
                                                                <iconify-icon icon="solar:bill-check-bold" class="small"></iconify-icon>
                                                                {{ $proforma->fattura_numero ? 'FT '.$proforma->fattura_numero : 'Fattura' }}
                                                            </a>
                                                        @endif
                                                    </div>
                                                    <small class="text-muted">{{ $proforma->data_documento->format('d/m/Y') }}</small>
                                                    @if($proforma->ddtInvio)
                                                        <small class="text-muted">DDT invio: {{ $proforma->ddtInvio->numero }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                @if($proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA)
                                                    <span class="badge bg-light-success text-success">Fatturata</span>
                                                @else
                                                    <span class="badge bg-light-warning text-warning">Da fatturare</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $proforma->cliente_nome }} {{ $proforma->cliente_cognome }}</div>
                                                @if($proforma->cliente_email)
                                                    <small class="text-muted">{{ $proforma->cliente_email }}</small>
                                                @endif
                                            </td>
                                            <td class="text-end fw-semibold">€{{ number_format($proforma->totale, 2, ',', '.') }}</td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('proforme-deposito.show', ['proformaDeposito' => $proforma->id]) }}" class="btn btn-outline-secondary" title="Apri proforma">
                                                        <iconify-icon icon="solar:eye-bold"></iconify-icon>
                                                    </a>
                                                    @if($proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA)
                                                        @if($proforma->fattura_pdf_url)
                                                            <a href="{{ $proforma->fattura_pdf_url }}" target="_blank" class="btn btn-success" title="Scarica fattura">
                                                                <iconify-icon icon="solar:download-bold"></iconify-icon>
                                                            </a>
                                                        @endif
                                                        @if($this->puoGestireDestinatario)
                                                            <button class="btn btn-outline-warning" wire:click="riapriProforma({{ $proforma->id }})" title="Riapri proforma">
                                                                <iconify-icon icon="solar:refresh-bold"></iconify-icon>
                                                            </button>
                                                        @endif
                                                    @else
                                                        @if($this->puoGestireDestinatario)
                                                            <button class="btn btn-primary" wire:click="apriSegnaFatturataModal({{ $proforma->id }})" title="Segna come fatturata">
                                                                <iconify-icon icon="solar:check-circle-bold"></iconify-icon>
                                                            </button>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Articoli in Deposito (vecchia sezione nascosta) --}}
    <div class="row d-none">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Articoli in Deposito</h5>
                </div>
                <div class="card-body">
                    @if($articoliInDeposito->count() > 0 || $prodottiFinitiInDeposito->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        @if($deposito->stato !== 'chiuso')
                                            <th width="30" title="Seleziona per reso">
                                                <iconify-icon icon="solar:import-bold" class="text-warning small"></iconify-icon>
                                            </th>
                                            <th width="30" title="Seleziona per vendita">
                                                <iconify-icon icon="solar:cart-check-bold" class="text-success small"></iconify-icon>
                                            </th>
                                        @endif
                                        <th>Tipo</th>
                                        <th>Codice</th>
                                        <th>Descrizione</th>
                                        <th>Quantità</th>
                                        <th>Costo Unit.</th>
                                        <th>Costo Tot.</th>
                                        <th class="text-center">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Articoli --}}
                                    @foreach($articoliInDeposito as $articoloData)
                                        <tr>
                                            @if($deposito->stato !== 'chiuso')
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:change="toggleArticoloReso({{ $articoloData['articolo']->id }})"
                                                           @if($this->isArticoloSelezionatoReso($articoloData['articolo']->id)) checked @endif>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:click="toggleArticoloVendita({{ $articoloData['articolo']->id }})"
                                                           @if($this->isArticoloSelezionatoVendita($articoloData['articolo']->id)) checked @endif>
                                                </td>
                                            @endif
                                            <td>
                                                <span class="badge bg-light-primary text-primary">Articolo</span>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary">{{ $articoloData['articolo']->codice }}</span>
                                            </td>
                                            <td>{{ Str::limit($articoloData['articolo']->descrizione, 40) }}</td>
                                            <td>{{ $articoloData['quantita'] }}</td>
                                            <td>€{{ number_format($articoloData['costo_unitario'], 2, ',', '.') }}</td>
                                            <td>€{{ number_format($articoloData['costo_unitario'] * $articoloData['quantita'], 2, ',', '.') }}</td>
                                            <td class="text-center">
                                                @if($deposito->stato !== 'chiuso')
                                                    <button class="btn btn-success btn-sm" 
                                                            wire:click="apriRegistraVenditaModal('articolo', {{ $articoloData['articolo']->id }})"
                                                            title="Registra vendita">
                                                        <iconify-icon icon="solar:cart-check-bold"></iconify-icon>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach

                                    {{-- Prodotti Finiti --}}
                                    @foreach($prodottiFinitiInDeposito as $pfData)
                                        <tr>
                                            @if($deposito->stato !== 'chiuso')
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:change="toggleProdottoFinitoReso({{ $pfData['prodotto_finito']->id }})"
                                                           @if($this->isProdottoFinitoSelezionatoReso($pfData['prodotto_finito']->id)) checked @endif>
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           wire:click="toggleProdottoFinitoVendita({{ $pfData['prodotto_finito']->id }})"
                                                           @if($this->isProdottoFinitoSelezionatoVendita($pfData['prodotto_finito']->id)) checked @endif>
                                                </td>
                                            @endif
                                            <td>
                                                <span class="badge bg-light-warning text-warning">PF</span>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary">{{ $pfData['prodotto_finito']->codice }}</span>
                                            </td>
                                            <td>{{ Str::limit($pfData['prodotto_finito']->descrizione, 40) }}</td>
                                            <td>1</td>
                                            <td>€{{ number_format($pfData['costo_unitario'], 2, ',', '.') }}</td>
                                            <td>€{{ number_format($pfData['costo_unitario'], 2, ',', '.') }}</td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    @if($pfData['componenti']->count() > 0)
                                                        <button class="btn btn-outline-info btn-sm" 
                                                                type="button" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#componenti-{{ $pfData['prodotto_finito']->id }}" 
                                                                title="Vedi componenti">
                                                            <iconify-icon icon="solar:list-bold"></iconify-icon>
                                                            <small>{{ $pfData['componenti']->count() }}</small>
                                                        </button>
                                                    @endif
                                                    @if($deposito->stato !== 'chiuso')
                                                        <button class="btn btn-success btn-sm" 
                                                                wire:click="apriRegistraVenditaModal('prodotto_finito', {{ $pfData['prodotto_finito']->id }})"
                                                                title="Registra vendita">
                                                            <iconify-icon icon="solar:cart-check-bold"></iconify-icon>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        {{-- Riga espandibile con componenti --}}
                                        @if($pfData['componenti']->count() > 0)
                                            <tr class="collapse" id="componenti-{{ $pfData['prodotto_finito']->id }}">
                                                <td colspan="{{ $deposito->stato !== 'chiuso' ? '8' : '7' }}" class="p-0 border-0">
                                                    <div class="bg-light-warning p-3 mx-3 mb-2 rounded">
                                                        <h6 class="text-warning mb-2">
                                                            <iconify-icon icon="solar:settings-bold" class="me-1"></iconify-icon>
                                                            Componenti del prodotto finito {{ $pfData['prodotto_finito']->codice }}
                                                        </h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Codice Articolo</th>
                                                                        <th>Descrizione</th>
                                                                        <th>Categoria</th>
                                                                        <th class="text-center">Q.tà</th>
                                                                        <th class="text-end">Costo Unit.</th>
                                                                        <th class="text-end">Costo Tot.</th>
                                                                        <th class="text-center">Stato</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($pfData['componenti'] as $componente)
                                                                        <tr>
                                                                            <td>
                                                                                <span class="fw-bold text-primary">{{ $componente['articolo']->codice }}</span>
                                                                            </td>
                                                                            <td>{{ Str::limit($componente['articolo']->descrizione, 30) }}</td>
                                                                            <td>
                                                                                <span class="badge bg-light-info text-info">
                                                                                    {{ $componente['articolo']->categoriaMerceologica->nome ?? 'N/A' }}
                                                                                </span>
                                                                            </td>
                                                                            <td class="text-center">{{ $componente['quantita'] }}</td>
                                                                            <td class="text-end">€{{ number_format($componente['costo_unitario'], 2, ',', '.') }}</td>
                                                                            <td class="text-end">€{{ number_format($componente['costo_totale'], 2, ',', '.') }}</td>
                                                                            <td class="text-center">
                                                                                <span class="badge bg-light-{{ $componente['stato'] === 'utilizzato' ? 'success' : 'secondary' }} text-{{ $componente['stato'] === 'utilizzato' ? 'success' : 'secondary' }}">
                                                                                    {{ ucfirst($componente['stato']) }}
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                                <tfoot class="table-light">
                                                                    <tr>
                                                                        <th colspan="5" class="text-end">Totale Componenti:</th>
                                                                        <th class="text-end">€{{ number_format($pfData['componenti']->sum('costo_totale'), 2, ',', '.') }}</th>
                                                                        <th></th>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                        <div class="mt-2">
                                                            <small class="text-muted">
                                                                <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon>
                                                                Questi articoli sono stati utilizzati per creare il prodotto finito {{ $pfData['prodotto_finito']->codice }}
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <iconify-icon icon="solar:box-bold" class="fs-1 text-muted mb-2"></iconify-icon>
                            <p class="text-muted mb-0">Nessun articolo nel deposito</p>
                            @if($puoGestireMittente)
                                <button class="btn btn-primary mt-2" wire:click="apriAggiungiArticoliModal">
                                    Aggiungi Articoli
                                </button>
                                <small class="text-muted d-block mt-2">Seleziona gli articoli da inviare da {{ $deposito->sedeMittente->nome ?? 'sede mittente' }}.</small>
                            @else
                                <small class="text-muted d-block">In attesa di articoli dal mittente.</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Aggiungi Articoli --}}
    @if($showAggiungiArticoliModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="me-2"></iconify-icon>
                            Aggiungi Articoli al Deposito
                        </h5>
                        <button type="button" wire:click="chiudiAggiungiArticoliModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        @if(session('error'))
                            <div class="alert alert-danger">
                                <iconify-icon icon="solar:close-circle-bold" class="me-2"></iconify-icon>
                                {{ session('error') }}
                            </div>
                        @endif
                        {{-- Filtri --}}
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" wire:model.live="search" 
                                       placeholder="Cerca per codice o descrizione...">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" wire:model.live="tipoItem">
                                    <option value="articoli">Articoli</option>
                                    <option value="prodotti_finiti">Prodotti Finiti</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="text-end">
                                    <span class="badge bg-primary fs-6">{{ $this->getTotaleSelezionati() }} selezionati</span>
                                </div>
                            </div>
                        </div>
                        
                        @if($this->getTotaleSelezionati() > 0)
                            <div class="alert alert-light border d-flex flex-column gap-2">
                                <div class="fw-semibold text-muted">Selezionati (sempre visibili)</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($articoliSelezionati as $articoloId => $articoloData)
                                        <span class="badge bg-light text-dark border">
                                            ART {{ $articoloData['codice'] ?? $articoloId }}
                                            @if(!empty($articoloData['quantita'])) x{{ $articoloData['quantita'] }} @endif
                                            <button type="button" class="btn btn-link btn-sm ms-1 p-0 text-danger"
                                                    wire:click="toggleArticolo({{ $articoloId }})"
                                                    title="Deseleziona">
                                                ✕
                                            </button>
                                        </span>
                                    @endforeach
                                    @foreach($prodottiFinitiSelezionati as $pfId => $pfData)
                                        <span class="badge bg-light text-dark border">
                                            PF {{ $pfData['codice'] ?? $pfId }}
                                            <button type="button" class="btn btn-link btn-sm ms-1 p-0 text-danger"
                                                    wire:click="toggleProdottoFinito({{ $pfId }})"
                                                    title="Deseleziona">
                                                ✕
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Lista Articoli --}}
                        @if($tipoItem === 'articoli')
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="50">Sel.</th>
                                            <th>Codice</th>
                                            <th>Descrizione</th>
                                            <th>Categoria</th>
                                            <th>Disp.</th>
                                            <th>Qtà</th>
                                            <th>Costo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($articoliDisponibili as $articolo)
                                            <tr wire:key="add-articolo-{{ $articolo->id }}" class="{{ $this->isArticoloSelezionato($articolo->id) ? 'table-primary' : '' }}">
                                                <td>
                                                    <input type="checkbox" class="form-check-input" 
                                                           wire:change="toggleArticolo({{ $articolo->id }})"
                                                           {{ $this->isArticoloSelezionato($articolo->id) ? 'checked' : '' }}>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-primary">{{ $articolo->codice }}</span>
                                                </td>
                                                <td>{{ Str::limit($articolo->descrizione, 30) }}</td>
                                                <td>
                                                    <span class="badge bg-light-info text-info">
                                                        {{ $articolo->categoriaMerceologica->nome ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>{{ $articolo->getQuantitaDisponibilePerMovimentazione() }}</td>
                                                <td>
                                                    @if($this->isArticoloSelezionato($articolo->id))
                                                        <input type="number" class="form-control form-control-sm" 
                                                               style="width: 80px;"
                                                               wire:model="articoliSelezionati.{{ $articolo->id }}.quantita"
                                                               min="1" 
                                                               max="{{ $articolo->getQuantitaDisponibilePerMovimentazione() }}">
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="small">€{{ number_format($articolo->prezzo_acquisto ?? 0, 2, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-3">
                                                    <p class="text-muted mb-0">Nessun articolo disponibile</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Lista Prodotti Finiti --}}
                        @if($tipoItem === 'prodotti_finiti')
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="50">Sel.</th>
                                            <th>Codice</th>
                                            <th>Descrizione</th>
                                            <th>Categoria</th>
                                            <th>Stato</th>
                                            <th>Costo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($prodottiFinitiDisponibili as $pf)
                                            <tr wire:key="add-pf-{{ $pf->id }}" class="{{ $this->isProdottoFinitoSelezionato($pf->id) ? 'table-primary' : '' }}">
                                                <td>
                                                    <input type="checkbox" class="form-check-input" 
                                                           wire:change="toggleProdottoFinito({{ $pf->id }})"
                                                           {{ $this->isProdottoFinitoSelezionato($pf->id) ? 'checked' : '' }}>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-primary">{{ $pf->codice }}</span>
                                                </td>
                                                <td>{{ Str::limit($pf->descrizione, 30) }}</td>
                                                <td>
                                                    <span class="badge bg-light-info text-info">
                                                        {{ $pf->categoriaMerceologica->nome ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light-success text-success">
                                                        {{ ucfirst($pf->stato) }}
                                                    </span>
                                                </td>
                                                <td class="small">€{{ number_format($pf->costo_totale ?? 0, 2, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-3">
                                                    <p class="text-muted mb-0">Nessun prodotto finito disponibile</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiAggiungiArticoliModal">
                            Annulla
                        </button>
                        <button type="button" class="btn btn-primary" 
                                wire:click="aggiungiArticoliAlDeposito"
                                {{ $this->getTotaleSelezionati() === 0 ? 'disabled' : '' }}>
                            <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                            Aggiungi {{ $this->getTotaleSelezionati() }} Selezionati
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal Registra Vendita --}}
    @if($showRegistraVenditaModal && $itemVendita)
        <div class="modal fade show d-block" style="z-index: 1055;" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-backdrop fade show" style="z-index: 1040; pointer-events: none;"></div>
            <div class="modal-dialog modal-dialog-centered" style="z-index: 1056;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:cart-check-bold-duotone" class="me-2"></iconify-icon>
                            Registra Vendita
                        </h5>
                        <button type="button" wire:click="chiudiRegistraVenditaModal" class="btn-close" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Articolo/Prodotto</label>
                            <div class="p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold text-primary">{{ $itemVendita['item_codice'] ?? 'N/A' }}</span>
                                        <p class="mb-0 small text-muted">{{ $itemVendita['item_descrizione'] ?? '' }}</p>
                                    </div>
                                    <span class="badge bg-light-{{ $itemVendita['tipo'] === 'articolo' ? 'primary' : 'warning' }} text-{{ $itemVendita['tipo'] === 'articolo' ? 'primary' : 'warning' }}">
                                        {{ $itemVendita['tipo'] === 'articolo' ? 'Articolo' : 'PF' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Quantità da vendere</label>
                            <input type="number" class="form-control @error('quantitaVendita') is-invalid @enderror" 
                                   wire:model="quantitaVendita"
                                   min="1" 
                                   max="{{ $itemVendita['quantita_disponibile'] }}">
                            <div class="form-text">Disponibile: {{ $itemVendita['quantita_disponibile'] }}</div>
                            @error('quantitaVendita')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                            <strong>Costo unitario:</strong> €{{ number_format($itemVendita['costo_unitario'], 2, ',', '.') }}<br>
                            <strong>Totale vendita:</strong> €{{ number_format($itemVendita['costo_unitario'] * $quantitaVendita, 2, ',', '.') }}
                        </div>

                        <hr class="my-4">
                        
                        <h6 class="fw-bold mb-3">
                            <iconify-icon icon="solar:document-bold" class="me-2"></iconify-icon>
                            Dati Proforma *
                        </h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Numero Proforma *</label>
                                <input type="text" class="form-control @error('numeroProforma') is-invalid @enderror" 
                                       wire:model="numeroProforma"
                                       placeholder="Es: FV-2025-001">
                                @error('numeroProforma')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Data Proforma *</label>
                                <input type="date" class="form-control @error('dataProforma') is-invalid @enderror" 
                                       wire:model="dataProforma">
                                @error('dataProforma')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Nome Cliente *</label>
                                <input type="text" class="form-control @error('clienteNome') is-invalid @enderror" 
                                       wire:model="clienteNome">
                                @error('clienteNome')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Cognome Cliente *</label>
                                <input type="text" class="form-control @error('clienteCognome') is-invalid @enderror" 
                                       wire:model="clienteCognome">
                                @error('clienteCognome')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Telefono Cliente</label>
                                <input type="text" class="form-control @error('clienteTelefono') is-invalid @enderror" 
                                       wire:model="clienteTelefono">
                                @error('clienteTelefono')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Email Cliente</label>
                                <input type="email" class="form-control @error('clienteEmail') is-invalid @enderror" 
                                       wire:model="clienteEmail">
                                @error('clienteEmail')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Importo Totale Proforma (€) *</label>
                            <input type="number" step="0.01" class="form-control @error('importoTotaleProforma') is-invalid @enderror" 
                                   wire:model.live="importoTotaleProforma"
                                   min="0.01"
                                   required>
                            <div class="form-text">
                                <span class="text-info">
                                    <iconify-icon icon="solar:calculator-bold" class="me-1"></iconify-icon>
                                    Calcolato da: €{{ number_format($itemVendita['costo_unitario'] ?? 0, 2, ',', '.') }} × {{ $quantitaVendita ?? 1 }} = 
                                    <strong>€{{ number_format(($itemVendita['costo_unitario'] ?? 0) * ($quantitaVendita ?? 1), 2, ',', '.') }}</strong>
                                </span>
                            </div>
                            @error('importoTotaleProforma')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Note Proforma</label>
                            <textarea class="form-control @error('noteProforma') is-invalid @enderror" 
                                      wire:model="noteProforma"
                                      rows="2"
                                      placeholder="Note aggiuntive sulla proforma..."></textarea>
                            @error('noteProforma')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" 
                                class="btn btn-secondary" 
                                wire:click="chiudiRegistraVenditaModal">
                            Annulla
                        </button>
                        <button type="button" 
                                class="btn btn-success" 
                                wire:click="registraVendita"
                                wire:loading.attr="disabled"
                                wire:target="registraVendita">
                            <iconify-icon icon="solar:cart-check-bold" class="me-1"></iconify-icon>
                            <span wire:loading.remove wire:target="registraVendita">
                                Registra Vendita
                            </span>
                            <span wire:loading wire:target="registraVendita">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Registrazione in corso...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

{{-- Modal Rinnovo Deposito --}}
@if($showRinnovoModal)
    <div class="modal fade show" style="display: block;" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:refresh-bold" class="me-2"></iconify-icon>
                        Rinnova conto deposito
                    </h5>
                    <button type="button" class="btn-close" wire:click="chiudiRinnovoModal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Scegli quali articoli includere nel nuovo deposito. Le vendite già registrate non verranno replicate.
                    </p>

                    <div class="list-group">
                        <label class="list-group-item d-flex align-items-start gap-3">
                            <input class="form-check-input mt-1" type="radio" value="rimanenti" wire:model="rinnovoModalita">
                            <div>
                                <div class="fw-semibold">Solo articoli ancora in deposito</div>
                                <small class="text-muted">Rinnova soltanto quanto è ancora fisicamente presente nel conto deposito.</small>
                            </div>
                        </label>

                        <label class="list-group-item d-flex align-items-start gap-3">
                            <input class="form-check-input mt-1" type="radio" value="tutti" wire:model="rinnovoModalita">
                            <div>
                                <div class="fw-semibold">Tutti gli articoli del DDT di invio</div>
                                <small class="text-muted">Ripropone l'intero DDT iniziale (escluse le vendite), includendo anche gli articoli già rientrati.</small>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="chiudiRinnovoModal">Annulla</button>
                    <button type="button" class="btn btn-dark" wire:click="confermaRinnovoDeposito" wire:loading.attr="disabled" wire:target="confermaRinnovoDeposito">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                        <span wire:loading.remove wire:target="confermaRinnovoDeposito">Conferma rinnovo</span>
                        <span wire:loading wire:target="confermaRinnovoDeposito">
                            <span class="spinner-border spinner-border-sm me-2"></span>
                            Elaborazione...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Modal Anteprima DDT invio --}}
@if($showAnteprimaInvioModal)
    <div class="modal fade show" style="display: block;" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:document-text-bold" class="me-2"></iconify-icon>
                        Anteprima DDT di invio
                    </h5>
                    <button type="button" class="btn-close" wire:click="chiudiAnteprimaInvioModal"></button>
                </div>
                <div class="modal-body">
                    @if(!$haContenutoDeposito)
                        <div class="alert alert-info mb-0">
                            Nessun articolo selezionato. Aggiungi articoli prima di generare il DDT.
                        </div>
                    @else
                        <p class="text-muted">Compila i dati DDT e controlla le righe che verranno incluse.</p>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Dati trasporto</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Causale trasporto</label>
                                        <input type="text" class="form-control" wire:model="ddtCausale" placeholder="Es: Conto deposito">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Aspetto esteriore beni</label>
                                        <input type="text" class="form-control" wire:model="ddtAspettoBeni" placeholder="Es: Colli">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">N. colli</label>
                                        <input type="number" min="0" class="form-control" wire:model="ddtNumeroColli">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Trasporto a mezzo</label>
                                        <select class="form-select" wire:model="ddtTrasportoMezzo">
                                            <option value="">Seleziona...</option>
                                            <option value="mittente">Mittente</option>
                                            <option value="destinatario">Destinatario</option>
                                            <option value="vettore">Vettore</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Corriere/Vettore</label>
                                        <input type="text" class="form-control" wire:model="ddtCorriere">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Numero tracking</label>
                                        <input type="text" class="form-control" wire:model="ddtNumeroTracking">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Note</label>
                                        <input type="text" class="form-control" wire:model="ddtNote" placeholder="Note per il DDT">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($articoliInDeposito->isNotEmpty())
                            <h6 class="fw-semibold">Articoli ({{ $articoliInDeposito->count() }})</h6>
                            <div class="table-responsive mb-3" style="max-height: 240px;">
                                <table class="table table-sm table-striped align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Codice</th>
                                            <th>Descrizione</th>
                                            <th class="text-end">Quantità</th>
                                            <th class="text-end">Costo unitario</th>
                                            <th class="text-end">Totale</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($articoliInDeposito as $articoloData)
                                            <tr>
                                                <td class="fw-semibold">{{ $articoloData['articolo']->codice }}</td>
                                                <td>{{ Str::limit($articoloData['articolo']->descrizione, 60) }}</td>
                                                <td class="text-end">{{ $articoloData['quantita'] }}</td>
                                                <td class="text-end">€{{ number_format($articoloData['costo_unitario'], 2, ',', '.') }}</td>
                                                <td class="text-end">€{{ number_format($articoloData['costo_unitario'] * $articoloData['quantita'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($prodottiFinitiInDeposito->isNotEmpty())
                            <h6 class="fw-semibold">Prodotti finiti ({{ $prodottiFinitiInDeposito->count() }})</h6>
                            <div class="table-responsive" style="max-height: 240px;">
                                <table class="table table-sm table-striped align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Codice</th>
                                            <th>Descrizione</th>
                                            <th class="text-end">Q.tà</th>
                                            <th class="text-end">Costo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($prodottiFinitiInDeposito as $pfData)
                                            <tr>
                                                <td class="fw-semibold">{{ $pfData['prodotto_finito']->codice }}</td>
                                                <td>{{ Str::limit($pfData['prodotto_finito']->descrizione, 60) }}</td>
                                                <td class="text-end">1</td>
                                                <td class="text-end">€{{ number_format($pfData['costo_unitario'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="chiudiAnteprimaInvioModal">Chiudi</button>
                    <button type="button" class="btn btn-success" wire:click="generaDdtInvio" wire:loading.attr="disabled" wire:target="generaDdtInvio">
                        <iconify-icon icon="solar:document-add-bold" class="me-1"></iconify-icon>
                        <span wire:loading.remove wire:target="generaDdtInvio">Genera DDT</span>
                        <span wire:loading wire:target="generaDdtInvio">
                            <span class="spinner-border spinner-border-sm me-2"></span>
                            Generazione...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Modal Annulla DDT Invio --}}
@if($showAnnullaDdtInvioModal)
    <div class="modal fade show" style="display: block;" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:danger-triangle-bold-duotone" class="me-2"></iconify-icon>
                        Annulla DDT di invio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="chiudiAnnullaDdtInvioModal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Questa operazione:</p>
                    <ul class="mb-0">
                        <li>rimuove il DDT di invio generato</li>
                        <li>permette di aggiungere articoli mancanti</li>
                        <li>richiede di rigenerare il DDT</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="chiudiAnnullaDdtInvioModal">Annulla</button>
                    <button type="button" class="btn btn-danger" wire:click="annullaDdtInvio">Conferma annullamento</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif

    {{-- Modal Vendita Multipla --}}
    @if($showVenditaMultiplaModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:cart-check-bold-duotone" class="me-2"></iconify-icon>
                            Vendita Multipla con Proforma
                        </h5>
                        <button type="button" wire:click="chiudiVenditaMultiplaModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            {{-- Colonna sinistra: Dati Proforma --}}
                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary mb-3">
                                    <iconify-icon icon="solar:document-text-bold" class="me-1"></iconify-icon>
                                    Dati Proforma
                                </h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Numero Proforma *</label>
                                    <input type="text" class="form-control @error('numeroProforma') is-invalid @enderror" 
                                           wire:model="numeroProforma" 
                                           placeholder="es. FT-2024-001">
                                    @error('numeroProforma')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Data Proforma *</label>
                                    <input type="date" class="form-control @error('dataProforma') is-invalid @enderror" 
                                           wire:model="dataProforma">
                                    @error('dataProforma')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Nome Cliente *</label>
                                            <input type="text" class="form-control @error('clienteNome') is-invalid @enderror" 
                                                   wire:model="clienteNome">
                                            @error('clienteNome')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Cognome Cliente *</label>
                                            <input type="text" class="form-control @error('clienteCognome') is-invalid @enderror" 
                                                   wire:model="clienteCognome">
                                            @error('clienteCognome')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Telefono Cliente</label>
                                    <input type="text" class="form-control @error('clienteTelefono') is-invalid @enderror" 
                                           wire:model="clienteTelefono">
                                    @error('clienteTelefono')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Cliente</label>
                                    <input type="email" class="form-control @error('clienteEmail') is-invalid @enderror" 
                                           wire:model="clienteEmail">
                                    @error('clienteEmail')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            {{-- Colonna destra: Articoli Selezionati --}}
                            <div class="col-md-6">
                                <h6 class="fw-bold text-success mb-3" wire:key="selection-header-{{ $this->getTotaleSelezionatiVendita() }}">
                                    <iconify-icon icon="solar:bag-check-bold" class="me-1"></iconify-icon>
                                    Articoli Selezionati ({{ $this->getTotaleSelezionatiVendita() }})
                                </h6>
                                
                                @if(empty($articoliSelezionatiVendita) && empty($prodottiFinitiSelezionatiVendita))
                                    <div class="alert alert-info text-center">
                                        <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                        Nessun articolo selezionato.<br>
                                        <small>Usa le checkbox nella tabella per selezionare articoli da vendere.</small>
                                    </div>
                                @else
                                    <div class="mb-3" style="max-height: 300px; overflow-y: auto;">
                                        {{-- Articoli selezionati --}}
                                        @foreach($articoliSelezionatiVendita as $articoloId => $articoloData)
                                            <div class="card mb-2">
                                                <div class="card-body p-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="badge bg-light-primary text-primary me-1">ART</span>
                                                            <strong>{{ $articoloData['codice'] }}</strong>
                                                            <br><small class="text-muted">{{ Str::limit($articoloData['descrizione'], 30) }}</small>
                                                        </div>
                                                        <div class="text-end">
                                                            <input type="number" 
                                                                   class="form-control form-control-sm" 
                                                                   style="width: 70px; display: inline-block;"
                                                                   wire:model="articoliSelezionatiVendita.{{ $articoloId }}.quantita"
                                                                   min="1" 
                                                                   max="{{ $articoloData['max_quantita'] }}">
                                                            <br><small class="text-muted">Max: {{ $articoloData['max_quantita'] }}</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                        
                                        {{-- Prodotti finiti selezionati --}}
                                        @foreach($prodottiFinitiSelezionatiVendita as $pfId => $pfData)
                                            <div class="card mb-2">
                                                <div class="card-body p-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="badge bg-light-warning text-warning me-1">PF</span>
                                                            <strong>{{ $pfData['codice'] }}</strong>
                                                            <br><small class="text-muted">{{ Str::limit($pfData['descrizione'], 30) }}</small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-light-success text-success">Q.tà: 1</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Importo Totale --}}
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-primary d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">
                                            <iconify-icon icon="solar:calculator-bold" class="me-2"></iconify-icon>
                                            Importo Totale Proforma
                                        </h6>
                                        <small class="text-muted">Calcolato automaticamente dai costi unitari</small>
                                    </div>
                                    <div>
                                        <span class="h4 mb-0 text-primary">€{{ number_format($importoTotaleProforma, 2, ',', '.') }}</span>
                                        <input type="hidden" wire:model="importoTotaleProforma">
                                    </div>
                                </div>
                            </div>
</div>

                        {{-- Note --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Note Proforma</label>
                                    <textarea class="form-control @error('noteProforma') is-invalid @enderror" 
                                              wire:model="noteProforma" 
                                              rows="2" 
                                              placeholder="Note aggiuntive per la proforma..."></textarea>
                                    @error('noteProforma')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiVenditaMultiplaModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" 
                                class="btn btn-success" 
                                wire:click="registraVenditaMultipla"
                                @if(empty($articoliSelezionatiVendita) && empty($prodottiFinitiSelezionatiVendita)) disabled @endif>
                            <iconify-icon icon="solar:cart-check-bold" class="me-1"></iconify-icon>
                            Registra Vendita Multipla
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal Reso Manuale --}}
    @if($showResoManualeModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:import-bold-duotone" class="me-2"></iconify-icon>
                            Reso Manuale Articoli
                        </h5>
                        <button type="button" wire:click="chiudiResoManualeModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                            <strong>Informazioni:</strong> Seleziona gli articoli da restituire alla sede mittente. Dopo il reso potrai generare il DDT di reso.
                        </div>

                        <h6 class="fw-bold text-warning mb-3">
                            <iconify-icon icon="solar:bag-check-bold" class="me-1"></iconify-icon>
                            Articoli Selezionati per Reso ({{ $this->getTotaleSelezionatiReso() }})
                        </h6>
                        
                        @if(empty($articoliSelezionatiReso) && empty($prodottiFinitiSelezionatiReso))
                            <div class="alert alert-warning text-center">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                Nessun articolo selezionato.<br>
                                <small>Usa le checkbox <iconify-icon icon="solar:import-bold" class="text-warning"></iconify-icon> nella tabella per selezionare articoli da restituire.</small>
                            </div>
                        @else
                            <div class="mb-3" style="max-height: 300px; overflow-y: auto;">
                                {{-- Articoli selezionati --}}
                                @foreach($articoliSelezionatiReso as $articoloId => $articoloData)
                                    @php
                                        $articolo = \App\Models\Articolo::find($articoloId);
                                    @endphp
                                    <div class="card mb-2">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-light-primary text-primary me-1">ART</span>
                                                    <strong>{{ $articolo->codice ?? 'N/A' }}</strong>
                                                    <br><small class="text-muted">{{ Str::limit($articolo->descrizione ?? '', 30) }}</small>
                                                </div>
                                                <div class="text-end">
                                                    <input type="number" 
                                                           class="form-control form-control-sm" 
                                                           style="width: 70px; display: inline-block;"
                                                           wire:model="articoliSelezionatiReso.{{ $articoloId }}.quantita"
                                                           min="1" 
                                                           max="{{ $articoloData['max_quantita'] }}">
                                                    <br><small class="text-muted">Max: {{ $articoloData['max_quantita'] }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                
                                {{-- Prodotti finiti selezionati --}}
                                @foreach($prodottiFinitiSelezionatiReso as $pfId => $pfData)
                                    @php
                                        $pf = \App\Models\ProdottoFinito::find($pfId);
                                    @endphp
                                    <div class="card mb-2">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-light-warning text-warning me-1">PF</span>
                                                    <strong>{{ $pf->codice ?? 'N/A' }}</strong>
                                                    <br><small class="text-muted">{{ Str::limit($pf->descrizione ?? '', 30) }}</small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light-warning text-warning">Q.tà: 1</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiResoManualeModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        <button type="button" 
                                class="btn btn-warning" 
                                wire:click="eseguiResoManuale"
                                @if(empty($articoliSelezionatiReso) && empty($prodottiFinitiSelezionatiReso)) disabled @endif>
                            <iconify-icon icon="solar:import-bold" class="me-1"></iconify-icon>
                            Conferma Reso
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal Genera DDT Reso con Anteprima --}}
    @if($showGeneraDdtResoModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:document-add-bold-duotone" class="me-2"></iconify-icon>
                            Genera DDT di Reso - Anteprima
                        </h5>
                        <button type="button" wire:click="chiudiGeneraDdtResoModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Info Box --}}
                        <div class="alert alert-info">
                            <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                            <strong>Come funziona:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Seleziona gli articoli da restituire (reso manuale)</li>
                                <li>Esamina l'anteprima qui sotto</li>
                                <li>Genera il DDT di reso</li>
                                <li>Stampa e spedisci</li>
                            </ol>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Dati trasporto</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Causale trasporto</label>
                                        <input type="text" class="form-control" wire:model="ddtCausale" placeholder="Es: Reso conto deposito">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Aspetto esteriore beni</label>
                                        <input type="text" class="form-control" wire:model="ddtAspettoBeni" placeholder="Es: Colli">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">N. colli</label>
                                        <input type="number" min="0" class="form-control" wire:model="ddtNumeroColli">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Trasporto a mezzo</label>
                                        <select class="form-select" wire:model="ddtTrasportoMezzo">
                                            <option value="">Seleziona...</option>
                                            <option value="mittente">Mittente</option>
                                            <option value="destinatario">Destinatario</option>
                                            <option value="vettore">Vettore</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Corriere/Vettore</label>
                                        <input type="text" class="form-control" wire:model="ddtCorriere">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Numero tracking</label>
                                        <input type="text" class="form-control" wire:model="ddtNumeroTracking">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Note</label>
                                        <input type="text" class="form-control" wire:model="ddtNote" placeholder="Note per il DDT">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                            $movimentiDisponibili = $this->anteprimaMovimentiReso;
                        @endphp

                        @if($movimentiDisponibili->isEmpty())
                            <div class="alert alert-warning text-center">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2 fs-4"></iconify-icon>
                                <strong>Nessun movimento di reso disponibile</strong><br>
                                <small>Tutti i movimenti di reso sono già stati inclusi in DDT precedenti.</small>
                            </div>
                        @else
                            {{-- DDT Reso esistenti --}}
                            @if($deposito->ddtResi->count() > 0)
                                <div class="mb-3">
                                    <h6 class="text-warning">
                                        <iconify-icon icon="solar:document-text-bold" class="me-1"></iconify-icon>
                                        DDT Reso già generati ({{ $deposito->ddtResi->count() }})
                                    </h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($deposito->ddtResi as $ddtReso)
                                            <span class="badge bg-light-warning text-warning p-2">
                                                <iconify-icon icon="solar:document-bold"></iconify-icon>
                                                {{ $ddtReso->numero }} 
                                                <small>({{ $ddtReso->created_at->format('d/m/Y') }})</small>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Anteprima Movimenti Reso --}}
                            <div class="card">
                                <div class="card-header bg-light-warning">
                                    <h6 class="card-title mb-0">
                                        <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon>
                                        Anteprima DDT di Reso
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        <strong>Questi articoli verranno inclusi nel nuovo DDT di reso:</strong>
                                    </p>
                                    
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Codice</th>
                                                    <th>Descrizione</th>
                                                    <th class="text-center">Quantità</th>
                                                    <th class="text-end">Costo Unit.</th>
                                                    <th class="text-end">Costo Tot.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $totaleArticoli = 0;
                                                    $totaleValore = 0;
                                                @endphp
                                                @foreach($movimentiDisponibili as $movimento)
                                                    @php
                                                        $item = $movimento->getItem();
                                                        $totaleArticoli += $movimento->quantita;
                                                        $totaleValore += $movimento->costo_totale;
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            @if($movimento->articolo_id)
                                                                <span class="badge bg-light-primary text-primary">Articolo</span>
                                                            @else
                                                                <span class="badge bg-light-warning text-warning">PF</span>
                                                            @endif
                                                        </td>
                                                        <td><strong>{{ $item->codice ?? 'N/A' }}</strong></td>
                                                        <td>{{ Str::limit($item->descrizione ?? '', 40) }}</td>
                                                        <td class="text-center">{{ $movimento->quantita }}</td>
                                                        <td class="text-end">€{{ number_format($movimento->costo_unitario, 2, ',', '.') }}</td>
                                                        <td class="text-end"><strong>€{{ number_format($movimento->costo_totale, 2, ',', '.') }}</strong></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th colspan="3" class="text-end">TOTALE:</th>
                                                    <th class="text-center">{{ $totaleArticoli }}</th>
                                                    <th colspan="2" class="text-end"><strong>€{{ number_format($totaleValore, 2, ',', '.') }}</strong></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="mt-3 p-3 bg-light-warning rounded">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <strong class="d-block text-warning">Articoli Totali</strong>
                                                <span class="fs-4">{{ $totaleArticoli }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="d-block text-warning">Valore Totale</strong>
                                                <span class="fs-4">€{{ number_format($totaleValore, 2, ',', '.') }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="d-block text-warning">DDT Precedenti</strong>
                                                <span class="fs-4">{{ $deposito->ddtResi->count() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiGeneraDdtResoModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        @if($movimentiDisponibili->isNotEmpty())
                            <button type="button" 
                                    class="btn btn-warning btn-lg" 
                                    wire:click="generaDdtReso"
                                    wire:loading.attr="disabled">
                                <iconify-icon icon="solar:document-add-bold" class="me-1"></iconify-icon>
                                <span wire:loading.remove wire:target="generaDdtReso">Genera DDT di Reso</span>
                                <span wire:loading wire:target="generaDdtReso">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Generazione in corso...
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

</div>