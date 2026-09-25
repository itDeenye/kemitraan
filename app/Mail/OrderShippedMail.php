<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderShippedMail extends Mailable
{
    public readonly string $trackingUrl;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $orderNumber,
        public readonly string $orderDate,
        public readonly string $shippingDate,
        public readonly string $courier,
        public readonly string $trackingNumber,
        public readonly string $shippingAddress,
        ?string $trackingUrl = null,
    ) {
        $this->trackingUrl = $trackingUrl ?? url('/member/transactions/orders');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[DNY Skincare Kemitraan] Pesanan Anda Telah Dikirim – {$this->orderNumber}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-shipped',
        );
    }
}
