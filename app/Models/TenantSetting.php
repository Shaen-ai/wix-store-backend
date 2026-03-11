<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'notification_email',
        'paypal_receiver_email',
        'base_currency',
        'fx_provider',
        'fx_api_key',
        'image_to_3d_provider',
        'image_to_3d_api_key',
    ];

    protected $casts = [
        'fx_api_key' => 'encrypted',
        'image_to_3d_api_key' => 'encrypted',
    ];

    protected $hidden = [
        'fx_api_key',
        'image_to_3d_api_key',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
