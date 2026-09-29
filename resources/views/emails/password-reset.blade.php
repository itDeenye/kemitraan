@extends('emails.layout')

@section('title', 'Reset Password Akun DNY Skincare')
@section('heading', 'Reset Password')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $accountName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Kami menerima permintaan untuk mengatur ulang password akun {{ $audience === 'admin' ? 'administrator' : 'mitra' }} Anda.</p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Buat Password Baru</a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0 0 10px;">Tautan ini berlaku selama {{ $expiresInMinutes }} menit dan hanya dapat digunakan satu kali.</p>
    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Jika Anda tidak meminta reset password, abaikan email ini dan jangan membagikan tautannya kepada siapa pun.</p>
@endsection
