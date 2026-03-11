<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'base_price_cents',
        'base_currency',
        'images_json',
        'is_active',
    ];

    protected $casts = [
        'images_json' => 'array',
        'base_price_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function model(): HasOne
    {
        return $this->hasOne(ProductModel::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getImagesAttribute(): array
    {
        return $this->images_json ?? [];
    }
}
