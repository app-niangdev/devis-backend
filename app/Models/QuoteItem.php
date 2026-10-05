<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    public const SUPPLY = 'supply';
    public const LABOR = 'labor';
    public const KINDS = [self::SUPPLY, self::LABOR];

    protected $fillable = [
        'quote_id', 'product_id', 'kind', 'designation', 'unit_name', 'quantity', 'unit_price', 'subtotal', 'position',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
            'position' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
