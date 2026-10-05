<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent : rejoué à chaque déploiement
        Role::firstOrCreate(['name' => 'ADMIN'],   ['label' => 'Administrateur']);
        Role::firstOrCreate(['name' => 'MANAGER'], ['label' => 'Gestionnaire']);
    }
}
