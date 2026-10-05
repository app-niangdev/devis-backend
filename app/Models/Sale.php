<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id', 'user_id', 'reference', 'sale_date',
    'subtotal', 'discount', 'total', 'paid_amount',
    'payment_method', 'payment_status', 'status',
])]
class Sale extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'sale_date'      => 'datetime',
            'subtotal'       => 'decimal:2',
            'discount'       => 'decimal:2',
            'total'          => 'decimal:2',
            'paid_amount'    => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'status'         => SaleStatus::class,
        ];
    }

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function saleLines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function refreshPaymentStatus(): void
    {
        $this->payment_status = match (true) {
            $this->paid_amount <= 0 => PaymentStatus::PENDING,
            $this->paid_amount >= $this->total => PaymentStatus::PAID,
            default => PaymentStatus::PARTIAL,
        };
    }
}
