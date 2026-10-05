<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'code',
        'title',
        'type',
        'classes',
        'url',
        'icon',
        'breadcrumbs',
        'position'
    ];

    public function menuRoles(): HasMany
    {
        return $this->hasMany(MenuRole::class);
    }

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
