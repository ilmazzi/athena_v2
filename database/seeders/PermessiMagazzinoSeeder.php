<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermessiMagazzinoSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Articoli
            'articoli.view', 'articoli.create', 'articoli.update', 'articoli.delete',
            'articoli.discharge', 'articoli.print_label',
            // Magazzini
            'magazzini.view', 'magazzini.create', 'magazzini.update', 'magazzini.delete',
            // Conti Deposito
            'conti_deposito.view', 'conti_deposito.manage', 'conti_deposito.resi',
            // Inventario
            'inventario.view', 'inventario.scan', 'inventario.manage',
            // Acquisti
            'acquisti.view', 'acquisti.create',
            // Stampanti
            'stampanti.view', 'stampanti.manage',
            // Vetrine
            'vetrine.view', 'vetrine.manage',
            // Notifiche
            'notifiche.view',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Associazioni di default
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $mag = Role::firstOrCreate(['name' => 'magazziniere']);
        $amm = Role::firstOrCreate(['name' => 'amministrazione']);

        $admin->givePermissionTo($permissions);
        $mag->syncPermissions([
            'articoli.view','articoli.create','articoli.update','articoli.discharge','articoli.print_label',
            'magazzini.view',
            'inventario.view','inventario.scan',
            'acquisti.view','acquisti.create',
            'stampanti.view',
            'vetrine.view',
            'notifiche.view',
        ]);

        // amministrazione: accesso conti deposito e amministrazione magazzini
        $amm->syncPermissions([
            'conti_deposito.view','conti_deposito.manage','conti_deposito.resi',
            'magazzini.view','magazzini.create','magazzini.update','magazzini.delete',
            'notifiche.view',
        ]);
    }
}


