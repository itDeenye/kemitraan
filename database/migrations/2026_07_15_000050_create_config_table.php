<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: config
 * Modul: Audit & Konfigurasi
 *
 * Tabel global parameter konfigurasi bisnis.
 * Menyimpan key-value config yang dapat diubah oleh admin
 * tanpa deploy ulang (commission rates, upgrade rules, dll).
 * Menyimpan key-value config yang dapat diubah oleh admin tanpa deploy ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config', function (Blueprint $table) {
            $table->comment('Tabel global parameter konfigurasi bisnis. Menyimpan key-value config yang dapat diubah oleh admin tanpa deploy ulang (commission rates, upgrade rules, dll).');
            $table->increments('config_id')->comment('ID Config');
            $table->string('config_key', 255)->comment('Key Config');
            $table->text('config_value')->nullable()->comment('Value Config');
            $table->string('config_type', 31)->default('string')->comment('Type Config (string|integer|json|boolean)');
            $table->dateTime('config_created_datetime')->comment('Waktu config dibuat');
            $table->dateTime('config_updated_datetime')->comment('Waktu config diupdate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config');
    }
};
