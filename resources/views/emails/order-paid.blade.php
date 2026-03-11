<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Paid</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .body { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .detail { margin: 8px 0; }
        .label { font-weight: 600; color: #374151; }
        .amount { font-size: 24px; font-weight: 700; color: #2563eb; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0;font-size:20px;">New Order Paid</h1>
    </div>
    <div class="body">
        <p class="amount">{{ $currency }} {{ $amount }}</p>

        <div class="detail">
            <span class="label">Order ID:</span> #{{ $order->id }}
        </div>
        <div class="detail">
            <span class="label">Product:</span> {{ $product->title ?? 'N/A' }}
        </div>
        <div class="detail">
            <span class="label">Quantity:</span> {{ $order->quantity }}
        </div>
        <div class="detail">
            <span class="label">Buyer Email:</span> {{ $order->buyer_email ?? 'N/A' }}
        </div>
        <div class="detail">
            <span class="label">PayPal Transaction:</span> {{ $order->provider_payment_id ?? 'N/A' }}
        </div>
        <div class="detail">
            <span class="label">Date:</span> {{ $order->updated_at->format('Y-m-d H:i:s') }} UTC
        </div>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">
        <p style="font-size:12px;color:#6b7280;">This is an automated notification from your 3D Store Gallery.</p>
    </div>
</body>
</html>
