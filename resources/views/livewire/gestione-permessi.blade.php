<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Gestione Permessi</h4>
        <div class="d-flex align-items-center gap-2">
            @if (session()->has('success'))
                <span class="text-success">{{ session('success') }}</span>
            @endif
            <button class="btn btn-primary btn-sm" wire:click="create">Nuovo Permesso</button>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" placeholder="Cerca permesso" wire:model.debounce.300ms="search">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nome</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($permessi as $p)
                        <tr>
                            <td>{{ $p->name }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-light" wire:click="edit({{ $p->id }})">Modifica</button>
                                    <button class="btn btn-danger" wire:click="delete({{ $p->id }})">Elimina</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $permessi->links() }}</div>
    </div>

    @if($editingPerm !== null)
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingPerm ? 'Modifica Permesso' : 'Nuovo Permesso' }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('editingPerm', null)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" wire:model.defer="name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="$set('editingPerm', null)">Annulla</button>
                        <button class="btn btn-primary" wire:click="save">Salva</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>




