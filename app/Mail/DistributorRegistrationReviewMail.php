<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DistributorRegistrationReviewMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $distributorName,
        public readonly string $applicantName,
        public readonly string $agentName,
        public readonly string $registrationId,
        public readonly string $reviewUrl,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[DNY Skincare Kemitraan] Pendaftaran Reseller Memerlukan Persetujuan');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.distributor-registration-review');
    }
}
