<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductModel extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'glb_disk',
        'glb_path',
        'source_type',
        'source_image_path',
        'source_images_json',
        'generation_status',
        'generation_meta_json',
    ];

    protected $casts = [
        'generation_meta_json' => 'array',
        'source_images_json' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getGlbUrlAttribute(): ?string
    {
        return $this->getGlbTemporaryUrl(60);
    }

    public function getGlbTemporaryUrl(int $minutes = 60): ?string
    {
        if (!$this->glb_path) return null;
        $disk = Storage::disk($this->glb_disk);
        try {
            return $disk->temporaryUrl($this->glb_path, now()->addMinutes($minutes));
        } catch (\RuntimeException) {
            return $disk->url($this->glb_path);
        }
    }
}
