<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetMail extends Mailable
{
    public function __construct(
        public readonly string $accountName,
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
        public readonly string $audience,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Password Akun DNY Skincare');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
