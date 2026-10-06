<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Type d'abonnement (forfait) proposé aux entreprises. Prix et durée non modifiables.
 */
class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'duration_months', 'price', 'currency', 'description', 'is_active', 'position',
    ];

    protected $hidden = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'price' => 'float',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Dernier jour couvert (inclus) pour une période démarrant le `$startsAt`.
     */
    public function endsAtFor(string $startsAt): CarbonImmutable
    {
        return CarbonImmutable::parse($startsAt)->addMonthsNoOverflow($this->duration_months)->subDay();
    }

    /** Forfaits proposés, dans l'ordre d'affichage. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('duration_months')->orderBy('id');
    }
}
