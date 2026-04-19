<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderDeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Order #{$this->order->id} Has Been Delivered!",
        );
    }

    public function content(): Content
    {
        $details = $this->order->buyer_details_json ?? [];

        return new Content(
            view: 'emails.order-delivered',
            with: [
                'order' => $this->order,
                'product' => $this->order->product,
                'buyerName' => $this->order->buyer_name ?? ($details['full_name'] ?? 'Valued Customer'),
            ],
        );
    }
}
