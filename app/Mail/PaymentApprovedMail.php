<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentApprovedMail extends Mailable
{
    public readonly string $orderUrl;

    public function __construct(
        public readonly string $buyerName,
        public readonly string $orderNumber,
        public readonly string $paymentAmount,
        public readonly string $paymentDate,
        ?string $orderUrl = null,
    ) {
        $this->orderUrl = $orderUrl ?? url('/member/transactions/orders');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[DNY Skincare Kemitraan] Pembayaran Berhasil Diverifikasi – {$this->orderNumber}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-approved',
        );
    }
}
