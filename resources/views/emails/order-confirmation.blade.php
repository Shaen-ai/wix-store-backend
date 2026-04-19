<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9fafb; }
        .card { background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; padding: 28px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 14px; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 10px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; }
        .detail-value { font-weight: 600; color: #111827; }
        .total-row { background: #f9fafb; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
        .total-amount { font-size: 22px; font-weight: 700; color: #4f46e5; }
        .status-box { background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; margin-top: 20px; }
        .status-box p { margin: 0; font-size: 13px; color: #92400e; }
        .status-box strong { color: #78350f; }
        .footer { padding: 16px 24px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Order Confirmed!</h1>
            <p>Thank you for your purchase, {{ $buyerName }}.</p>
        </div>
        <div class="body">
            <div class="section">
                <div class="section-title">Order Details</div>
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
                <div class="total-row">
                    <span style="font-size: 14px; font-weight: 600; color: #374151;">Total Paid</span>
                    <span class="total-amount">{{ $currency }} {{ $amount }}</span>
                </div>
            </div>

            @if(!empty($buyerDetails))
            <div class="section">
                <div class="section-title">Shipping Information</div>
                @foreach($buyerDetails as $key => $value)
                    @if($value && !in_array($key, ['email']))
                    <div class="detail-row">
                        <span class="detail-label">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                        <span class="detail-value">{{ $value }}</span>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif

            <div class="status-box">
                <p><strong>What happens next?</strong></p>
                <p>Your order is being prepared. You will receive a shipping notification with tracking details once your order has been dispatched.</p>
            </div>
        </div>
        <div class="footer">
            This is an automated confirmation from your purchase. If you have questions, please contact the store directly.
        </div>
    </div>
</body>
</html>
