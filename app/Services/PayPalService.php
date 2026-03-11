<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private bool $sandbox;

    public function __construct()
    {
        $this->sandbox = (bool) config('services.paypal.sandbox', true);
    }

    /**
     * Build the PayPal redirect URL for a classic Website Payments Standard button.
     */
    public function buildPaymentUrl(Order $order, TenantSetting $settings): string
    {
        $base = $this->sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $params = [
            'cmd' => '_xclick',
            'business' => $settings->paypal_receiver_email,
            'item_name' => $order->product?->title ?? "Order #{$order->id}",
            'amount' => number_format($order->amount_cents / 100, 2, '.', ''),
            'currency_code' => $order->currency,
            'no_shipping' => '1',
            'notify_url' => config('services.paypal.ipn_url'),
            'return' => config('services.paypal.return_url') . '&orderId=' . $order->id,
            'cancel_return' => config('services.paypal.cancel_url'),
            'custom' => (string) $order->id,
        ];

        return $base . '?' . http_build_query($params);
    }

    /**
     * Build PayPal cart redirect URL for multiple items (single payment).
     *
     * @param  array<Order>  $orders
     */
    public function buildCartPaymentUrl(array $orders, TenantSetting $settings): string
    {
        if (empty($orders)) {
            throw new \InvalidArgumentException('At least one order is required');
        }

        $base = $this->sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $orderIds = array_map(fn (Order $o) => $o->id, $orders);
        $firstOrder = $orders[0];

        $params = [
            'cmd' => '_cart',
            'upload' => '1',
            'business' => $settings->paypal_receiver_email,
            'currency_code' => $firstOrder->currency,
            'no_shipping' => '1',
            'notify_url' => config('services.paypal.ipn_url'),
            'return' => config('services.paypal.return_url') . '&orderId=' . implode(',', $orderIds),
            'cancel_return' => config('services.paypal.cancel_url'),
            'custom' => implode(',', $orderIds),
        ];

        foreach ($orders as $i => $order) {
            $n = $i + 1;
            $unitPriceCents = (int) round($order->amount_cents / $order->quantity);
            $params["item_name_{$n}"] = $order->product?->title ?? "Item {$n}";
            $params["amount_{$n}"] = number_format($unitPriceCents / 100, 2, '.', '');
            $params["quantity_{$n}"] = $order->quantity;
        }

        return $base . '?' . http_build_query($params);
    }

    /**
     * Verify an IPN message by posting it back to PayPal.
     */
    public function verifyIpn(string $rawBody): bool
    {
        $verifyUrl = $this->sandbox
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $verifyBody = 'cmd=_notify-validate&' . $rawBody;

        try {
            $response = Http::timeout(30)
                ->withBody($verifyBody, 'application/x-www-form-urlencoded')
                ->post($verifyUrl);

            $result = trim($response->body());

            if ($result === 'VERIFIED') {
                return true;
            }

            Log::warning('PayPal IPN verification failed', [
                'response' => $result,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('PayPal IPN verification error', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
