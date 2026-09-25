<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberRegistrationRejectedMail extends Mailable
{
    public readonly string $verificationUrl;

    public function __construct(
        public readonly string $memberName,
        public readonly string $registrationId,
        public readonly string $memberLevel,
        public readonly string $rejectionReason,
        ?string $verificationUrl = null,
    ) {
        $this->verificationUrl = $verificationUrl ?? url('/member/network/registrations');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[DNY Skincare Kemitraan] Status Registrasi Kemitraan – Memerlukan Perbaikan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-registration-rejected',
        );
    }
}
