<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberRegistrationApprovedMail extends Mailable
{
    public readonly string $loginUrl;

    public function __construct(
        public readonly string $memberName,
        public readonly string $memberCode,
        public readonly string $memberLevel,
        public readonly string $sponsorName,
        public readonly string $username,
        public readonly string $password,
        ?string $loginUrl = null,
    ) {
        $this->loginUrl = $loginUrl ?? url('/member/login');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[DNY Skincare Kemitraan] Registrasi Kemitraan Anda Telah Disetujui',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-registration-approved',
        );
    }
}
