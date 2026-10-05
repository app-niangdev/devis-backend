<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id', 'product_id', 'quantity', 'unit_price',
    'discount', 'subtotal', 'total',
])]
class SaleLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'discount'   => 'decimal:2',
            'subtotal'   => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
