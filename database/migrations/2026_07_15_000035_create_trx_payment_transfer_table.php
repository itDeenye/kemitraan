<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: trx_payment_transfer
 * Modul: Transaksi
 *
 * Mencatat pembayaran via transfer bank dan status verifikasinya.
 * Menyimpan bukti transfer, nominal, dan approval admin.
 * Menyimpan bukti transfer, nominal, dan approval admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_payment_transfer', function (Blueprint $table) {
            $table->comment('Mencatat pembayaran via transfer bank dan status verifikasinya. Menyimpan bukti transfer, nominal, dan approval admin.');
            $table->increments('payment_transfer_id')->comment('Primary Key');
            $table->unsignedInteger('payment_transfer_trx_id')->comment('Relasi ke trx.trx_id');
            $table->unsignedInteger('payment_transfer_bill_remaining')->default(0)->comment('Nominal sisa tagihan');
            $table->unsignedInteger('payment_transfer_bill_augment')->default(0)->comment('Nominal kode unik tagihan');
            $table->unsignedInteger('payment_transfer_bill_amount')->comment('Nominal tagihan yang harus dibayar');
            $table->unsignedInteger('payment_transfer_bank_id')->default(0)->comment('ID bank tujuan transfer');
            $table->string('payment_transfer_account_name', 100)->default('')->comment('Nama pemilik rekening tujuan');
            $table->string('payment_transfer_account_number', 50)->default('')->comment('Nomor rekening tujuan');
            $table->unsignedInteger('payment_transfer_amount')->comment('Nominal yang ditransfer');
            $table->dateTime('payment_transfer_datetime')->comment('Tanggal Transfer');
            $table->string('payment_transfer_receipt_file', 255)->nullable()->comment('Path bukti transfer (max 5MB)');
            $table->enum('payment_transfer_approval_status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Status verifikasi');
            $table->unsignedInteger('payment_transfer_approval_admin_id')->default(0)->comment('Admin yang approve/reject');
            $table->dateTime('payment_transfer_approval_datetime')->nullable()->comment('Tanggal Approval');
            $table->text('payment_transfer_note')->comment('Catatan tambahan pembayaran');

            $table->index('payment_transfer_trx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_payment_transfer');
    }
};
