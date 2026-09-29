@extends('emails.layout')

@section('title', 'Bukti Pembayaran Berhasil Diterima')
@section('heading', 'Bukti Pembayaran Berhasil Diterima')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $buyerName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Bukti pembayaran Anda telah berhasil diterima oleh sistem dan saat ini sedang menunggu proses verifikasi.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff4f6;border:1px solid #f5ccd5;border-radius:12px;margin-bottom:26px;">
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Nomor Pesanan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $orderNumber }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Nominal Pembayaran</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $paymentAmount }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Tanggal Upload</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $uploadDate }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;color:#775e64;font-size:13px;">Status</td>
            <td align="right" style="padding:14px 20px;font-size:15px;font-weight:700;color:#a66b00;">MENUNGGU VERIFIKASI</td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Lihat Status Pesanan</a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Mohon menunggu proses verifikasi pembayaran. Anda akan menerima pemberitahuan berikutnya setelah pembayaran selesai diperiksa.</p>
@endsection
