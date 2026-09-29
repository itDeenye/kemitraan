@extends('emails.layout')

@section('title', 'Screening Stok Pesanan Disetujui')
@section('heading', 'Screening Stok Disetujui')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $trx->buyer?->member_name }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Screening stok untuk pesanan Anda telah disetujui. Anda sekarang dapat mengunggah bukti pembayaran.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff4f6;border:1px solid #f5ccd5;border-radius:12px;margin-bottom:26px;">
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Nomor Pesanan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $trx->trx_code }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #f5ccd5;color:#775e64;font-size:13px;">Jenis Pesanan</td>
            <td align="right" style="padding:14px 20px;border-bottom:1px solid #f5ccd5;font-size:15px;font-weight:700;">{{ $trx->trx_is_preorder ? 'PO' : 'Reguler' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px;color:#775e64;font-size:13px;">Total Pembayaran</td>
            <td align="right" style="padding:14px 20px;font-size:15px;font-weight:700;">Rp{{ number_format((int) $trx->trx_bill_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Lihat dan Bayar Pesanan</a>
            </td>
        </tr>
    </table>

    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Pastikan data pembayaran sesuai dengan detail pesanan.</p>
@endsection
