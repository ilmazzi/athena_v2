<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

class GestionePermessi extends Component
{
    use WithPagination;

    public $search = '';
    public $editingPerm = null;
    public $name = '';

    protected $rules = [
        'name' => 'required|string|max:100'
    ];

    public function create()
    {
        $this->editingPerm = 0;
        $this->name = '';
    }

    public function edit($id)
    {
        $p = Permission::findOrFail($id);
        $this->editingPerm = $p->id;
        $this->name = $p->name;
    }

    public function save()
    {
        $this->validate();
        if ($this->editingPerm) {
            $p = Permission::findOrFail($this->editingPerm);
            $p->name = $this->name;
            $p->save();
        } else {
            Permission::firstOrCreate(['name' => $this->name]);
        }
        session()->flash('success', 'Permesso salvato');
        $this->editingPerm = null;
    }

    public function delete($id)
    {
        Permission::findOrFail($id)->delete();
        session()->flash('success', 'Permesso eliminato');
    }

    public function render()
    {
        $perms = Permission::query()
            ->when($this->search, fn($q)=>$q->where('name', 'like', "%{$this->search}%"))
            ->paginate(15);

        return view('livewire.gestione-permessi', [
            'permessi' => $perms,
        ]);
    }
}




