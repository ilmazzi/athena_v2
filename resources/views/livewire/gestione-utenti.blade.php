<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Gestione Utenti</h4>
        <div class="d-flex align-items-center gap-2">
            @if (session()->has('success'))
                <span class="text-success">{{ session('success') }}</span>
            @endif
            <button class="btn btn-primary btn-sm" wire:click="create">
                <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>
                Nuovo Utente
            </button>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" placeholder="Cerca nome o email" wire:model.debounce.300ms="search">
        </div>
        <div class="col-md-4">
            <select class="form-select" wire:model="sedeId">
                <option value="">Tutte le sedi</option>
                @foreach($sedi as $s)
                    <option value="{{ $s->id }}">{{ $s->nome }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Sede</th>
                        <th>Ruoli</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($utenti as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->sede->nome ?? '-' }}</td>
                            <td>{{ implode(', ', $u->roles->pluck('name')->toArray()) }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-light" wire:click="edit({{ $u->id }})">Modifica</button>
                                    @if($u->deleted_at)
                                        <button class="btn btn-success" wire:click="restore({{ $u->id }})">Ripristina</button>
                                        <button class="btn btn-danger" wire:click="forceDelete({{ $u->id }})">Elimina</button>
                                    @else
                                        <button class="btn btn-warning" wire:click="delete({{ $u->id }})">Disabilita</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $utenti->links() }}</div>
    </div>

    @if($editingUser !== null)
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingUser ? 'Modifica Utente' : 'Nuovo Utente' }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('editingUser', null)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" wire:model.defer="name">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" wire:model.defer="email">
                        </div>
                        @if(!$editingUser)
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="invite" wire:model="invite">
                                <label class="form-check-label" for="invite">Invia email per impostare password</label>
                            </div>
                            @if(!$invite)
                                <div class="mb-2">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" wire:model.defer="password">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Conferma Password</label>
                                    <input type="password" class="form-control" wire:model.defer="password_confirmation">
                                </div>
                            @endif
                        @else
                            <div class="mb-2">
                                <label class="form-label">Nuova Password</label>
                                <input type="password" class="form-control" wire:model.defer="password" placeholder="Lascia vuoto per non cambiare">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Conferma Password</label>
                                <input type="password" class="form-control" wire:model.defer="password_confirmation">
                            </div>
                        @endif
                        <div class="mb-2">
                            <label class="form-label">Sede</label>
                            <select class="form-select" wire:model.defer="sede_id">
                                <option value="">-- Nessuna (Admin) --</option>
                                @foreach($sedi as $s)
                                    <option value="{{ $s->id }}">{{ $s->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Ruoli</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($tuttiRuoli as $r)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $r }}" wire:model="roles" id="role-{{ $r }}">
                                        <label class="form-check-label" for="role-{{ $r }}">{{ $r }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Permessi diretti</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($tuttiPermessi as $p)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $p }}" wire:model="directPermissions" id="perm-{{ $p }}">
                                        <label class="form-check-label" for="perm-{{ $p }}">{{ $p }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="$set('editingUser', null)">Annulla</button>
                        <button class="btn btn-primary" wire:click="save">Salva</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>


