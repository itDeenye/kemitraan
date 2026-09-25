<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reward_share_profit', function (Blueprint $table) {
            $table->comment('Kewajiban pembagian pembayaran per transaksi kepada setiap penerima spread payment.');
            $table->increments('reward_share_profit_id');
            $table->unsignedInteger('reward_share_profit_trx_id')->comment('Relasi ke trx.trx_id');
            $table->enum('reward_share_profit_recipient_type', ['pbf', 'partnership'])->comment('Jenis penerima pembagian pembayaran');
            $table->unsignedInteger('reward_share_profit_recipient_id')->default(0)->comment('ID penerima sesuai recipient_type');
            $table->unsignedInteger('reward_share_profit_bank_id')->default(0)->comment('Snapshot ID bank tujuan');
            $table->string('reward_share_profit_account_name', 100)->default('')->comment('Snapshot nama pemilik rekening tujuan');
            $table->string('reward_share_profit_account_number', 50)->default('')->comment('Snapshot nomor rekening tujuan');
            $table->decimal('reward_share_profit_percentage', 8, 4)->unsigned()->default(0)->comment('Persentase pembagian saat transaksi dibuat');
            $table->unsignedBigInteger('reward_share_profit_amount')->default(0)->comment('Nominal yang wajib ditransfer');
            $table->enum('reward_share_profit_status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending')->comment('Status pembayaran bagian penerima');
            $table->unsignedInteger('reward_share_profit_verified_by')->default(0)->comment('Administrator yang memverifikasi');
            $table->dateTime('reward_share_profit_verified_datetime')->nullable()->comment('Waktu verifikasi');
            $table->text('reward_share_profit_note')->nullable()->comment('Catatan verifikasi atau penolakan');
            $table->dateTime('reward_share_profit_created_datetime')->comment('Waktu kewajiban dibuat');

            $table->index('reward_share_profit_trx_id');
            $table->index(['reward_share_profit_trx_id', 'reward_share_profit_status'], 'reward_share_profit_trx_status_index');
            $table->index(['reward_share_profit_recipient_type', 'reward_share_profit_recipient_id'], 'reward_share_profit_recipient_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_share_profit');
    }
};
