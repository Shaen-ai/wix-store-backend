<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4f46e5, #6366f1); padding: 32px 28px;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff;">Order Confirmed!</h1>
                            <p style="margin: 8px 0 0; font-size: 15px; color: rgba(255,255,255,0.85); line-height: 1.5;">Thank you for your purchase, {{ $buyerName }}.</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 28px;">

                            <!-- Order Details Section -->
                            <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280;">Order Details</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #6b7280; width: 45%;">Order Number</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #111827; text-align: right;">#{{ $order->id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #6b7280;">Product</td>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #111827; text-align: right;">{{ $product->title ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; font-size: 14px; color: #6b7280;">Quantity</td>
                                    <td style="padding: 10px 0; font-size: 14px; font-weight: 600; color: #111827; text-align: right;">{{ $order->quantity }}</td>
                                </tr>
                            </table>

                            <!-- Total -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f9fafb; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 14px 18px; font-size: 14px; font-weight: 600; color: #374151;">Total Paid</td>
                                    <td style="padding: 14px 18px; font-size: 22px; font-weight: 700; color: #4f46e5; text-align: right;">{{ $currency }} {{ $amount }}</td>
                                </tr>
                            </table>

                            <!-- Shipping Information -->
                            @if(!empty($buyerDetails))
                            <p style="margin: 0 0 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280;">Shipping Information</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                @foreach($buyerDetails as $key => $value)
                                    @if($value && !in_array($key, ['email']))
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #6b7280; width: 45%;">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #111827; text-align: right;">{{ $value }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </table>
                            @endif

                            <!-- What happens next -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 16px 18px;">
                                        <p style="margin: 0 0 6px; font-size: 14px; font-weight: 700; color: #78350f;">What happens next?</p>
                                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">Your order is being prepared. You will receive a shipping notification with tracking details once your order has been dispatched.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 18px 28px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6;">
                            This is an automated confirmation from your purchase. If you have questions, please contact the store directly.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
