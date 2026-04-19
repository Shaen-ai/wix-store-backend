<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order Paid</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

                    <!-- Header -->
                    <tr>
                        <td style="background: #2563eb; padding: 28px;">
                            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #ffffff;">New Order Paid</h1>
                            <p style="margin: 8px 0 0; font-size: 28px; font-weight: 700; color: #ffffff;">{{ $currency }} {{ $amount }}</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 28px;">

                            <!-- Order Info -->
                            <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280;">Order Information</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #374151; width: 45%;">Order ID</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #111827; text-align: right;">#{{ $order->id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #374151;">Product</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #111827; text-align: right;">{{ $product->title ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #374151;">Quantity</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #111827; text-align: right;">{{ $order->quantity }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #374151;">Buyer Email</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #111827; text-align: right;">{{ $order->buyer_email ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #374151;">PayPal Transaction</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #111827; text-align: right; font-family: monospace;">{{ $order->provider_payment_id ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; font-size: 14px; font-weight: 600; color: #374151;">Date</td>
                                    <td style="padding: 10px 0; font-size: 14px; color: #111827; text-align: right;">{{ $order->updated_at->format('Y-m-d H:i:s') }} UTC</td>
                                </tr>
                            </table>

                            <!-- Buyer / Shipping Details -->
                            @if(!empty($buyerDetails))
                            <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280;">Buyer / Shipping Details</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f9fafb; border-radius: 8px; margin-bottom: 20px;">
                                @foreach($buyerDetails as $key => $value)
                                    @if($value)
                                    <tr>
                                        <td style="padding: 8px 16px; font-size: 13px; font-weight: 600; color: #374151; width: 40%;">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                        <td style="padding: 8px 16px; font-size: 13px; color: #111827; text-align: right;">{{ $value }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </table>
                            @endif

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 18px 28px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6;">
                            This is an automated notification from your 3D Store Gallery.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
