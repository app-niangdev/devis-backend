<?php

use App\Models\Role;
use App\Models\User;
use App\Support\SenegalPhone;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Premier administrateur en production (UserSeeder ne crée que des comptes de démonstration) :
// docker compose exec backend php artisan admin:create
Artisan::command('admin:create', function () {
    $role = Role::where('name', 'ADMIN')->firstOrFail();

    $email = $this->ask('E-mail');
    if (User::withTrashed()->where('email', $email)->exists()) {
        $this->error('Cet e-mail est déjà utilisé.');

        return 1;
    }

    $firstName = $this->ask('Prénom');
    $lastName = $this->ask('Nom');
    $phone = $this->ask('Téléphone (9 chiffres)');
    $password = $this->secret('Mot de passe (8 caractères minimum)');

    if (strlen((string) $password) < 8) {
        $this->error('Mot de passe trop court.');

        return 1;
    }

    User::create([
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'phone_one'  => SenegalPhone::normalize($phone) ?? $phone,
        'password'   => $password,
        'status'     => true,
        'role_id'    => $role->id,
    ]);

    $this->info("Administrateur {$email} créé.");
})->purpose('Créer un administrateur de la plateforme');
