@extends('emails.layout')

@section('title', 'Pesanan Anda Telah Dikirim')
@section('heading', 'Pesanan Anda Telah Dikirim')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $recipientName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Pesanan kemitraan DNY Skincare Anda telah diproses dan diserahkan kepada kurir. Berikut informasi pengirimannya.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff4f6;border:1px solid #f5ccd5;border-radius:12px;margin-bottom:26px;">
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Nomor Pesanan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $orderNumber }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Tanggal Pesanan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $orderDate }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Tanggal Pengiriman</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $shippingDate }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Kurir</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $courier }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Nomor Resi</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $trackingNumber }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;color:#775e64;font-size:13px;">Alamat Pengiriman</td>
            <td align="right" style="padding:14px 20px;font-size:15px;font-weight:700;">{{ $shippingAddress }}</td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $trackingUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Lacak Pesanan</a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Pastikan paket diterima dalam kondisi baik dan lakukan konfirmasi penerimaan melalui aplikasi setelah barang sampai.</p>
@endsection
