<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmation – #{$this->order->id}",
        );
    }

    public function content(): Content
    {
        $details = $this->order->buyer_details_json ?? [];

        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'order' => $this->order,
                'product' => $this->order->product,
                'amount' => number_format($this->order->amount_cents / 100, 2),
                'currency' => $this->order->currency,
                'buyerName' => $this->order->buyer_name ?? ($details['full_name'] ?? 'Valued Customer'),
                'buyerDetails' => $details,
            ],
        );
    }
}
