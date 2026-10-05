<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const ACCEPTED = 'accepted';
    public const REFUSED = 'refused';
    public const STATUSES = [self::DRAFT, self::SENT, self::ACCEPTED, self::REFUSED];

    public const DEPOSIT_NONE = 'none';
    public const DEPOSIT_PERCENT = 'percent';
    public const DEPOSIT_AMOUNT = 'amount';
    public const DEPOSIT_TYPES = [self::DEPOSIT_NONE, self::DEPOSIT_PERCENT, self::DEPOSIT_AMOUNT];

    public const PAYMENT_METHODS = ['wave', 'orange_money', 'free_money', 'cash'];

    protected $fillable = [
        'tenant_id', 'customer_id', 'user_id', 'source_quote_id', 'quote_number', 'status', 'title',
        'valid_until', 'notes', 'supplies_amount', 'labor_amount', 'gross_amount', 'discount', 'total_amount',
        'deposit_type', 'deposit_value', 'deposit_amount',
        'deposit_received_amount', 'deposit_received_at', 'deposit_payment_method', 'deposit_reference',
        'sent_at', 'decided_at',
    ];

    protected $hidden = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date:Y-m-d',
            'deposit_received_at' => 'date:Y-m-d',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
            'supplies_amount' => 'integer',
            'labor_amount' => 'integer',
            'gross_amount' => 'integer',
            'discount' => 'integer',
            'total_amount' => 'integer',
            'deposit_value' => 'integer',
            'deposit_amount' => 'integer',
            'deposit_received_amount' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'source_quote_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /** Le contenu reste modifiable tant que le client n'a pas répondu. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SENT], true);
    }

    /** Date de validité dépassée sans réponse du client (calculé, non stocké). */
    public function isExpired(): bool
    {
        return $this->isEditable() && $this->valid_until !== null && $this->valid_until->lt(today());
    }

    /** none | pending | partial | received */
    public function depositStatus(): string
    {
        if ($this->deposit_amount <= 0) {
            return 'none';
        }

        $received = (int) $this->deposit_received_amount;

        return match (true) {
            $received <= 0 => 'pending',
            $received < $this->deposit_amount => 'partial',
            default => 'received',
        };
    }
}
