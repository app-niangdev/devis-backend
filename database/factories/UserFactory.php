<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            // Mobile sénégalais unique : 77 + 7 chiffres
            'phone_one' => '77' . fake()->unique()->numerify('#######'),
            'password' => 'Secret2026',
            'status' => true,
            'must_change_password' => false,
            'phone_verified_at' => now(),
            'role_id' => fn () => Role::firstOrCreate(['name' => 'MANAGER'], ['label' => 'Gestionnaire'])->id,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['name' => 'ADMIN'], ['label' => 'Administrateur'])->id,
        ]);
    }

    /** Compte créé par l'administrateur, jamais connecté. */
    public function firstLogin(): static
    {
        return $this->state(fn () => [
            'must_change_password' => true,
            'phone_verified_at' => null,
        ]);
    }
}
