<div>
    {{-- Header con statistiche --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">Gestione Conti Deposito</h4>
                    <p class="text-muted mb-0">Monitora e gestisci i depositi articoli tra sedi</p>
                </div>
                <button class="btn btn-primary" wire:click="apriNuovoDepositoModal">
                    <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                    Nuovo Deposito
                </button>
            </div>
        </div>
    </div>

    {{-- Statistiche principali --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-light-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:box-bold-duotone" class="fs-2 text-primary"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['depositi_attivi'] }}</h5>
                            <p class="text-muted mb-0 small">Depositi Attivi</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:clock-circle-bold-duotone" class="fs-2 text-warning"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['depositi_in_scadenza'] }}</h5>
                            <p class="text-muted mb-0 small">In Scadenza</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:danger-bold-duotone" class="fs-2 text-danger"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['depositi_scaduti'] }}</h5>
                            <p class="text-muted mb-0 small">Scaduti</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:euro-bold-duotone" class="fs-2 text-success"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">€{{ number_format($statistiche['valore_totale_depositi'], 0, ',', '.') }}</h5>
                            <p class="text-muted mb-0 small">Valore Totale</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Approfondimenti --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-light-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:document-text-bold-duotone" class="fs-2 text-danger"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['proforme_da_fatturare'] }}</h5>
                            <p class="text-muted mb-0 small">Proforme da fatturare</p>
                            <small class="text-danger fw-semibold">€{{ number_format($statistiche['valore_da_fatturare'], 0, ',', '.') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-2 text-success"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['proforme_fatturate'] }}</h5>
                            <p class="text-muted mb-0 small">Proforme fatturate</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0" style="background-color:#ede5ff;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:bill-check-bold-duotone" class="fs-2" style="color:#5d3fd3;"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['fatture_pdf'] }}</h5>
                            <p class="text-muted mb-0 small">Fatture caricate</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <iconify-icon icon="solar:box-minimalistic-bold-duotone" class="fs-2 text-warning"></iconify-icon>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="fw-bold mb-0">{{ $statistiche['giacenze_residue'] }}</h5>
                            <p class="text-muted mb-0 small">Articoli ancora in deposito</p>
                            <small class="text-warning fw-semibold">DDT reso mancanti: {{ $statistiche['depositi_senza_ddt_reso'] }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if($alerts->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                @foreach($alerts as $alert)
                    <div class="alert alert-{{ $alert['tipo'] }} d-flex align-items-center" role="alert">
                        <iconify-icon icon="{{ $alert['icona'] }}" class="me-2 fs-5"></iconify-icon>
                        <div class="flex-grow-1">
                            <strong>{{ $alert['titolo'] }}:</strong> {{ $alert['messaggio'] }}
                        </div>
                        <button class="btn btn-sm btn-{{ $alert['tipo'] }}" 
                                wire:click="applicaFiltroAlert('{{ $alert['azione'] }}', '{{ $alert['valore'] }}')">
                            Visualizza
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filtri --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Cerca</label>
                    <input type="text" class="form-control" wire:model.live="search" 
                           placeholder="Codice, sede...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Stato</label>
                    <select class="form-select" wire:model.live="filtroStato">
                        <option value="">Tutti</option>
                        <option value="attivo">Attivo</option>
                        <option value="scaduto">Scaduto</option>
                        <option value="chiuso">Chiuso</option>
                        <option value="parziale">Parziale</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Sede</label>
                    <select class="form-select" wire:model.live="filtroSede">
                        <option value="">Tutte</option>
                        @foreach($sedi as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Scadenza</label>
                    <select class="form-select" wire:model.live="filtroScadenza">
                        <option value="">Tutte</option>
                        <option value="scaduti">Scaduti</option>
                        <option value="in_scadenza_30">Scadenza 30gg</option>
                        <option value="in_scadenza_60">Scadenza 60gg</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-light w-100" wire:click="resetFiltri">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Legenda documenti --}}
    <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 small mb-3">
        <span class="fw-semibold text-muted me-2">Legenda documenti:</span>
        <span class="badge bg-light-info text-info">DDT Invio</span>
        <span class="badge bg-light-warning text-warning">DDT Reso</span>
        <span class="badge bg-light-danger text-danger">Proforma da fatturare</span>
        <span class="badge bg-light-success text-success">Proforma fatturata</span>
        <span class="badge" style="background-color:#ede5ff;color:#5d3fd3;">Fattura finale</span>
    </div>

    {{-- Tabella depositi --}}
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>DDT / Proforme</th>
                            <th>Sedi</th>
                            <th>Data Invio</th>
                            <th>Scadenza</th>
                            <th>Stato</th>
                            <th>Articoli</th>
                            <th>Valore</th>
                            <th class="text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($depositi as $deposito)
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">{{ $deposito->codice }}</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if($deposito->ddt_invio_id && $deposito->ddtInvio)
                                            <a href="{{ route('ddt-deposito.show', $deposito->ddt_invio_id) }}" 
                                               class="badge bg-light-info text-info d-inline-flex align-items-center gap-1" title="DDT di invio">
                                                <iconify-icon icon="solar:export-bold" class="small"></iconify-icon>
                                                {{ $deposito->ddtInvio->numero }}
                                            </a>
                                        @endif

                                        @foreach($deposito->ddtResi as $ddtReso)
                                            <a href="{{ route('ddt-deposito.show', $ddtReso->id) }}" 
                                               class="badge bg-light-warning text-warning d-inline-flex align-items-center gap-1" title="DDT di reso {{ $ddtReso->numero }}">
                                                <iconify-icon icon="solar:import-bold" class="small"></iconify-icon>
                                                {{ $ddtReso->numero }}
                                            </a>
                                        @endforeach

                                        @foreach(($deposito->proforme ?? collect())->sortByDesc('data_documento') as $proforma)
                                            @php
                                                $isFatturata = $proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA;
                                                $proformaClasse = $isFatturata ? 'bg-light-success text-success' : 'bg-light-danger text-danger';
                                                $iconaProforma = $isFatturata ? 'solar:document-text-bold' : 'solar:danger-circle-bold';
                                            @endphp
                                            <a href="{{ route('proforme-deposito.show', ['proformaDeposito' => $proforma->id]) }}" 
                                               class="badge {{ $proformaClasse }} d-inline-flex align-items-center gap-1" 
                                               title="Proforma {{ $proforma->numero }} - Cliente: {{ $proforma->cliente_nome }} {{ $proforma->cliente_cognome }} - €{{ number_format($proforma->totale, 2, ',', '.') }}">
                                                <iconify-icon icon="{{ $iconaProforma }}" class="small"></iconify-icon>
                                                PF {{ $proforma->numero }}
                                            </a>

                                            @if($isFatturata && $proforma->fattura_pdf_url)
                                                <a href="{{ $proforma->fattura_pdf_url }}" target="_blank" class="badge d-inline-flex align-items-center gap-1" style="background-color:#ede5ff;color:#5d3fd3;" title="Fattura finale">
                                                    <iconify-icon icon="solar:bill-check-bold" class="small"></iconify-icon>
                                                    {{ $proforma->fattura_numero ? 'FT '.$proforma->fattura_numero : 'Fattura' }}
                                                </a>
                                            @endif
                                        @endforeach

                                        @if(!$deposito->ddt_invio_id && $deposito->ddtResi->count() == 0 && (!$deposito->proforme || $deposito->proforme->count() == 0))
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <iconify-icon icon="solar:export-bold" class="text-muted me-1"></iconify-icon>
                                        <small>{{ $deposito->sedeMittente->nome }}</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <iconify-icon icon="solar:import-bold" class="text-muted me-1"></iconify-icon>
                                        <small>{{ $deposito->sedeDestinataria->nome }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="small">{{ $deposito->data_invio->format('d/m/Y') }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-light-{{ $this->getColoreScadenza($deposito->data_scadenza) }} text-{{ $this->getColoreScadenza($deposito->data_scadenza) }} me-2">
                                            {{ $deposito->data_scadenza->format('d/m/Y') }}
                                        </span>
                                        @php $giorni = $this->getGiorniRimanenti($deposito->data_scadenza); @endphp
                                        @if($giorni < 0)
                                            <small class="text-danger">Scaduto</small>
                                        @elseif($giorni <= 30)
                                            <small class="text-warning">{{ $giorni }}gg</small>
                                        @else
                                            <small class="text-muted">{{ $giorni }}gg</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light-{{ $deposito->stato_color }} text-{{ $deposito->stato_color }}">
                                        {{ $deposito->stato_label }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <div>Inviati: <strong>{{ $deposito->articoli_inviati }}</strong></div>
                                        @if($deposito->articoli_venduti > 0)
                                            <div class="text-success">Venduti: {{ $deposito->articoli_venduti }}</div>
                                        @endif
                                        @if($deposito->articoli_rientrati > 0)
                                            <div class="text-info">Rientrati: {{ $deposito->articoli_rientrati }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div>€{{ number_format($deposito->valore_totale_invio, 0, ',', '.') }}</div>
                                        @if($deposito->valore_venduto > 0)
                                            <div class="text-success">-€{{ number_format($deposito->valore_venduto, 0, ',', '.') }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        {{-- Azione principale: se attivo -> Gestisci, se chiuso -> Dettagli --}}
                                        @if($deposito->stato === 'attivo')
                                            <a href="{{ route('conti-deposito.gestisci', $deposito->id) }}" 
                                               class="btn btn-primary btn-sm"
                                               title="Gestisci deposito">
                                                <iconify-icon icon="solar:settings-bold" class="me-1"></iconify-icon>
                                                Gestisci
                                            </a>
                                        @else
                                            <a href="{{ route('conti-deposito.show', $deposito->id) }}" 
                                               class="btn btn-light btn-sm"
                                               title="Dettagli deposito">
                                                <iconify-icon icon="solar:eye-bold" class="me-1 text-secondary"></iconify-icon>
                                                Dettagli
                                            </a>
                                        @endif
                                        
                                        {{-- Tasto Dettagli --}}
                                        <button class="btn btn-light btn-sm" 
                                                wire:click="apriDettaglioModal({{ $deposito->id }})"
                                                title="Visualizza dettagli">
                                            <iconify-icon icon="solar:eye-bold" class="text-info"></iconify-icon>
                                        </button>
                                        
                                        @if($deposito->stato === 'attivo' && $deposito->isScaduto())
                                            <button class="btn btn-light btn-sm" 
                                                    wire:click="gestisciResoScadenza({{ $deposito->id }})"
                                                    title="Gestisci reso scadenza">
                                                <iconify-icon icon="solar:import-bold" class="text-warning"></iconify-icon>
                                            </button>
                                        @endif
                                        
                                        {{-- Azioni consentite sui depositi chiusi: nessuna --}}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <iconify-icon icon="solar:box-bold" class="fs-1 mb-2"></iconify-icon>
                                        <p class="mb-0">Nessun deposito trovato</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginazione --}}
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Mostrando {{ $depositi->firstItem() ?? 0 }} - {{ $depositi->lastItem() ?? 0 }} 
                    di {{ $depositi->total() }} depositi
                </div>
                {{ $depositi->links() }}
            </div>
        </div>
    </div>

    {{-- Modal Nuovo Deposito con Step Guidati --}}
    @if($showNuovoDepositoModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="me-2"></iconify-icon>
                            Nuovo Conto Deposito
                        </h5>
                        <button type="button" wire:click="chiudiNuovoDepositoModal" class="btn-close btn-close-white"></button>
                    </div>
                    
                    {{-- Progress Steps --}}
                    <div class="modal-body border-bottom">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center {{ $stepCreazioneDeposito >= 1 ? 'text-primary' : 'text-muted' }}">
                                <div class="rounded-circle bg-{{ $stepCreazioneDeposito >= 1 ? 'primary' : 'light' }} text-{{ $stepCreazioneDeposito >= 1 ? 'white' : 'muted' }} d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">{{ $stepCreazioneDeposito >= 2 ? '✓' : '1' }}</span>
                                </div>
                                <span class="ms-2 fw-semibold">Informazioni Deposito</span>
                            </div>
                            <div class="flex-grow-1 mx-3">
                                <div class="progress" style="height: 2px;">
                                    <div class="progress-bar bg-{{ $stepCreazioneDeposito >= 2 ? 'primary' : 'secondary' }}" 
                                         style="width: {{ $stepCreazioneDeposito >= 2 ? '100' : '0' }}%"></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center {{ $stepCreazioneDeposito >= 2 ? 'text-primary' : 'text-muted' }}">
                                <span class="me-2 fw-semibold">Anteprima</span>
                                <div class="rounded-circle bg-{{ $stepCreazioneDeposito >= 2 ? 'primary' : 'light' }} text-{{ $stepCreazioneDeposito >= 2 ? 'white' : 'muted' }} d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">2</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-body">
                        @if($stepCreazioneDeposito == 1)
                            {{-- STEP 1: Informazioni Deposito --}}
                            <div class="alert alert-info">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                <strong>Come funziona:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Inserisci le informazioni base del deposito</li>
                                    <li>Verifica l'anteprima</li>
                                    <li>Crea il deposito e aggiungi gli articoli</li>
                                </ol>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <iconify-icon icon="solar:shop-bold" class="me-1"></iconify-icon>
                                    Sede Mittente *
                                </label>
                                <select class="form-select form-select-lg @error('sedeMittenteId') is-invalid @enderror" 
                                        wire:model.live="sedeMittenteId">
                                    <option value="">Seleziona sede mittente...</option>
                                    @foreach($this->sedi as $sede)
                                        <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                    @endforeach
                                </select>
                                @error('sedeMittenteId')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <iconify-icon icon="solar:map-point-bold" class="me-1"></iconify-icon>
                                    Sede Destinataria *
                                </label>
                                <select class="form-select form-select-lg @error('sedeDestinatariaId') is-invalid @enderror" 
                                        wire:model.live="sedeDestinatariaId">
                                    <option value="">Seleziona sede destinataria...</option>
                                    @foreach($this->sedi as $sede)
                                        <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                    @endforeach
                                </select>
                                @error('sedeDestinatariaId')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if($sedeMittenteId && $sedeDestinatariaId && $sedeMittenteId == $sedeDestinatariaId)
                                    <div class="text-danger small mt-1">
                                        <iconify-icon icon="solar:danger-circle-bold" class="me-1"></iconify-icon>
                                        La sede destinataria deve essere diversa dalla mittente
                                    </div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <iconify-icon icon="solar:notes-bold" class="me-1"></iconify-icon>
                                    Note (opzionale)
                                </label>
                                <textarea class="form-control @error('noteDeposito') is-invalid @enderror" 
                                          wire:model="noteDeposito" 
                                          rows="3" 
                                          placeholder="Note aggiuntive sul deposito..."></textarea>
                                @error('noteDeposito')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-warning">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                <strong>Nota:</strong> Il deposito sarà creato con durata di <strong>1 anno</strong> dalla data di creazione. 
                                Dopo la creazione potrai aggiungere articoli e prodotti finiti dal pannello di gestione.
                            </div>
                            
                        @elseif($stepCreazioneDeposito == 2)
                            {{-- STEP 2: Anteprima --}}
                            @php
                                $sedeMittente = $this->sedi->firstWhere('id', $sedeMittenteId);
                                $sedeDestinataria = $this->sedi->firstWhere('id', $sedeDestinatariaId);
                                $dataInvio = now();
                                $dataScadenza = now()->addYear();
                            @endphp
                            
                            <div class="alert alert-success">
                                <iconify-icon icon="solar:check-circle-bold" class="me-2"></iconify-icon>
                                <strong>Riepilogo informazioni deposito</strong>
                            </div>

                            <div class="card">
                                <div class="card-header bg-light-primary">
                                    <h6 class="card-title mb-0">
                                        <iconify-icon icon="solar:box-bold-duotone" class="me-1"></iconify-icon>
                                        Dettagli Deposito
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong class="text-primary">
                                                    <iconify-icon icon="solar:shop-bold" class="me-1"></iconify-icon>
                                                    Sede Mittente:
                                                </strong><br>
                                                <span class="ms-4">{{ $sedeMittente->nome ?? 'N/A' }}</span>
                                            </p>
                                            <p class="mb-2">
                                                <strong class="text-primary">
                                                    <iconify-icon icon="solar:map-point-bold" class="me-1"></iconify-icon>
                                                    Sede Destinataria:
                                                </strong><br>
                                                <span class="ms-4">{{ $sedeDestinataria->nome ?? 'N/A' }}</span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2">
                                                <strong>Data Invio:</strong><br>
                                                <span class="badge bg-light-primary text-primary">{{ $dataInvio->format('d/m/Y') }}</span>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Data Scadenza:</strong><br>
                                                <span class="badge bg-light-warning text-warning">{{ $dataScadenza->format('d/m/Y') }}</span>
                                                <small class="text-muted"> (1 anno)</small>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Stato:</strong><br>
                                                <span class="badge bg-success">Attivo</span>
                                            </p>
                                        </div>
</div>

                                    @if($noteDeposito)
                                        <hr>
                                        <p class="mb-0">
                                            <strong>Note:</strong><br>
                                            <span class="text-muted">{{ $noteDeposito }}</span>
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                <strong>Prossimi passi dopo la creazione:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Verrai reindirizzato alla pagina di gestione del deposito</li>
                                    <li>Potrai aggiungere articoli e prodotti finiti dal pannello "Aggiungi Articoli"</li>
                                    <li>Potrai generare il DDT di invio quando pronto</li>
                                </ol>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiNuovoDepositoModal">
                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>
                            Annulla
                        </button>
                        
                        @if($stepCreazioneDeposito == 1)
                            <button type="button" 
                                    class="btn btn-primary btn-lg" 
                                    wire:click="vaiAdAnteprima"
                                    wire:loading.attr="disabled"
                                    @if(!$this->canContinue) disabled @endif>
                                <iconify-icon icon="solar:arrow-right-bold" class="me-1"></iconify-icon>
                                <span wire:loading.remove wire:target="vaiAdAnteprima">Continua → Anteprima</span>
                                <span wire:loading wire:target="vaiAdAnteprima">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Verifica...
                                </span>
                            </button>
                        @elseif($stepCreazioneDeposito == 2)
                            <button type="button" class="btn btn-outline-secondary" wire:click="tornaAllInfo">
                                <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon>
                                Indietro
                            </button>
                            <button type="button" 
                                    class="btn btn-primary btn-lg" 
                                    wire:click="creaDeposito"
                                    wire:loading.attr="disabled">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>
                                <span wire:loading.remove wire:target="creaDeposito">Crea Deposito</span>
                                <span wire:loading wire:target="creaDeposito">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Creazione in corso...
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal Dettaglio Deposito --}}
    @if($showDettaglioModal && $depositoSelezionato)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:box-bold-duotone" class="me-2"></iconify-icon>
                            Dettaglio Deposito {{ $depositoSelezionato->codice }}
                        </h5>
                        <button type="button" wire:click="chiudiDettaglioModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Informazioni generali --}}
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Informazioni Generali</h6>
                                <p><strong>Sede Mittente:</strong> {{ $depositoSelezionato->sedeMittente->nome }}</p>
                                <p><strong>Sede Destinataria:</strong> {{ $depositoSelezionato->sedeDestinataria->nome }}</p>
                                <p><strong>Data Invio:</strong> {{ $depositoSelezionato->data_invio->format('d/m/Y') }}</p>
                                <p><strong>Data Scadenza:</strong> {{ $depositoSelezionato->data_scadenza->format('d/m/Y') }}</p>
                                <p><strong>Stato:</strong> 
                                    <span class="badge bg-light-{{ $depositoSelezionato->stato_color }} text-{{ $depositoSelezionato->stato_color }}">
                                        {{ $depositoSelezionato->stato_label }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Statistiche</h6>
                                <p><strong>Articoli Inviati:</strong> {{ $depositoSelezionato->articoli_inviati }}</p>
                                <p><strong>Articoli Venduti:</strong> {{ $depositoSelezionato->articoli_venduti }}</p>
                                <p><strong>Articoli Rientrati:</strong> {{ $depositoSelezionato->articoli_rientrati }}</p>
                                <p><strong>Valore Totale:</strong> €{{ number_format($depositoSelezionato->valore_totale_invio, 2, ',', '.') }}</p>
                                <p><strong>Valore Venduto:</strong> €{{ number_format($depositoSelezionato->valore_venduto, 2, ',', '.') }}</p>
                            </div>
                        </div>

                        {{-- Movimenti --}}
                        @if($depositoSelezionato->movimenti->count() > 0)
                            @php
                                $movimentiPerTipo = $depositoSelezionato->movimenti
                                    ->sortBy('data_movimento')
                                    ->groupBy('tipo_movimento');
                                $sezioniMov = [
                                    'invio' => 'Invii',
                                    'vendita' => 'Vendite',
                                    'reso' => 'Resi',
                                    'rimando' => 'Rimandi',
                                ];
                            @endphp

                            <h6 class="fw-bold">Movimenti</h6>

                            @foreach($sezioniMov as $tipoMovimento => $titoloSezione)
                                @if(isset($movimentiPerTipo[$tipoMovimento]))
                                    <h6 class="small text-uppercase text-muted mt-3 mb-2">{{ $titoloSezione }}</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Articolo/PF</th>
                                                    <th>Qtà</th>
                                                    <th>Valore</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($movimentiPerTipo[$tipoMovimento] as $movimento)
                                                    <tr>
                                                        <td class="small">{{ optional($movimento->data_movimento)->format('d/m/Y') }}</td>
                                                        <td>
                                                            <span class="badge bg-light-{{ $movimento->tipo_movimento_color }} text-{{ $movimento->tipo_movimento_color }}">
                                                                {{ $movimento->tipo_movimento_label }}
                                                            </span>
                                                        </td>
                                                        <td class="small">
                                                            <strong>{{ $movimento->getCodiceItem() }}</strong><br>
                                                            {{ Str::limit($movimento->getDescrizioneItem(), 40) }}
                                                        </td>
                                                        <td>{{ $movimento->quantita }}</td>
                                                        <td class="small">{{ $movimento->costo_totale_formatted }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        {{-- Note --}}
                        @if($depositoSelezionato->note)
                            <div class="mt-3">
                                <h6 class="fw-bold">Note</h6>
                                <p class="text-muted">{{ $depositoSelezionato->note }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="chiudiDettaglioModal">
                            Chiudi
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>