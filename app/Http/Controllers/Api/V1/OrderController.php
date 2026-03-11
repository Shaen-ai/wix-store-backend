<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'currency' => $o->currency,
            'amount_cents' => $o->amount_cents,
            'fx_rate_used' => $o->fx_rate_used,
            'provider' => $o->provider,
            'provider_payment_id' => $o->provider_payment_id,
            'status' => $o->status,
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

    public function exportCsv(Request $request): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');

        $orders = Order::where('tenant_id', $tenant->id)
            ->with('product:id,title')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Product', 'Quantity', 'Amount', 'Currency', 'Status', 'Buyer Email', 'PayPal TXN', 'Date']);

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->id,
                    $o->product?->title ?? '',
                    $o->quantity,
                    number_format($o->amount_cents / 100, 2),
                    $o->currency,
                    $o->status,
                    $o->buyer_email ?? '',
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
