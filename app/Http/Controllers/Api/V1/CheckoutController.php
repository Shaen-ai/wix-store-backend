<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypal,
    ) {}

    public function paypal(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'productId' => 'required|integer',
            'qty' => 'required|integer|min:1|max:100',
            'currency' => 'required|string|size:3',
        ]);

        $product = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->findOrFail($validated['productId']);

        $settings = $tenant->settings;

        if (!$settings?->paypal_receiver_email) {
            return response()->json(['error' => 'PayPal not configured for this store'], 422);
        }

        $baseCurrency = $settings->base_currency ?? 'EUR';
        $currency = $validated['currency'];

        if (strtoupper($currency) !== strtoupper($baseCurrency)) {
            return response()->json(['error' => 'Store accepts payments in ' . $baseCurrency . ' only'], 422);
        }

        $qty = $validated['qty'];
        $amountCents = $product->base_price_cents * $qty;

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'currency' => $currency,
            'amount_cents' => $amountCents,
            'fx_rate_used' => null,
            'provider' => 'paypal',
            'status' => 'pending',
        ]);

        $redirectUrl = $this->paypal->buildPaymentUrl($order, $settings);

        return response()->json([
            'data' => [
                'redirect_url' => $redirectUrl,
                'order_id' => $order->id,
            ],
        ]);
    }

    /**
     * Create PayPal checkout for entire cart (multiple items, single payment).
     */
    public function paypalCart(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.productId' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'currency' => 'required|string|size:3',
        ]);

        $settings = $tenant->settings;

        if (!$settings?->paypal_receiver_email) {
            return response()->json(['error' => 'PayPal not configured for this store'], 422);
        }

        $baseCurrency = $settings->base_currency ?? 'EUR';
        $currency = $validated['currency'];

        if (strtoupper($currency) !== strtoupper($baseCurrency)) {
            return response()->json(['error' => 'Store accepts payments in ' . $baseCurrency . ' only'], 422);
        }

        $orders = [];

        foreach ($validated['items'] as $item) {
            $product = Product::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->findOrFail($item['productId']);

            $qty = $item['qty'];
            $amountCents = $product->base_price_cents * $qty;

            $order = Order::create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'currency' => $currency,
                'amount_cents' => $amountCents,
                'fx_rate_used' => null,
                'provider' => 'paypal',
                'status' => 'pending',
            ]);

            $orders[] = $order->load('product');
        }

        $redirectUrl = $this->paypal->buildCartPaymentUrl($orders, $settings);

        return response()->json([
            'data' => [
                'redirect_url' => $redirectUrl,
                'order_ids' => array_map(fn ($o) => $o->id, $orders),
            ],
        ]);
    }
}
