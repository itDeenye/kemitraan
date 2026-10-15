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
        Schema::table('member', function (Blueprint $table): void {
            $table->enum('member_identity_type', ['KTP', 'SIM', 'BPJS', 'PASPOR'])
                ->default('KTP')->comment('Jenis Identitas Member')->change();
            $table->string('member_npwp', 30)->nullable()->comment('Nomor Pokok Wajib Pajak');
        });

        Schema::table('member_registration', function (Blueprint $table): void {
            $table->enum('member_registration_identity_type', ['KTP', 'SIM', 'BPJS', 'PASPOR'])
                ->default('KTP')->comment('Jenis Identitas')->change();
            $table->string('member_registration_npwp', 30)->nullable()->comment('Nomor Pokok Wajib Pajak');
            $table->string('member_registration_image', 255)->nullable()->comment('Foto profil calon mitra');
            $table->string('member_registration_payment_proof_image', 255)->nullable()->comment('Bukti transfer pendaftaran');
            $table->json('member_registration_bank_accounts')->nullable()->comment('Snapshot rekening pendaftaran');
            $table->index(['member_registration_member_id', 'member_registration_status', 'member_registration_id'], 'idx_registration_member_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_registration', function (Blueprint $table): void {
            $table->dropIndex('idx_registration_member_status');
            $table->dropColumn([
                'member_registration_npwp',
                'member_registration_image',
                'member_registration_payment_proof_image',
                'member_registration_bank_accounts',
            ]);
        });
        Schema::table('member', function (Blueprint $table): void {
            $table->dropColumn('member_npwp');
        });
        // Keep BPJS valid on rollback so existing identity values are not lost.
    }
};
