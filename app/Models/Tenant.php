<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'code_website', 'slogan', 'description',
    'address', 'email', 'trade', 'ninea', 'rccm',
    'phone_other', 'phone_call', 'phone_whatsapp',
    'logo_url', 'snap', 'instagram', 'facebook', 'tiktok',
    'primary_color', 'secondary_color', 'accent_color',
    'default_deposit_type', 'default_deposit_value', 'quote_validity_days', 'quote_footer',
    'stamp_enabled', 'stamp_color',
    'short_name', 'state',
    'approval_status', 'approval_reviewed_at', 'approval_reviewed_by', 'rejection_reason',
])]
class Tenant extends Model
{
    use SoftDeletes;

    /**
     * Inscription faite depuis l'application : « pending » jusqu'à la confirmation du numéro
     * par code WhatsApp, puis « approved ». Créée par l'administrateur : « approved » d'office.
     */
    public const APPROVED = 'approved';
    public const PENDING = 'pending';

    protected function casts(): array
    {
        return [
            'state' => 'boolean',
            'stamp_enabled' => 'boolean',
            'default_deposit_value' => 'integer',
            'quote_validity_days' => 'integer',
            'approval_reviewed_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function isPending(): bool
    {
        return $this->approval_status === self::PENDING;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** Charte graphique transmise aux applications (thème mobile, PDF). */
    public function branding(): array
    {
        return [
            'name' => $this->name,
            'short_name' => $this->short_name,
            'slogan' => $this->slogan,
            'logo_url' => $this->logo_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
