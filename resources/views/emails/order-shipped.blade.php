<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Shipped</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9fafb; }
        .card { background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: linear-gradient(135deg, #059669, #10b981); color: white; padding: 28px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 14px; }
        .body { padding: 24px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; }
        .detail-value { font-weight: 600; color: #111827; }
        .tracking-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 16px; margin-top: 16px; text-align: center; }
        .tracking-number { font-size: 18px; font-weight: 700; color: #065f46; letter-spacing: 0.05em; font-family: monospace; }
        .tracking-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 4px; }
        .footer { padding: 16px 24px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Your Order Has Been Shipped!</h1>
            <p>Great news, {{ $buyerName }}! Your order is on its way.</p>
        </div>
        <div class="body">
            <div class="detail-row">
                <span class="detail-label">Order Number</span>
                <span class="detail-value">#{{ $order->id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Product</span>
                <span class="detail-value">{{ $product->title ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Quantity</span>
                <span class="detail-value">{{ $order->quantity }}</span>
            </div>

            @if($trackingNumber)
            <div class="tracking-box">
                <div class="tracking-label">Tracking Number</div>
                <div class="tracking-number">{{ $trackingNumber }}</div>
            </div>
            @endif

            <p style="margin-top: 20px; font-size: 14px; color: #6b7280;">
                If you have any questions about your delivery, please contact the store directly.
            </p>
        </div>
        <div class="footer">
            This is an automated shipping notification.
        </div>
    </div>
</body>
</html>
