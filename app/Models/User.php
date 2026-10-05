<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'first_name', 'last_name', 'email', 'password',
    'phone_one', 'phone_two', 'username', 'address', 'status', 'role_id',
    'must_change_password', 'tenant_id', 'phone_verified_at', 'token_version',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'status'               => 'boolean',
            'must_change_password' => 'boolean',
            'phone_verified_at'    => 'datetime',
            'token_version'        => 'integer',
        ];
    }

    protected $hidden = [
        'password',
        'remember_token',
        'token_version',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $appends = [
        'full_name'
    ];

    public function getFullNameAttribute(): string
    {
        return trim(
            ($this->first_name ?? '') . ' ' .
            ($this->last_name ?? '')
        );
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'ADMIN';
    }

    public function isManager(): bool
    {
        return $this->role?->name === 'MANAGER';
    }

    /** Invalide tous les jetons (accès et rafraîchissement) déjà délivrés. */
    public function revokeTokens(): void
    {
        $this->increment('token_version');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
