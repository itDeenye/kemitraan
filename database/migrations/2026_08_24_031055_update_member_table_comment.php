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
        Schema::table('member', function (Blueprint $table) {
            $table->comment('Tabel inti profil member/mitra DNY. Menyimpan biodata, identitas, pohon jaringan, level, status keanggotaan, foto profil, dan media sosial. Alamat serta rekening disimpan pada tabel member_address dan member_bank_account.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->comment('Tabel inti profil member/mitra DNY. Menyimpan biodata, pohon jaringan, status keanggotaan, alamat domisili, data bank, dan identitas. Pusat relasi yang diacu oleh hampir seluruh modul.');
        });
    }
};
