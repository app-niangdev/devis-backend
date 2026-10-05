<?php

namespace App\Models;

use App\Support\SenegalPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'phone', 'address', 'email', 'notes'];

    protected $hidden = ['deleted_at'];

    protected $appends = ['phone_display'];

    public function getPhoneDisplayAttribute(): ?string
    {
        return SenegalPhone::format($this->phone);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
