<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PayPalIpnController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypal,
    ) {}

    /**
     * Handle PayPal IPN notification.
     * This endpoint is PUBLIC — no Wix auth required.
     * Security is enforced by PayPal IPN verification + receiver_email check.
     */
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();

        Log::info('PayPal IPN received', ['body_length' => strlen($rawBody)]);

        // 1. Verify IPN with PayPal
        if (!$this->paypal->verifyIpn($rawBody)) {
            Log::warning('PayPal IPN verification failed');
            return response('INVALID', 400);
        }

        $data = $request->all();

        $custom = $data['custom'] ?? null;
        $txnId = $data['txn_id'] ?? null;
        $paymentStatus = $data['payment_status'] ?? '';
        $receiverEmail = $data['receiver_email'] ?? '';
        $mcGross = $data['mc_gross'] ?? '0';
        $mcCurrency = $data['mc_currency'] ?? '';
        $payerEmail = $data['payer_email'] ?? null;

        // 2. Parse order ID(s) — single order or cart (comma-separated)
        if (!$custom) {
            Log::warning('PayPal IPN: missing custom (orderId)');
            return response('OK', 200);
        }

        $orderIds = array_map('intval', array_filter(array_map('trim', explode(',', $custom))));
        if (empty($orderIds)) {
            Log::warning('PayPal IPN: invalid custom', ['custom' => $custom]);
            return response('OK', 200);
        }

        $orders = Order::with(['tenant.settings', 'product'])
            ->whereIn('id', $orderIds)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            Log::warning('PayPal IPN: orders not found', ['orderIds' => $orderIds]);
            return response('OK', 200);
        }

        $order = $orders->first();
        $settings = $order->tenant?->settings;

        // 3. Idempotency: skip if all already processed with this txn_id
        $allPaid = $orders->every(fn ($o) => $o->status === 'paid' && $o->provider_payment_id === $txnId);
        if ($allPaid) {
            Log::info('PayPal IPN: already processed', ['orderIds' => $orderIds, 'txnId' => $txnId]);
            return response('OK', 200);
        }

        // 4. Check payment_status
        if ($paymentStatus !== 'Completed') {
            Log::info('PayPal IPN: non-completed status', ['status' => $paymentStatus]);
            if (in_array($paymentStatus, ['Failed', 'Denied', 'Reversed'])) {
                Order::whereIn('id', $orderIds)->update(['status' => 'failed']);
            }
            return response('OK', 200);
        }

        // 5. Verify receiver_email matches tenant setting
        if (!$settings || strtolower($receiverEmail) !== strtolower($settings->paypal_receiver_email)) {
            Log::warning('PayPal IPN: receiver_email mismatch', [
                'expected' => $settings?->paypal_receiver_email,
                'got' => $receiverEmail,
            ]);
            return response('OK', 200);
        }

        // 6. Verify total amount and currency
        $expectedTotalCents = $orders->sum('amount_cents');
        $expectedAmount = number_format($expectedTotalCents / 100, 2, '.', '');
        if ($mcGross !== $expectedAmount || strtoupper($mcCurrency) !== strtoupper($order->currency)) {
            Log::warning('PayPal IPN: amount/currency mismatch', [
                'expected_amount' => $expectedAmount,
                'got_amount' => $mcGross,
                'expected_currency' => $order->currency,
                'got_currency' => $mcCurrency,
            ]);
            return response('OK', 200);
        }

        // 7. Atomically mark all orders as paid
        $updated = DB::transaction(function () use ($orderIds, $txnId, $payerEmail) {
            $lockedOrders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
            $anyUnpaid = $lockedOrders->contains(fn ($o) => $o->status !== 'paid');

            if (!$anyUnpaid) {
                return false;
            }

            Order::whereIn('id', $orderIds)->update([
                'status' => 'paid',
                'provider_payment_id' => $txnId,
                'buyer_email' => $payerEmail,
            ]);

            return true;
        });

        if (!$updated) {
            Log::info('PayPal IPN: orders already processed (concurrent)', ['orderIds' => $orderIds]);
            return response('OK', 200);
        }

        Log::info('PayPal IPN: orders marked as paid', ['orderIds' => $orderIds, 'txnId' => $txnId]);

        // 8. Send email notification for each order
        if ($settings->notification_email) {
            foreach (Order::whereIn('id', $orderIds)->with('product')->get() as $paidOrder) {
                try {
                    Mail::to($settings->notification_email)
                        ->send(new OrderPaidMail($paidOrder));
                } catch (\Throwable $e) {
                    Log::error('Failed to send order paid email', ['error' => $e->getMessage()]);
                }
            }
        }

        return response('OK', 200);
    }
}
