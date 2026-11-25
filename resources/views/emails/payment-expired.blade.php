@extends('emails.layout')

@section('title', 'Pesanan Kedaluwarsa')
@section('heading', 'Pesanan Kedaluwarsa')

@section('content')
    <p style="font-size:16px;line-height:1.6;">Halo <strong>{{ $buyerName }}</strong>,</p>
    <p style="font-size:15px;line-height:1.7;">Pesanan <strong>{{ $orderNumber }}</strong> dibatalkan otomatis karena bukti transfer belum diunggah sebelum batas waktu upload berakhir.</p>
    <p style="font-size:15px;line-height:1.7;">Batas upload: <strong>{{ $expiredAt }} WIB</strong><br>Status: <strong>Kedaluwarsa</strong></p>
    <p style="font-size:15px;line-height:1.7;">Pesanan ini tidak dapat dilanjutkan. Silakan buat pesanan baru jika Anda masih ingin membeli. Jika sudah melakukan transfer tetapi belum sempat mengunggah bukti, hubungi admin dan sertakan bukti transfer serta kode transaksi.</p>
    <p><a href="{{ $orderUrl }}" style="color:#a90028;">Lihat detail pesanan</a></p>
@endsection
