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
        Schema::dropIfExists('bonus_log');
        Schema::dropIfExists('bonus');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('bonus', function (Blueprint $table): void {
            $table->comment('Menyimpan ringkasan komisi member.');
            $table->unsignedInteger('bonus_member_id')->primary()->comment('ID Member');
            $table->unsignedInteger('bonus_acc')->default(0)->comment('Total akumulasi komisi (Rp)');
            $table->unsignedInteger('bonus_paid')->default(0)->comment('Total komisi dibayar (Rp)');
            $table->dateTime('bonus_last_updated_datetime')->nullable()->comment('Waktu pembaruan terakhir');
        });

        Schema::create('bonus_log', function (Blueprint $table): void {
            $table->comment('Log mutasi saldo komisi member.');
            $table->increments('bonus_log_id')->comment('ID log bonus');
            $table->unsignedInteger('bonus_log_member_id')->comment('ID Member');
            $table->unsignedInteger('bonus_log_value')->default(0)->comment('Nilai mutasi (Rp)');
            $table->enum('bonus_log_type', ['in', 'out'])->default('in')->comment('Tipe mutasi');
            $table->string('bonus_log_note', 255)->default('')->comment('Catatan mutasi');
            $table->dateTime('bonus_log_datetime')->comment('Waktu mutasi');

            $table->index('bonus_log_member_id');
        });
    }
};
