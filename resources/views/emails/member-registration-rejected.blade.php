@extends('emails.layout')

@section('title', 'Registrasi Memerlukan Perbaikan')
@section('heading', 'Registrasi Memerlukan Perbaikan')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $memberName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Terima kasih telah melakukan registrasi kemitraan DNY Skincare. Setelah proses pemeriksaan, data registrasi Anda belum dapat disetujui dan memerlukan perbaikan.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff4f6;border:1px solid #f5ccd5;border-radius:12px;margin-bottom:26px;">
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">ID Registrasi</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $registrationId }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Level Kemitraan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $memberLevel }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Status</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;color:#a90028;">MEMERLUKAN PERBAIKAN</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;color:#775e64;font-size:13px;">Catatan Verifikasi</td>
            <td align="right" style="padding:14px 20px;font-size:15px;font-weight:700;">{{ $rejectionReason }}</td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $verificationUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Lihat Data Pendaftaran</a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0 0 10px;">Silakan periksa catatan verifikasi di atas dan hubungi pihak yang membantu pendaftaran Anda untuk melakukan perbaikan data.</p>
    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Jika membutuhkan bantuan, silakan menghubungi Tim Kemitraan DNY Skincare.</p>
@endsection
