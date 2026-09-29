<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: return
 * Modul: Retur
 *
 * Pengajuan retur barang rusak atau salah kirim.
 * Tabel baru khusus DNY — mengelola permohonan return barang
 * dari mitra ke admin pusat beserta bukti foto/video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return', function (Blueprint $table) {
            $table->comment('Pengajuan retur barang rusak atau salah kirim. Tabel baru khusus DNY — mengelola permohonan return barang dari mitra ke admin pusat beserta bukti foto/video.');
            $table->increments('return_id')->comment('ID Return');
            $table->string('return_code', 30)->default('')->comment('Kode return unik');
            $table->unsignedInteger('return_trx_id')->comment('ID Transaksi asal');
            $table->unsignedInteger('return_member_id')->comment('ID Member yang mengajukan');
            $table->string('return_description', 500)->default('')->comment('Deskripsi alasan return');
            $table->longText('return_attachment_image_url_json')->nullable()->comment('Array path foto bukti (JSON)');
            $table->string('return_attachment_video_url', 255)->nullable()->comment('Path video bukti');
            $table->enum('return_status', ['submitted', 'under_review', 'approved', 'rejected', 'completed'])->default('submitted')->comment('Status return');
            $table->enum('return_shipping_cost_bearer', ['warehouse', 'member'])->default('warehouse')->comment('Penanggung ongkir (warehouse=pusat, member=mitra)');
            $table->enum('return_shipping_method', ['courier_express', 'courier_instant', 'pickup', 'courier_manual'])->nullable()->comment('Metode pengiriman return (dari mitra ke pusat)');
            $table->unsignedInteger('return_shipping_cost')->default(0)->comment('Biaya kirim return');
            $table->unsignedInteger('return_approved_by')->default(0)->comment('ID Admin yang approve');
            $table->dateTime('return_created_datetime')->comment('Tanggal pengajuan');

            $table->unique('return_code');
            $table->index('return_trx_id');
            $table->index('return_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return');
    }
};
