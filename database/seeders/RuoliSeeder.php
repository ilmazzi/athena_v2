<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RuoliSeeder extends Seeder
{
    public function run(): void
    {
        // Crea ruoli base se non esistono
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $magazziniere = Role::firstOrCreate(['name' => 'magazziniere']);

        // Assegna admin al primo utente (se presente)
        $user = User::query()->orderBy('id')->first();
        if ($user && !$user->hasRole('admin')) {
            $user->assignRole($admin);
        }
    }
}



