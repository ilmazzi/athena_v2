<div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <iconify-icon icon="solar:list-bold-duotone"></iconify-icon>
                        Sessioni Inventario
                    </h3>
                    <button wire:click="apriModal" class="btn btn-primary">
                        <iconify-icon icon="solar:add-circle-bold-duotone"></iconify-icon>
                        Nuova Sessione
                    </button>
                </div>
                <div class="card-body">
                    <!-- Filtri -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Filtra per Sede:</label>
                            <select wire:model.live="filtroSede" class="form-select">
                                <option value="">Tutte le sedi</option>
                                @foreach($sedi as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filtra per Stato:</label>
                            <select wire:model.live="filtroStato" class="form-select">
                                <option value="">Tutti gli stati</option>
                                <option value="attiva">Attiva</option>
                                <option value="chiusa">Chiusa</option>
                                <option value="annullata">Annullata</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button wire:click="aggiornaLista" class="btn btn-primary">
                                <iconify-icon icon="solar:refresh-bold-duotone"></iconify-icon>
                                Aggiorna
                            </button>
                        </div>
                    </div>

                    <!-- Tabella Sessioni -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Sede</th>
                                    <th>Utente</th>
                                    <th>Data Inizio</th>
                                    <th>Stato</th>
                                    <th>Progresso</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessioni as $sessione)
                                    <tr>
                                        <td>
                                            <strong>{{ $sessione->nome }}</strong>
                                            @if($sessione->note)
                                                <br><small class="text-muted">{{ Str::limit($sessione->note, 50) }}</small>
                                            
                                        </td>
                                        <td>{{ $sessione->sede->nome }}</td>
                                        <td>{{ $sessione->utente->name }}</td>
                                        <td>{{ $sessione->data_inizio->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <span class="badge bg-{{ $sessione->stato === 'attiva' ? 'success' : ($sessione->stato === 'chiusa' ? 'primary' : 'danger') }}">
                                                {{ ucfirst($sessione->stato) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($sessione->stato === 'attiva')
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: {{ $sessione->progresso }}%"
                                                         aria-valuenow="{{ $sessione->progresso }}" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        {{ $sessione->progresso }}%
                                                    </div>
                                                </div>
                                            @else
                                                <small class="text-muted">
                                                    Trovati: {{ $sessione->articoli_trovati }}<br>
                                                    Eliminati: {{ $sessione->articoli_eliminati }}
                                                </small>
                                            
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button wire:click="visualizzaDettagli({{ $sessione->id }})" 
                                                        class="btn btn-sm btn-info">
                                                    <iconify-icon icon="solar:eye-bold-duotone"></iconify-icon>
                                                    Dettagli
                                                </button>
                                                @if($sessione->stato === 'attiva')
                                                    <a href="{{ route('inventario.scanner', $sessione->id) }}" 
                                                       class="btn btn-sm btn-primary">
                                                        <iconify-icon icon="solar:scanner-bold-duotone"></iconify-icon>
                                                        Scanner
                                                    </a>
                                                    <a href="{{ route('inventario.monitor', $sessione->id) }}" 
                                                       class="btn btn-sm btn-info">
                                                        <iconify-icon icon="solar:chart-2-bold-duotone"></iconify-icon>
                                                        Monitor
                                                    </a>
                                                    <button wire:click="chiudiSessione({{ $sessione->id }})" 
                                                            class="btn btn-sm btn-success"
                                                            onclick="return confirm('Sei sicuro di voler chiudere questa sessione? Gli articoli non trovati verranno eliminati.')">
                                                        <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                                                        Chiudi
                                                    </button>
                                                    <button wire:click="annullaSessione({{ $sessione->id }})" 
                                                            class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Sei sicuro di voler annullare questa sessione? Tutte le scansioni verranno eliminate.')">
                                                        <iconify-icon icon="solar:close-circle-bold-duotone"></iconify-icon>
                                                        Annulla
                                                    </button>
                                                @else
                                                    <a href="{{ route('inventario.report', $sessione->id) }}" 
                                                       class="btn btn-sm btn-primary">
                                                        <iconify-icon icon="solar:document-text-bold-duotone"></iconify-icon>
                                                        Report
                                                    </a>
                                                
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginazione -->
                    <div class="d-flex justify-content-center">
                        {{ $sessioni->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Creazione Sessione -->
    @if($showModal)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:add-circle-bold-duotone"></iconify-icon>
                            Nuova Sessione Inventario
                        </h5>
                        <button type="button" wire:click="chiudiModal" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="creaSessione">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Nome Sessione *</label>
                                    <input type="text" wire:model="nome" class="form-control" placeholder="Es: Inventario Gennaio 2024">
                                    @error('nome') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sede *</label>
                                    <select wire:model="sedeId" class="form-select">
                                        <option value="">Seleziona sede</option>
                                        @foreach($sedi as $sede)
                                            <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                                        @endforeach
                                    </select>
                                    @error('sedeId') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="form-label">Magazzini da Inventariare</label>
                                    <div class="row">
                                        @foreach($categorie as $categoria)
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           wire:model="categorieSelezionate" 
                                                           value="{{ $categoria->id }}" 
                                                           id="categoria_{{ $categoria->id }}">
                                                    <label class="form-check-label" for="categoria_{{ $categoria->id }}">
                                                        {{ $categoria->nome }}

                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">Lascia vuoto per inventariare tutte le categorie</small>
                                </div>
</div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="form-label">Note</label>
                                    <textarea wire:model="note" class="form-control" rows="3" 
                                              placeholder="Note aggiuntive per la sessione..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="chiudiModal" class="btn btn-secondary">Annulla</button>
                        <button type="button" wire:click="creaSessione" class="btn btn-primary">
                            <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                            Crea Sessione
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    

    <!-- Modal Dettagli Sessione -->
    @if($showModalDettagli && $sessioneSelezionata)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:eye-bold-duotone"></iconify-icon>
                            Dettagli Sessione Inventario
                        </h5>
                        <button type="button" wire:click="chiudiModalDettagli" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Informazioni Sessione</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Nome:</strong></td>
                                        <td>{{ $sessioneSelezionata->nome }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sede:</strong></td>
                                        <td>{{ $sessioneSelezionata->sede->nome }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Utente:</strong></td>
                                        <td>{{ $sessioneSelezionata->utente->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Stato:</strong></td>
                                        <td>
                                            <span class="badge bg-{{ $sessioneSelezionata->stato === 'attiva' ? 'success' : ($sessioneSelezionata->stato === 'chiusa' ? 'primary' : 'danger') }}">
                                                {{ ucfirst($sessioneSelezionata->stato) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Data Inizio:</strong></td>
                                        <td>{{ $sessioneSelezionata->data_inizio->format('d/m/Y H:i:s') }}</td>
                                    </tr>
                                    @if($sessioneSelezionata->data_fine)
                                        <tr>
                                            <td><strong>Data Fine:</strong></td>
                                            <td>{{ $sessioneSelezionata->data_fine->format('d/m/Y H:i:s') }}</td>
                                        </tr>
                                    
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Statistiche</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Articoli Totali:</strong></td>
                                        <td>{{ $sessioneSelezionata->articoli_totali }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Articoli Trovati:</strong></td>
                                        <td>{{ $sessioneSelezionata->articoli_trovati }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Articoli Eliminati:</strong></td>
                                        <td>{{ $sessioneSelezionata->articoli_eliminati }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Valore Eliminato:</strong></td>
                                        <td>€{{ number_format($sessioneSelezionata->valore_eliminato, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Progresso:</strong></td>
                                        <td>{{ $sessioneSelezionata->progresso }}%</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Categorie Selezionate -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6>Magazzini da Inventariare</h6>
@if($sessioneSelezionata->categorie_permesse && count($sessioneSelezionata->categorie_permesse) > 0)
    <div class="row">
        @foreach($sessioneSelezionata->categorie_permesse as $categoriaId)
            @php
                $magazzinoLogico = app(\App\Services\MagazzinoLogicoService::class)->resolveFromCategoriaId((int) $categoriaId) ?? (int) $categoriaId;
            @endphp
            <div class="col-md-4">
                <span class="badge bg-primary">{{ 'Magazzino ' . $magazzinoLogico }}</span>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted">Tutti i magazzini</p>
@endif

                                
                                
                            </div>
                        </div>

                        <!-- Note -->
                        @if($sessioneSelezionata->note)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6>Note</h6>
                                    <p class="text-muted">{{ $sessioneSelezionata->note }}</p>
                                </div>
                            </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="chiudiModalDettagli" class="btn btn-secondary">Chiudi</button>
                        <button wire:click="visualizzaScansioni({{ $sessioneSelezionata->id }})" class="btn btn-info">
                            <iconify-icon icon="solar:list-bold-duotone"></iconify-icon>
                            Visualizza Scansioni
                        </button>
                        @if($sessioneSelezionata->stato === 'attiva')
                            <a href="{{ route('inventario.monitor', $sessioneSelezionata->id) }}" class="btn btn-info">
                                <iconify-icon icon="solar:chart-2-bold-duotone"></iconify-icon>
                                Monitor
                            </a>
                            <a href="{{ route('inventario.scanner', $sessioneSelezionata->id) }}" class="btn btn-primary">
                                <iconify-icon icon="solar:scanner-bold-duotone"></iconify-icon>
                                Scanner
                            </a>
                        
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    
</div>