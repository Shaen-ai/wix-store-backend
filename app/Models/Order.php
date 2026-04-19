<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'quantity',
        'buyer_email',
        'buyer_name',
        'buyer_phone',
        'buyer_details_json',
        'currency',
        'amount_cents',
        'fx_rate_used',
        'provider',
        'provider_payment_id',
        'status',
        'tracking_number',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount_cents' => 'integer',
        'fx_rate_used' => 'decimal:8',
        'buyer_details_json' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * All unique buyer email addresses (form email, primary buyer_email, and PayPal payer email).
     */
    public function allBuyerEmails(): array
    {
        $details = $this->buyer_details_json ?? [];

        return collect([
                $this->buyer_email,
                $details['email'] ?? null,
                $details['paypal_email'] ?? null,
            ])
            ->filter()
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()
            ->values()
            ->all();
    }
}
