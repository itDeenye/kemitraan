<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberCredentialsMail extends Mailable
{
    public readonly string $loginUrl;

    public function __construct(
        public readonly string $memberName,
        public readonly string $username,
        public readonly string $password,
        ?string $loginUrl = null,
        public readonly ?string $memberCode = null,
        public readonly ?string $memberLevel = null,
        public readonly ?string $email = null,
    ) {
        $this->loginUrl = $loginUrl ?? url('/member/login');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[DNY Skincare Kemitraan] Registrasi Kemitraan Berhasil – Informasi Akun Anda',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.member-credentials');
    }
}
