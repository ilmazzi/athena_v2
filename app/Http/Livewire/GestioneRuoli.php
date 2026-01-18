<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class GestioneRuoli extends Component
{
    use WithPagination;

    public $search = '';
    public $editingRole = null;
    public $name = '';
    public $permissions = [];

    protected $rules = [
        'name' => 'required|string|max:50',
        'permissions' => 'array',
    ];

    public function create()
    {
        $this->reset(['editingRole', 'name', 'permissions']);
        $this->editingRole = 0;
    }

    public function edit($roleId)
    {
        $role = Role::findOrFail($roleId);
        $this->editingRole = $role->id;
        $this->name = $role->name;
        $this->permissions = $role->permissions()->pluck('name')->toArray();
    }

    public function save()
    {
        $this->validate();
        if ($this->editingRole) {
            $role = Role::findOrFail($this->editingRole);
            $role->name = $this->name;
            $role->save();
        } else {
            $role = Role::create(['name' => $this->name]);
        }
        $role->syncPermissions($this->permissions);
        session()->flash('success', 'Ruolo salvato');
        $this->editingRole = null;
    }

    public function delete($roleId)
    {
        $role = Role::findOrFail($roleId);
        $role->delete();
        session()->flash('success', 'Ruolo eliminato');
    }

    public function render()
    {
        $roles = Role::query()
            ->when($this->search, fn($q)=>$q->where('name', 'like', "%{$this->search}%"))
            ->paginate(15);
        $allPerms = Permission::all()->pluck('name')->toArray();
        return view('livewire.gestione-ruoli', [
            'ruoli' => $roles,
            'tuttiPermessi' => $allPerms,
        ]);
    }
}




