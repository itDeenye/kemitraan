<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_account
 * Modul: Kemitraan (Auth)
 *
 * Kredensial login member ke website/aplikasi mitra.
 * Relasi 1:1 dengan `member` — PK = member_id.
 * Dipisah dari tabel `member` agar data auth terpisah dari biodata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_account', function (Blueprint $table) {
            $table->comment('Kredensial login member ke website/aplikasi mitra. Dipisah dari tabel `member` agar data auth terpisah dari biodata.');
            $table->increments('member_account_id')->comment('ID Akun Login Member');
            $table->unsignedInteger('member_account_member_id')->default(0)->comment('ID Member (profil/biodata)');
            $table->unsignedInteger('member_account_member_group_id')->default(0)->comment('ID Group Akses Member');
            $table->string('member_account_username', 50)->unique()->comment('Username Login');
            $table->text('member_account_password')->comment('Password Login');
            $table->char('member_account_pin', 6)->default('')->comment('PIN Pengaman');
            $table->dateTime('member_account_last_login_datetime')->nullable()->comment('Tanggal login terakhir');

            $table->index('member_account_member_id');
            $table->index('member_account_member_group_id');
            $table->index('member_account_last_login_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_account');
    }
};
