<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_bank_account
 * Modul: Kemitraan
 *
 * Rekening bank tambahan milik member untuk tujuan penarikan komisi.
 * Member bisa punya beberapa rekening bank.
 * Untuk DNY, member bisa punya beberapa rekening bank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_bank_account', function (Blueprint $table) {
            $table->comment('Rekening bank tambahan milik member untuk tujuan penarikan komisi. Member bisa punya beberapa rekening bank.');
            $table->increments('member_bank_account_id')->comment('ID Rekening Bank Member');
            $table->unsignedInteger('member_bank_account_member_id')->comment('ID Member');
            $table->unsignedInteger('member_bank_account_bank_id')->comment('ID Ref Bank');
            $table->string('member_bank_account_name', 100)->default('')->comment('Nama Rekening (atas nama)');
            $table->string('member_bank_account_number', 50)->default('')->comment('Nomor Rekening');
            $table->unsignedTinyInteger('member_bank_account_is_active')->default(1)->comment('Status Aktif');

            $table->index('member_bank_account_member_id');
            $table->index('member_bank_account_bank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_bank_account');
    }
};
