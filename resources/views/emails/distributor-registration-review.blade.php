@extends('emails.layout')

@section('title', 'Pendaftaran Memerlukan Persetujuan')
@section('heading', 'Pendaftaran Memerlukan Persetujuan')

@section('content')
    <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Halo <strong>{{ $distributorName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;margin:0 0 24px;">Agen Utama <strong>{{ $agentName }}</strong> mengajukan pendaftaran Reseller <strong>{{ $applicantName }}</strong> ({{ $registrationId }}) yang memerlukan persetujuan Anda sebelum diteruskan kepada Admin.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
        <tr>
            <td style="background:#a90028;border-radius:10px;">
                <a href="{{ $reviewUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Periksa Pendaftaran</a>
            </td>
        </tr>
    </table>
    <p style="font-size:13px;line-height:1.6;color:#775e64;margin:0;">Silakan masuk ke akun Distributor Anda untuk memeriksa data dan menyetujui atau menolak pengajuan.</p>
@endsection
