<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['wix_site_id', 'plan'];

    protected $casts = [
        'plan' => 'string',
    ];

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function widgetSettings(): HasMany
    {
        return $this->hasMany(WidgetSetting::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
