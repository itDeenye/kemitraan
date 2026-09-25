<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberPasswordResetByAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $loginUrl;

    public function __construct(
        public readonly string $memberName,
        public readonly string $memberCode,
        public readonly string $username,
        public readonly string $password,
        ?string $loginUrl = null,
    ) {
        $this->loginUrl = $loginUrl ?? url('/member/login');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[DNY Skincare Kemitraan] Password Akun Anda Telah Direset',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(view: 'emails.member-password-reset-by-admin');
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
