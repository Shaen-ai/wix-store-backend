<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageMonth extends Model
{
    protected $fillable = [
        'tenant_id',
        'period_yyyymm',
        'image_to_3d_count',
    ];

    protected $casts = [
        'image_to_3d_count' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
