<div @if($autoRefresh) wire:poll.15s @endif>
    @if (session()->has('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:info-circle-bold-duotone"></iconify-icon>
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(!$sessione)
        <div class="text-center py-5">
            <iconify-icon icon="solar:chart-2-bold-duotone" class="fs-1 text-muted"></iconify-icon>
            <h4 class="text-muted mt-3">Nessuna sessione selezionata</h4>
            <p class="text-muted">Apri una sessione di inventario per monitorare lo stato.</p>
            <a href="{{ route('inventario.sessioni') }}" class="btn btn-primary">
                <iconify-icon icon="solar:list-bold-duotone"></iconify-icon>
                Vai alle sessioni
            </a>
        </div>
    @else
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
            <div>
                <div class="text-uppercase text-muted small">Inventario</div>
                <h2 class="mb-1">{{ $sessione->nome }}</h2>
                <div class="text-muted small">
                    <span class="me-3"><strong>Sede:</strong> {{ $sessione->sede->nome }}</span>
                    <span class="me-3"><strong>Utente:</strong> {{ $sessione->utente->name }}</span>
                    <span class="me-3"><strong>Data:</strong> {{ $sessione->data_inizio->format('d/m/Y H:i') }}</span>
                    <span><strong>Stato:</strong> {{ ucfirst($sessione->stato) }}</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <div class="form-check form-switch align-self-center me-2">
                    <input class="form-check-input" type="checkbox" id="autoRefresh" wire:model.live="autoRefresh">
                    <label class="form-check-label small text-muted" for="autoRefresh">Auto refresh</label>
                </div>
                @if($sessione->stato !== 'attiva')
                    <button wire:click="riattivaSessione" class="btn btn-success btn-sm">
                        <iconify-icon icon="solar:refresh-bold-duotone"></iconify-icon>
                        Riattiva sessione
                    </button>
                @endif
                <button wire:click="exportFullAuditCsv" class="btn btn-dark btn-sm">
                    <iconify-icon icon="solar:download-bold-duotone"></iconify-icon>
                    Export audit
                </button>
                <a href="{{ route('inventario.scanner', $sessioneId) }}" class="btn btn-secondary btn-sm">
                    <iconify-icon icon="solar:scanner-bold-duotone"></iconify-icon>
                    Scanner
                </a>
                <button wire:click="prepareAllineaQuantita" class="btn btn-primary btn-sm">
                    <iconify-icon icon="solar:checklist-minimalistic-bold-duotone"></iconify-icon>
                    Allinea pezzi
                </button>
                <button wire:click="prepareFinalizzaInventario" class="btn btn-danger btn-sm">
                    <iconify-icon icon="solar:trash-bin-minimalistic-bold-duotone"></iconify-icon>
                    Fine inventario
                </button>
            </div>
        </div>
        <div class="alert alert-light border small text-muted">
            <div class="fw-semibold mb-1">Azioni rapide (cosa fanno)</div>
            <ul class="mb-0">
                <li>Auto refresh: aggiorna automaticamente i numeri mentre lavori.</li>
                <li>Export audit: scarica un report completo (anomalie, non scansionati, registro).</li>
                <li>Scanner: apre la pagina per scansionare i codici e registrare le quantita trovate.</li>
                <li>Allinea pezzi: aggiorna le giacenze del magazzino con le quantita trovate.</li>
                <li>Fine inventario: allinea le quantita e rimuove dal magazzino gli articoli non trovati.</li>
            </ul>
        </div>
        @if($lastFinalizzaReport)
            <div class="alert alert-success small">
                <div class="fw-semibold">Chiusura completata</div>
                <div>Allineati: {{ $lastFinalizzaReport['da_allineare'] }} · Rimossi: {{ $lastFinalizzaReport['da_rimuovere'] }}</div>
                <div class="mt-2">
                    <button wire:click="exportFinalizzaReport" class="btn btn-dark btn-sm">
                        Esporta report chiusura
                    </button>
                </div>
            </div>
        @endif

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button class="nav-link {{ $view === 'overview' ? 'active' : '' }}" wire:click="setView('overview')">
                    Panoramica
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $view === 'anomalie' ? 'active' : '' }}" wire:click="setView('anomalie')">
                    Anomalie
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $view === 'articoli' ? 'active' : '' }}" wire:click="setView('articoli')">
                    Articoli
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $view === 'kpi' ? 'active' : '' }}" wire:click="setView('kpi')">
                    Andamento
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $view === 'registro' ? 'active' : '' }}" wire:click="setView('registro')">
                    Registro
                </button>
            </li>
        </ul>

        @if($view === 'overview')
            <div class="alert alert-light border small text-muted">
                <div class="fw-semibold mb-1">Cosa puoi fare qui</div>
                <ul class="mb-0">
                    <li>Totali / Scansionati / Trovati / Eliminati: apri la lista filtrata con "Apri lista".</li>
                    <li>Non scansionati: individua cosa manca ancora da contare.</li>
                    <li>Progresso: misura la percentuale completata della sessione.</li>
                    <li>Valore magazzino: vedi il valore economico stimato per sede.</li>
                    <li>Per magazzino: confronta stato e differenze per ogni categoria.</li>
                </ul>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Metriche operative</th>
                            <th class="text-end">Valore</th>
                            <th>Azioni rapide</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <tr>
                            <td>Totali</td>
                            <td class="text-end fw-semibold">{{ $statistiche['articoli_totali'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('')">Mostra elenco</button></td>
                        </tr>
                        <tr>
                            <td>Scansionati</td>
                            <td class="text-end fw-semibold">{{ $statistiche['articoli_scansionati'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('')">Mostra elenco</button></td>
                        </tr>
                        <tr>
                            <td>Trovati</td>
                            <td class="text-end fw-semibold">{{ $statistiche['articoli_trovati'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('trovati')">Apri lista</button></td>
                        </tr>
                        <tr>
                            <td>Eliminati</td>
                            <td class="text-end fw-semibold">{{ $statistiche['articoli_eliminati'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('mancanti')">Apri lista</button></td>
                        </tr>
                        <tr>
                            <td>Mancanti</td>
                            <td class="text-end fw-semibold">{{ $statistiche['articoli_non_scansionati'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('non_scansionati')">Apri lista</button></td>
                        </tr>
                        <tr>
                            <td>Valore magazzino</td>
                            <td class="text-end fw-semibold">€ {{ number_format($statistiche['valore_magazzino'], 2, ',', '.') }}</td>
                            <td class="text-muted">—</td>
                        </tr>
                        <tr>
                            <td>Completamento</td>
                            <td class="text-end fw-semibold">{{ $statistiche['progresso'] }}%</td>
                            <td class="text-muted">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar" role="progressbar"
                     style="width: {{ $statistiche['progresso'] }}%"
                     aria-valuenow="{{ $statistiche['progresso'] }}"
                     aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Magazzino</th>
                            <th class="text-end">Totali</th>
                            <th class="text-end">Scansionati</th>
                            <th class="text-end">Mancanti</th>
                            <th class="text-end">Eccedenze</th>
                            <th class="text-end">Parziali</th>
                            <th class="text-end">Valore</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($statisticheMagazzini as $magazzino)
                            <tr>
                                <td>{{ $magazzino['nome'] }}</td>
                                <td class="text-end">{{ $magazzino['totali'] }}</td>
                                <td class="text-end">{{ $magazzino['scansionati'] }}</td>
                                <td class="text-end">{{ $magazzino['mancanti'] }}</td>
                                <td class="text-end">{{ $magazzino['eccedenze'] }}</td>
                                <td class="text-end">{{ $magazzino['parziali'] }}</td>
                                <td class="text-end">€ {{ number_format($magazzino['valore'], 2, ',', '.') }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-link p-0 text-decoration-none" wire:click="setCategoria({{ $magazzino['id'] }})">
                                            Apri
                                        </button>
                                        <button class="btn btn-link p-0 text-decoration-none" wire:click="prepareAllineaQuantita">
                                            Allinea
                                        </button>
                                        <button class="btn btn-link p-0 text-decoration-none text-danger" wire:click="prepareFinalizzaInventario">
                                            Fine
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($view === 'anomalie')
            <div class="alert alert-light border small text-muted">
                <div class="fw-semibold mb-1">Cosa puoi fare qui</div>
                <ul class="mb-0">
                    <li>Target: scegli il tipo di anomalia (incongruenze, eccedenze, parziali, mancanze).</li>
                    <li>Categoria: limita l'analisi a una sola categoria merceologica.</li>
                    <li>Regola: definisci come correggere le righe filtrate.</li>
                    <li>Diff min/max e Valore min/max: restringi il campo alle anomalie importanti.</li>
                    <li>Soglie criticita: definiscono quando una differenza è bassa, media o alta.</li>
                    <li>Heatmap: conta quante anomalie critiche ci sono per categoria.</li>
                    <li>Esempio: diff +12 con soglia alta 10 = criticita alta.</li>
                    <li>Applica regola: corregge in massa SOLO le righe filtrate.</li>
                    <li>Top anomalie / Report / Heatmap: analisi rapide e priorita di intervento.</li>
                    <li>Azioni consigliate: suggerimenti puntuali su singoli articoli con un click.</li>
                </ul>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div>
                    <label class="form-label small text-muted">Target</label>
                    <select wire:model.live="bulkTarget" class="form-select form-select-sm">
                        <option value="incongruenze">Incongruenze</option>
                        <option value="eccedenze">Eccedenze</option>
                        <option value="parziali">Parziali</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Categoria</label>
                    <select wire:model.live="categoriaId" class="form-select form-select-sm">
                        <option value="">Tutte</option>
                        @foreach($categorie as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Regola</label>
                    <select wire:model.live="bulkRule" class="form-select form-select-sm">
                        <option value="set_trovata_to_sistema">Imposta trovata = sistema</option>
                        <option value="set_trovata_zero">Imposta trovata = 0</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Diff min</label>
                    <input type="number" min="0" class="form-control form-control-sm" wire:model.live="bulkDiffMin" placeholder="0">
                </div>
                <div>
                    <label class="form-label small text-muted">Diff max</label>
                    <input type="number" min="0" class="form-control form-control-sm" wire:model.live="bulkDiffMax" placeholder="∞">
                </div>
                <div>
                    <label class="form-label small text-muted">Valore min (€)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="bulkValoreMin" placeholder="0">
                </div>
                <div>
                    <label class="form-label small text-muted">Valore max (€)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="bulkValoreMax" placeholder="∞">
                </div>
                <div>
                    <label class="form-label small text-muted" title="Soglia per le anomalie più critiche in heatmap">
                        Criticità alta (diff)
                    </label>
                    <input type="number" min="1" class="form-control form-control-sm" wire:model.live="heatmapDiffHigh">
                </div>
                <div>
                    <label class="form-label small text-muted" title="Soglia per le anomalie medie in heatmap">
                        Criticità media (diff)
                    </label>
                    <input type="number" min="1" class="form-control form-control-sm" wire:model.live="heatmapDiffMedium">
                </div>
                <button wire:click="prepareBulkResolution" class="btn btn-primary btn-sm">Applica regola (solo filtrati)</button>
                <button wire:click="exportAnomalieCsv" class="btn btn-dark btn-sm">Esporta anomalie</button>
            </div>
            <div class="small text-muted mb-3">
                Soglie heatmap: diff &lt; media = bassa, diff ≥ media = media, diff ≥ alta = alta.
                Esempio: media 3 e alta 10 → diff 2 = bassa, diff 5 = media, diff 12 = alta.
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tipologia</th>
                            <th class="text-end">Valore</th>
                            <th>Azioni rapide</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <tr>
                            <td>Incongruenze</td>
                            <td class="text-end fw-semibold">{{ $statistiche['scansioni_con_differenza'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('incongruenze')">Apri lista</button></td>
                        </tr>
                        <tr>
                            <td>Eccedenze</td>
                            <td class="text-end fw-semibold">{{ $statistiche['scansioni_con_eccesso'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('eccedenze')">Apri lista</button></td>
                        </tr>
                        <tr>
                            <td>Parziali</td>
                            <td class="text-end fw-semibold">{{ $statistiche['scansioni_parziali'] }}</td>
                            <td><button class="btn btn-link p-0 text-decoration-none" wire:click="setFiltroStat('parziali')">Apri lista</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="text-uppercase text-muted small mb-2">Top anomalie</div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Categoria</th>
                            <th class="text-end">Q.ta trovata</th>
                            <th class="text-end">Diff</th>
                            <th class="text-end">Valore</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($topAnomalie as $item)
                            @php
                                $valoreDiff = ($item->diff ?? 0) * ($item->costo_unitario ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $item->codice }}</td>
                                <td>{{ $item->descrizione }}</td>
                                <td>{{ $item->categoria ?? '-' }}</td>
                                <td class="text-end">{{ $item->quantita_trovata }}</td>
                                <td class="text-end">{{ $item->diff > 0 ? '+' : '' }}{{ $item->diff }}</td>
                                <td class="text-end">€ {{ number_format($valoreDiff, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Nessuna anomalia.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="text-uppercase text-muted small mb-2">Report per categoria</div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th class="text-end">Righe</th>
                            <th class="text-end">Eccedenze</th>
                            <th class="text-end">Mancanze</th>
                            <th class="text-end">Diff totale</th>
                            <th class="text-end">Valore diff</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($reportAnomalie as $row)
                            <tr>
                                <td>{{ $row->categoria ?? 'Senza categoria' }}</td>
                                <td class="text-end">{{ $row->righe }}</td>
                                <td class="text-end">{{ $row->eccedenze }}</td>
                                <td class="text-end">{{ $row->mancanze }}</td>
                                <td class="text-end">{{ $row->diff_totale }}</td>
                                <td class="text-end">€ {{ number_format($row->valore_diff, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Nessun dato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="text-uppercase text-muted small mb-2">Heatmap criticità per categoria</div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th class="text-end">Critiche</th>
                            <th class="text-end">Medie</th>
                            <th class="text-end">Basse</th>
                            <th class="text-end">Totali</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($heatmapAnomalie as $row)
                            <tr>
                                <td>{{ $row->categoria ?? 'Senza categoria' }}</td>
                                <td class="text-end">{{ $row->critiche }}</td>
                                <td class="text-end">{{ $row->medie }}</td>
                                <td class="text-end">{{ $row->basse }}</td>
                                <td class="text-end">{{ $row->totali }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Nessun dato disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="text-uppercase text-muted small mb-2">Priorità assolute (valore economico)</div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Categoria</th>
                            <th class="text-end">Q.ta trovata</th>
                            <th class="text-end">Diff</th>
                            <th class="text-end">Valore diff</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($topAnomalieValore as $item)
                            <tr>
                                <td>{{ $item->codice }}</td>
                                <td>{{ $item->descrizione }}</td>
                                <td>{{ $item->categoria ?? '-' }}</td>
                                <td class="text-end">{{ $item->quantita_trovata }}</td>
                                <td class="text-end">{{ $item->diff > 0 ? '+' : '' }}{{ $item->diff }}</td>
                                <td class="text-end">€ {{ number_format($item->valore_diff, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Nessun dato disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="text-uppercase text-muted small mb-2">Azioni consigliate</div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Categoria</th>
                            <th class="text-end">Diff</th>
                            <th class="text-end">Valore diff</th>
                            <th>Azione</th>
                            <th class="text-end">Esegui</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($azioniConsigliate as $item)
                            <tr>
                                <td>{{ $item->codice }}</td>
                                <td>{{ $item->descrizione }}</td>
                                <td>{{ $item->categoria ?? '-' }}</td>
                                <td class="text-end">{{ $item->diff > 0 ? '+' : '' }}{{ $item->diff }}</td>
                                <td class="text-end">€ {{ number_format($item->valore_diff, 2, ',', '.') }}</td>
                                <td>
                                    @php
                                        $actionKey = $item->diff > 0 ? 'sistema' : (($item->quantita_trovata ?? 0) > 0 ? 'sistema' : 'zero');
                                        $actionLabel = $actionKey === 'zero'
                                            ? 'Consigliata: segna come non trovato'
                                            : 'Consigliata: allinea alla giacenza';
                                        $reasonLabel = $item->diff > 0
                                            ? 'Eccedenza'
                                            : (($item->quantita_trovata ?? 0) > 0 ? 'Parziale' : 'Mancanza');
                                    @endphp
                                    <span class="badge text-bg-light">{{ $actionLabel }}</span>
                                    <div class="small text-muted mt-1">
                                        {{ $reasonLabel }} · differenza {{ $item->diff > 0 ? '+' : '' }}{{ $item->diff }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-link p-0 text-decoration-none"
                                            wire:click="prepareSuggestedAction({{ $item->articolo_id }}, '{{ $actionKey }}')">
                                        Applica suggerimento
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Nessun dato disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($view === 'articoli')
            <div class="alert alert-light border small text-muted">
                <div class="fw-semibold mb-1">Cosa puoi fare qui</div>
                <ul class="mb-0">
                    <li>Filtra per categoria e stato per lavorare su un sottoinsieme preciso.</li>
                    <li>Controllo coerenza inventario: verifica che numeri e scansioni siano allineati.</li>
                    <li>Confronto inventario vs giacenze: confronta totali della sessione con le giacenze reali.</li>
                    <li>Inventaria filtrati: segna come trovati tutti gli articoli attualmente filtrati.</li>
                    <li>Modifica: aggiorna quantita trovata e sistema per il singolo articolo.</li>
                    <li>Inventariato: marca un articolo come correttamente inventariato.</li>
                    <li>Export: scarica le liste per analisi esterne.</li>
                </ul>
            </div>
            @if($actionFeedback || $lastActionMessage)
                <div class="alert alert-success small" role="alert" id="articoli-feedback">
                    @if($actionFeedback)
                        <div>{{ $actionFeedback }}</div>
                    @endif
                    @if($lastActionMessage)
                        <div class="text-muted">{{ $lastActionMessage }}</div>
                    @endif
                </div>
            @endif
            <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div>
                    <label class="form-label small text-muted">Categoria</label>
                    <select wire:model.live="categoriaId" class="form-select form-select-sm">
                        <option value="">Tutte le categorie</option>
                        @foreach($categorie as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Stato</label>
                    <select wire:model.live="statoArticolo" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="trovati">Trovati</option>
                        <option value="mancanti">Eliminati</option>
                        <option value="non_scansionati">Non Scansionati</option>
                        <option value="eccedenze">Eccedenze</option>
                        <option value="parziali">Parziali</option>
                        <option value="incongruenze">Incongruenze</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Base confronto</label>
                    <select wire:model.live="baseConfronto" class="form-select form-select-sm">
                        <option value="scansione">Sistema (al momento scansione)</option>
                        <option value="giacenza">Giacenza attuale</option>
                    </select>
                </div>
                <button wire:click="filtraArticoli" class="btn btn-primary btn-sm">Filtra</button>
                <button wire:click="resetFiltri" class="btn btn-secondary btn-sm">Reset filtri</button>
                <button wire:click="verificaDati" class="btn btn-info btn-sm">Controllo coerenza inventario</button>
                <button wire:click="confrontaConArticoli" class="btn btn-warning btn-sm">Confronto inventario vs giacenze</button>
                <button wire:click="bulkMarkInventariato" class="btn btn-success btn-sm" wire:loading.attr="disabled" wire:target="bulkMarkInventariato">
                    <span wire:loading wire:target="bulkMarkInventariato">Inventariazione in corso...</span>
                    <span wire:loading.remove wire:target="bulkMarkInventariato">Inventaria filtrati (segna trovati)</span>
                </button>
                <button wire:click="exportCsv" class="btn btn-dark btn-sm">Esporta lista</button>
                <button wire:click="exportNonScansionatiCsv" class="btn btn-dark btn-sm">Esporta non scansionati</button>
            </div>

            <div class="small text-muted mb-2">
                Controllo coerenza inventario: verifica che conteggi, giacenze e scansioni siano allineati
                e segnala eventuali anomalie nei numeri. Confronto inventario vs giacenze: confronta il totale
                degli articoli con giacenza con quelli della sessione inventario.
            </div>
            <div class="table-responsive" id="inventario-articoli">
                <table class="table table-striped table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Categoria</th>
                            <th class="text-end">Q.ta sistema</th>
                            <th class="text-end">Q.ta trovata</th>
                            <th class="text-end">Diff.</th>
                            <th>Esito</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($articoli as $articolo)
                            @php
                                $scansione = $articolo->scansioni()
                                    ->where('sessione_id', $sessioneId)
                                    ->first();
                                $quantitaSistemaGiacenza = $articolo->giacenze
                                    ->where('sede_id', $sessione->sede_id)
                                    ->sum('quantita_residua');
                                $quantitaSistema = $baseConfronto === 'giacenza'
                                    ? $quantitaSistemaGiacenza
                                    : ($scansione?->quantita_sistema ?? null);
                                $quantitaTrovata = $scansione?->quantita_trovata;
                                $diff = $quantitaSistema !== null && $quantitaTrovata !== null
                                    ? ($quantitaTrovata - $quantitaSistema)
                                    : null;
                            @endphp
                            <tr>
                                <td>{{ $articolo->codice }}</td>
                                <td>{{ $articolo->descrizione }}</td>
                                <td>{{ $articolo->categoriaMerceologica->nome ?? '-' }}</td>
                                <td class="text-end">{{ $quantitaSistema ?? '-' }}</td>
                                <td class="text-end">{{ $quantitaTrovata ?? '-' }}</td>
                                <td class="text-end">
                                    @if($diff !== null)
                                        {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if(!$scansione)
                                        Non scansionato
                                    @elseif(($scansione->quantita_trovata ?? 0) > 0)
                                        Trovato
                                    @else
                                        Assente
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-link p-0 text-decoration-none" wire:click="openEditArticolo({{ $articolo->id }})">
                                        Modifica
                                    </button>
                                    <button class="btn btn-link p-0 text-decoration-none ms-2"
                                            wire:click="markInventariato({{ $articolo->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markInventariato({{ $articolo->id }})">
                                        <span wire:loading wire:target="markInventariato({{ $articolo->id }})">Salvataggio...</span>
                                        <span wire:loading.remove wire:target="markInventariato({{ $articolo->id }})">Inventariato</span>
                                    </button>
                                    @if($lastActionArticoloId === $articolo->id)
                                        <span class="badge text-bg-success ms-2">Fatto</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $articoli->links() }}
            </div>
        @endif

        @if($view === 'kpi')
            <div class="alert alert-light border small text-muted">
                <div class="fw-semibold mb-1">Cosa puoi fare qui</div>
                <ul class="mb-0">
                    <li>Totali periodo: riepilogo complessivo delle scansioni e delle differenze.</li>
                    <li>Andamento giornaliero: vedi progressi e criticita giorno per giorno.</li>
                    <li>Andamento per categoria: capisci dove si concentra la differenza economica.</li>
                </ul>
            </div>
            @php
                $totScansioni = $kpiTimeline->sum('scansioni');
                $totTrovati = $kpiTimeline->sum('trovati');
                $totEliminati = $kpiTimeline->sum('eliminati');
                $totDiff = $kpiTimeline->sum('diff_totale');
                $totValore = $kpiTimeline->sum('valore_diff');
            @endphp

            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Totali periodo</th>
                            <th class="text-end">Scansioni</th>
                            <th class="text-end">Trovati</th>
                            <th class="text-end">Eliminati</th>
                            <th class="text-end">Diff totale</th>
                            <th class="text-end">Valore diff</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <tr>
                            <td>—</td>
                            <td class="text-end">{{ $totScansioni }}</td>
                            <td class="text-end">{{ $totTrovati }}</td>
                            <td class="text-end">{{ $totEliminati }}</td>
                            <td class="text-end">{{ $totDiff }}</td>
                            <td class="text-end">€ {{ number_format($totValore, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Giorno</th>
                            <th class="text-end">Scansioni</th>
                            <th class="text-end">Trovati</th>
                            <th class="text-end">Eliminati</th>
                            <th class="text-end">Diff totale</th>
                            <th class="text-end">Valore diff</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($kpiTimeline as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row->giorno)->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $row->scansioni }}</td>
                                <td class="text-end">{{ $row->trovati }}</td>
                                <td class="text-end">{{ $row->eliminati }}</td>
                                <td class="text-end">{{ $row->diff_totale }}</td>
                                <td class="text-end">€ {{ number_format($row->valore_diff, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Nessun dato disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mt-4">
                <div class="text-uppercase text-muted small mb-2">Andamento per categoria</div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th class="text-end">Scansioni</th>
                            <th class="text-end">Trovati</th>
                            <th class="text-end">Eliminati</th>
                            <th class="text-end">Diff totale</th>
                            <th class="text-end">Valore diff</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($kpiPerCategoria as $row)
                            <tr>
                                <td>{{ $row->categoria ?? 'Senza categoria' }}</td>
                                <td class="text-end">{{ $row->scansioni }}</td>
                                <td class="text-end">{{ $row->trovati }}</td>
                                <td class="text-end">{{ $row->eliminati }}</td>
                                <td class="text-end">{{ $row->diff_totale }}</td>
                                <td class="text-end">€ {{ number_format($row->valore_diff, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Nessun dato disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($view === 'registro')
            <div class="alert alert-light border small text-muted">
                <div class="fw-semibold mb-1">Cosa puoi fare qui</div>
                <ul class="mb-0">
                    <li>Vedi tutte le azioni svolte sull'inventario con data e utente.</li>
                    <li>Controlla dettagli delle modifiche e delle correzioni applicate.</li>
                    <li>Esporta il registro per audit o condivisione.</li>
                </ul>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <button wire:click="exportRegistroCsv" class="btn btn-dark btn-sm">Esporta registro</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Quando</th>
                            <th>Tipo</th>
                            <th>Articolo</th>
                            <th>Utente</th>
                            <th>Dettagli</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($eventi as $evento)
                            <tr>
                                <td>{{ $evento->created_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ str_replace('_', ' ', $evento->tipo) }}</td>
                                <td>
                                    @if($evento->articolo)
                                        {{ $evento->articolo->codice }} - {{ $evento->articolo->descrizione }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $evento->utente->name ?? '-' }}</td>
                                <td class="text-muted">
                                    @if(!empty($evento->payload))
                                        {{ json_encode($evento->payload) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nessun evento registrato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('scroll-to-articoli', () => {
                    const target = document.getElementById('inventario-articoli');
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
                Livewire.on('scroll-to-feedback', () => {
                    const target = document.getElementById('articoli-feedback');
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });

                const getModalId = (type) => {
                    if (type === 'allinea') return 'confirmAllineaModal';
                    if (type === 'bulk') return 'confirmBulkModal';
                    if (type === 'azione') return 'confirmAzioneModal';
                    if (type === 'edit') return 'editArticoloModal';
                    return 'confirmFinalizzaModal';
                };

                Livewire.on('open-confirm-modal', ({ type }) => {
                    const el = document.getElementById(getModalId(type));
                    if (el) {
                        const modal = new bootstrap.Modal(el);
                        modal.show();
                    }
                });

                Livewire.on('close-confirm-modal', ({ type }) => {
                    const el = document.getElementById(getModalId(type));
                    if (el) {
                        const modal = bootstrap.Modal.getInstance(el);
                        if (modal) {
                            modal.hide();
                        }
                    }
                });
            });
        </script>
    @endif

    <div class="modal fade" id="confirmAllineaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma allineamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Stai per aggiornare le quantità di magazzino usando le quantità trovate.</p>
                    <ul class="small text-muted mb-0">
                        <li>Righe da allineare: <strong>{{ $previewAllinea }}</strong></li>
                        <li>Magazzino: <strong>{{ $sessione->sede->nome ?? '' }}</strong></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmAllineaQuantita">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmFinalizzaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma fine inventario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Questa operazione allinea le quantità e rimuove gli articoli mancanti.</p>
                    <ul class="small text-muted mb-0">
                        <li>Righe da allineare: <strong>{{ $previewFinalizza['da_allineare'] }}</strong></li>
                        <li>Articoli da rimuovere: <strong>{{ $previewFinalizza['da_rimuovere'] }}</strong></li>
                        <li>Magazzino: <strong>{{ $sessione->sede->nome ?? '' }}</strong></li>
                    </ul>
                    <div class="mt-3" wire:loading wire:target="confirmFinalizzaInventario">
                        <div class="small text-muted mb-2">Chiusura in corso... sto allineando e rimuovendo gli articoli.</div>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmFinalizzaInventario" wire:loading.attr="disabled">
                        <span wire:loading wire:target="confirmFinalizzaInventario">Chiusura in corso...</span>
                        <span wire:loading.remove wire:target="confirmFinalizzaInventario">Conferma</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmBulkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma risoluzione massiva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Stai per aggiornare le anomalie selezionate.</p>
                    <ul class="small text-muted mb-0">
                        <li>Target: <strong>{{ $bulkTarget }}</strong></li>
                        <li>Regola: <strong>{{ $bulkRule }}</strong></li>
                        <li>Categoria: <strong>{{ $categoriaId ?: 'Tutte' }}</strong></li>
                        <li>Diff min: <strong>{{ $bulkDiffMin === null || $bulkDiffMin === '' ? '—' : $bulkDiffMin }}</strong></li>
                        <li>Diff max: <strong>{{ $bulkDiffMax === null || $bulkDiffMax === '' ? '—' : $bulkDiffMax }}</strong></li>
                        <li>Valore min: <strong>{{ $bulkValoreMin === null || $bulkValoreMin === '' ? '—' : $bulkValoreMin }}</strong></li>
                        <li>Valore max: <strong>{{ $bulkValoreMax === null || $bulkValoreMax === '' ? '—' : $bulkValoreMax }}</strong></li>
                        <li>Righe coinvolte: <strong>{{ $bulkPreviewCount }}</strong></li>
                        <li>Base confronto: <strong>{{ $baseConfronto }}</strong></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmBulkResolution">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmAzioneModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma azione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Vuoi applicare l'azione suggerita all'articolo selezionato?</p>
                    <ul class="small text-muted mb-0">
                        <li>Articolo ID: <strong>{{ $pendingArticoloId }}</strong></li>
                        <li>Azione: <strong>{{ $pendingAction }}</strong></li>
                        <li>Base confronto: <strong>{{ $baseConfronto }}</strong></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmSuggestedAction">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editArticoloModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica quantità</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" wire:click="closeEditArticolo"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Quantità trovata</label>
                        <input type="number" min="0" class="form-control" wire:model.defer="editingQuantitaTrovata">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantità sistema (giacenza)</label>
                        <input type="number" min="0" class="form-control" wire:model.defer="editingQuantitaSistema">
                    </div>
                    <p class="small text-muted mb-0">
                        Questa modifica aggiorna la scansione e la giacenza per il magazzino corrente.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" wire:click="closeEditArticolo">Annulla</button>
                    <button type="button" class="btn btn-primary" wire:click="saveEditArticolo">Salva</button>
                </div>
            </div>
        </div>
    </div>
</div>
