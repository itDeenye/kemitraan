<?php

namespace App\Mail;

use App\Models\Trx;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StockScreeningApprovedMail extends Mailable
{
    public readonly string $orderUrl;

    public function __construct(public readonly Trx $trx)
    {
        $this->orderUrl = url("/member/transactions/orders/{$trx->getKey()}");
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Screening Stok Pesanan Disetujui');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.stock-screening-approved');
    }
}
