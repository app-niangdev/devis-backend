<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'ADMIN')->firstOrFail();
        $managerRole = Role::where('name', 'MANAGER')->firstOrFail();

        $tenant = Tenant::firstOrCreate(
            ['code_website' => 'demo-btp'],
            [
                'name' => 'Ndiaye Plomberie',
                'trade' => 'Plombier',
                'address' => 'Bargny, Rufisque',
                'phone_call' => '770906538',
                'primary_color' => '#1D4ED8',
                'secondary_color' => '#0F172A',
                'accent_color' => '#F59E0B',
                'default_deposit_type' => 'percent',
                'default_deposit_value' => 30,
            ]
        );

        if (!$tenant->subscriptions()->exists()) {
            app(SubscriptionService::class)->createTrial($tenant);
        }

        // Administrateur de la plateforme : connexion par e-mail
        User::firstOrCreate(['email' => 'niangdev031299@gmail.com'], [
            'first_name' => 'Ibrahima',
            'last_name'  => 'Niang',
            'phone_one'  => '770906538',
            'password'   => 'P@sser1234',
            'address'    => 'Bargny',
            'status'     => true,
            'role_id'    => $adminRole->id,
        ]);

        // Gestionnaire : connexion par téléphone ; à la première connexion il reçoit un code
        // WhatsApp (visible dans storage/logs sans WAHA en local) puis choisit son mot de passe.
        User::firstOrCreate(['phone_one' => '771234567'], [
            'first_name'           => 'Moussa',
            'last_name'            => 'Ndiaye',
            'email'                => 'manager@yopmail.com',
            'password'             => 'P@sser1234',
            'address'              => 'Bargny',
            'status'               => true,
            'must_change_password' => true,
            'role_id'              => $managerRole->id,
            'tenant_id'            => $tenant->id,
        ]);
    }
}
