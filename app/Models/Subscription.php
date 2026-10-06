<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'subscription_plan_id', 'plan', 'amount', 'currency', 'starts_at', 'ends_at', 'notes', 'created_by',
    ];

    protected $hidden = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->select(['id', 'first_name', 'last_name']);
    }
}
