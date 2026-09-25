@extends('emails.layout')

@section('title', 'Registrasi Kemitraan Berhasil')
@section('heading', 'Registrasi Kemitraan Berhasil')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $memberName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Selamat, proses registrasi kemitraan DNY Skincare Anda telah berhasil. Berikut informasi akun yang dapat digunakan untuk mengakses sistem kemitraan DNY Skincare.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff4f6;border:1px solid #f5ccd5;border-radius:12px;margin-bottom:26px;">
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Nama Mitra</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $memberName }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Level Kemitraan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $memberLevel ?: 'Mitra' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">ID Kemitraan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $memberCode ?: $username }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Email</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $email ?: '-' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Username</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $username }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;color:#775e64;font-size:13px;">Password Sementara</td>
            <td align="right" style="padding:14px 20px;font-size:15px;font-weight:700;">{{ $password }}</td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $loginUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Masuk ke Akun Kemitraan</a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0 0 10px;">Demi keamanan akun, segera ubah password sementara setelah berhasil masuk dan jangan membagikannya kepada siapa pun.</p>
    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Jika membutuhkan bantuan, silakan menghubungi Tim Kemitraan DNY Skincare.</p>
@endsection
