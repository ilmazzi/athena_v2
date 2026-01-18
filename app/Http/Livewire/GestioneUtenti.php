<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Sede;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GestioneUtenti extends Component
{
    use WithPagination;

    public $search = '';
    public $sedeId = '';
    public $role = '';

    public $editingUser = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $sede_id = null;
    public $roles = [];
    public $directPermissions = [];
    public $invite = true; // invia email per impostare password

    protected $rules = [
        'name' => 'required|string|max:100',
        'email' => 'required|email',
        'sede_id' => 'nullable|exists:sedi,id',
    ];

    public function create()
    {
        $this->resetValidation();
        $this->editingUser = 0;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->sede_id = null;
        $this->roles = [];
        $this->directPermissions = [];
        $this->invite = true;
    }

    public function edit($userId)
    {
        $user = User::findOrFail($userId);
        $this->editingUser = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->sede_id = $user->sede_id;
        $this->roles = $user->roles()->pluck('name')->toArray();
    }

    public function save()
    {
        $this->validate();

        if ($this->editingUser) {
            $user = User::withTrashed()->findOrFail($this->editingUser);
            $user->update(['name' => $this->name, 'email' => $this->email, 'sede_id' => $this->sede_id]);
            $user->syncRoles($this->roles);
            $user->syncPermissions($this->directPermissions);
            session()->flash('success', 'Utente aggiornato');
        } else {
            // creazione
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'sede_id' => $this->sede_id,
            ];
            if (!$this->invite) {
                $this->validate(['password' => 'required|min:8|confirmed']);
                $data['password'] = bcrypt($this->password);
            } else {
                // password random provvisoria
                $data['password'] = bcrypt(Str::random(16));
            }
            $user = User::create($data);
            $user->syncRoles($this->roles);
            $user->syncPermissions($this->directPermissions);
            if ($this->invite) {
                Password::sendResetLink(['email' => $user->email]);
            }
            session()->flash('success', 'Utente creato');
        }
    }

    public function delete($userId)
    {
        $u = User::findOrFail($userId);
        $u->delete();
        session()->flash('success', 'Utente eliminato');
    }

    public function restore($userId)
    {
        $u = User::withTrashed()->findOrFail($userId);
        $u->restore();
        session()->flash('success', 'Utente ripristinato');
    }

    public function forceDelete($userId)
    {
        $u = User::withTrashed()->findOrFail($userId);
        $u->forceDelete();
        session()->flash('success', 'Utente eliminato definitivamente');
    }

    public function render()
    {
        $query = User::withTrashed()
            ->when($this->search, function($q){
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            })
            ->when($this->sedeId, fn($q)=>$q->where('sede_id', $this->sedeId));

        return view('livewire.gestione-utenti', [
            'utenti' => $query->paginate(15),
            'sedi' => Sede::orderBy('nome')->get(),
            'tuttiRuoli' => Role::all()->pluck('name')->toArray(),
            'tuttiPermessi' => Permission::all()->pluck('name')->toArray(),
        ]);
    }
}


