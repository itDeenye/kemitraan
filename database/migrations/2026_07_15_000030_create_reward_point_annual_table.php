<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_point_annual
 * Modul: Reward (Tahunan)
 *
 * Menyimpan akumulasi saldo poin reward tahunan member per tahun.
 * Data disimpan multi-tahun sehingga histori poin tidak hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_point_annual', function (Blueprint $table) {
            $table->comment('Menyimpan akumulasi saldo poin reward tahunan member per tahun. Data disimpan multi-tahun sehingga histori poin tidak hilang.');
            $table->increments('reward_point_annual_id')->comment('ID Poin Reward Tahunan');
            $table->unsignedInteger('reward_point_annual_member_id')->comment('ID Member');
            $table->unsignedSmallInteger('reward_point_annual_year')->comment('Tahun reward');
            $table->unsignedInteger('reward_point_annual_acc')->default(0)->comment('Total akumulasi poin reward tahunan didapat (acc)');
            $table->unsignedInteger('reward_point_annual_paid')->default(0)->comment('Total poin reward tahunan yang sudah dicairkan/diklaim (paid)');
            $table->dateTime('reward_point_annual_last_updated_datetime')->nullable()->comment('Tanggal update terakhir');

            $table->unique(['reward_point_annual_member_id', 'reward_point_annual_year'], 'uq_rpa_member_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_point_annual');
    }
};
