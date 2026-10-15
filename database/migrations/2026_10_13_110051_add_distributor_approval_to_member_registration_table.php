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
        Schema::table('member_registration', function (Blueprint $table): void {
            $table->unsignedInteger('member_registration_distributor_member_id')->default(0)
                ->comment('Distributor yang wajib meninjau pengajuan dari Agen');
            $table->enum('member_registration_distributor_approval_status', ['not_required', 'pending', 'approved', 'rejected'])
                ->default('not_required')->comment('Persetujuan Distributor sebelum persetujuan admin');
            $table->text('member_registration_distributor_approval_note')->nullable();
            $table->dateTime('member_registration_distributor_approval_datetime')->nullable();
            $table->index(['member_registration_distributor_member_id', 'member_registration_distributor_approval_status'], 'idx_registration_distributor_approval');
            $table->index(['member_registration_distributor_approval_status', 'member_registration_status'], 'idx_registration_admin_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_registration', function (Blueprint $table): void {
            $table->dropIndex('idx_registration_distributor_approval');
            $table->dropIndex('idx_registration_admin_approval');
            $table->dropColumn([
                'member_registration_distributor_member_id',
                'member_registration_distributor_approval_status',
                'member_registration_distributor_approval_note',
                'member_registration_distributor_approval_datetime',
            ]);
        });
    }
};
