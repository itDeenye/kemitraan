<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: bonus
 * Modul: Komisi & Reward
 *
 * Menyimpan ringkasan saldo komisi dan pencapaian poin bulanan member.
 * Disesuaikan untuk DNY Skincare (bukan binary/multilevel MLM).
 * Saldo (balance) bertambah saat proses perhitungan komisi bulanan selesai,
 * dan berkurang saat member melakukan withdrawal/pencairan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus', function (Blueprint $table) {
            $table->comment('Menyimpan ringkasan komisi member. Disesuaikan untuk DNY Skincare (bukan binary/multilevel MLM). Poin berjalan berada di tabel terpisah. Sisa saldo dihitung dinamis dari total_acc - total_paid.');
            $table->unsignedInteger('bonus_member_id')->primary()->comment('ID Member');
            $table->unsignedInteger('bonus_acc')->default(0)->comment('Total akumulasi komisi yang pernah didapatkan (Rp)');
            $table->unsignedInteger('bonus_paid')->default(0)->comment('Total komisi yang sudah ditransfer/cair (Rp)');
            $table->dateTime('bonus_last_updated_datetime')->nullable()->comment('Waktu pembaruan terakhir');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus');
    }
};
