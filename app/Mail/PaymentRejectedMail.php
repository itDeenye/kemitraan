<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentRejectedMail extends Mailable
{
    public readonly string $paymentUrl;

    public function __construct(
        public readonly string $buyerName,
        public readonly string $orderNumber,
        public readonly string $paymentAmount,
        public readonly string $rejectionReason,
        ?string $paymentUrl = null,
        public readonly bool $orderCancelled = false,
    ) {
        $this->paymentUrl = $paymentUrl ?? url('/member/transactions/orders');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->orderCancelled
                ? "[DNY Skincare Kemitraan] Pesanan PO Dibatalkan – {$this->orderNumber}"
                : "[DNY Skincare Kemitraan] Verifikasi Pembayaran Memerlukan Perhatian – {$this->orderNumber}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-rejected',
        );
    }
}
