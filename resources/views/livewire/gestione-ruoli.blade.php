<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Gestione Ruoli</h4>
        <div class="d-flex align-items-center gap-2">
            @if (session()->has('success'))
                <span class="text-success">{{ session('success') }}</span>
            @endif
            <button class="btn btn-primary btn-sm" wire:click="create">Nuovo Ruolo</button>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" placeholder="Cerca ruolo" wire:model.debounce.300ms="search">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nome</th>
                        <th>Permessi</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ruoli as $r)
                        <tr>
                            <td>{{ $r->name }}</td>
                            <td>{{ implode(', ', $r->permissions->pluck('name')->toArray()) }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-light" wire:click="edit({{ $r->id }})">Modifica</button>
                                    <button class="btn btn-danger" wire:click="delete({{ $r->id }})">Elimina</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $ruoli->links() }}</div>
    </div>

    @if($editingRole !== null)
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingRole ? 'Modifica Ruolo' : 'Nuovo Ruolo' }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('editingRole', null)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" wire:model.defer="name">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Permessi</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($tuttiPermessi as $p)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $p }}" wire:model="permissions" id="perm-{{ $p }}">
                                        <label class="form-check-label" for="perm-{{ $p }}">{{ $p }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="$set('editingRole', null)">Annulla</button>
                        <button class="btn btn-primary" wire:click="save">Salva</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>




