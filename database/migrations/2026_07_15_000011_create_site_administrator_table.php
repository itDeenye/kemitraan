<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: site_administrator
 * Modul: Auth & Admin
 *
 * Tabel utama untuk semua akun administrator sistem.
 * Digunakan untuk login backoffice, profile admin, dan mengelola setting/data
 * di dashboard admin. Setiap admin memiliki satu group role
 * (`site_administrator_group`) yang menentukan hak aksesnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_administrator', function (Blueprint $table) {
            $table->comment('Tabel utama untuk semua akun administrator sistem. Digunakan untuk login backoffice, profile admin, dan mengelola setting/data di dashboard admin. Setiap admin memiliki satu group role (`site_administrator_group`) yang menentukan hak aksesnya.');
            $table->increments('administrator_id')->comment('ID Administrator');
            $table->unsignedInteger('administrator_administrator_group_id')->comment('ID Grup Administrator');
            $table->string('administrator_username', 50)->default('')->comment('Username');
            $table->string('administrator_password', 255)->default('')->comment('Kata Sandi');
            $table->string('administrator_name', 50)->default('')->comment('Nama Administrator');
            $table->string('administrator_email', 50)->default('')->comment('Email Administrator');
            $table->string('administrator_image', 255)->default('')->comment('Foto Profil Administrator');
            $table->dateTime('administrator_last_login')->nullable()->comment('Tanggal Terakhir Login');
            $table->unsignedTinyInteger('administrator_is_active')->default(1)->comment('Status Aktif');

            $table->index('administrator_administrator_group_id');
            $table->index('administrator_username');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_administrator');
    }
};
