<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $orders = Order::where('tenant_id', $tenant->id)
            ->with('product:id,title')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $data = $orders->getCollection()->map(fn (Order $o) => [
            'id' => $o->id,
            'tenant_id' => $o->tenant_id,
            'product_id' => $o->product_id,
            'product' => $o->product ? ['id' => $o->product->id, 'title' => $o->product->title] : null,
            'quantity' => $o->quantity,
            'buyer_email' => $o->buyer_email,
            'buyer_name' => $o->buyer_name,
            'buyer_phone' => $o->buyer_phone,
            'buyer_details_json' => $o->buyer_details_json,
            'currency' => $o->currency,
            'amount_cents' => $o->amount_cents,
            'fx_rate_used' => $o->fx_rate_used,
            'provider' => $o->provider,
            'provider_payment_id' => $o->provider_payment_id,
            'status' => $o->status,
            'tracking_number' => $o->tracking_number,
            'shipped_at' => $o->shipped_at?->toISOString(),
            'delivered_at' => $o->delivered_at?->toISOString(),
            'created_at' => $o->created_at?->toISOString(),
            'updated_at' => $o->updated_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function updateShipping(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $order = Order::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:processing,shipped,delivered',
            'tracking_number' => 'nullable|string|max:255',
        ]);

        $previousStatus = $order->status;
        $newStatus = $validated['status'];

        $order->update([
            'status' => $newStatus,
            'tracking_number' => $validated['tracking_number'] ?? $order->tracking_number,
            'shipped_at' => $newStatus === 'shipped' && !$order->shipped_at ? now() : $order->shipped_at,
            'delivered_at' => $newStatus === 'delivered' && !$order->delivered_at ? now() : $order->delivered_at,
        ]);

        if ($order->buyer_email) {
            $order->load('product');

            // Send shipping notification when marking as shipped
            if ($newStatus === 'shipped' && !in_array($previousStatus, ['shipped', 'delivered'])) {
                try {
                    Mail::to($order->buyer_email)->send(new OrderShippedMail($order));
                } catch (\Throwable $e) {
                    Log::error('Failed to send order shipped email', ['error' => $e->getMessage()]);
                }
            }

            // Send delivery confirmation when marking as delivered
            if ($newStatus === 'delivered' && $previousStatus !== 'delivered') {
                try {
                    Mail::to($order->buyer_email)->send(new OrderDeliveredMail($order));
                } catch (\Throwable $e) {
                    Log::error('Failed to send order delivered email', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['data' => $order]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');

        $orders = Order::where('tenant_id', $tenant->id)
            ->with('product:id,title')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Product', 'Quantity', 'Amount', 'Currency', 'Status', 'Buyer Name', 'Buyer Email', 'Buyer Phone', 'Tracking', 'PayPal TXN', 'Date']);

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->id,
                    $o->product?->title ?? '',
                    $o->quantity,
                    number_format($o->amount_cents / 100, 2),
                    $o->currency,
                    $o->status,
                    $o->buyer_name ?? '',
                    $o->buyer_email ?? '',
                    $o->buyer_phone ?? '',
                    $o->tracking_number ?? '',
                    $o->provider_payment_id ?? '',
                    $o->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'orders.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
