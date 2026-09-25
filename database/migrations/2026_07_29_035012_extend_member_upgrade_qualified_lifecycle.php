<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('member_upgrade_qualified', function (Blueprint $table) {
            $table->enum('member_upgrade_qualified_status', [
                'requested',
                'approved',
                'rejected',
                'scheduled',
                'applied',
                'cancelled',
            ])->default('requested')->comment('Status approval dan penerapan upgrade')->change();
            $table->dateTime('member_upgrade_qualified_approved_datetime')
                ->nullable()
                ->after('member_upgrade_qualified_admin_id')
                ->comment('Waktu upgrade disetujui administrator');
            $table->date('member_upgrade_qualified_effective_date')
                ->nullable()
                ->after('member_upgrade_qualified_approved_datetime')
                ->comment('Tanggal perubahan level mulai berlaku');
            $table->dateTime('member_upgrade_qualified_applied_datetime')
                ->nullable()
                ->after('member_upgrade_qualified_effective_date')
                ->comment('Waktu perubahan level benar-benar diterapkan');

            $table->index(
                ['member_upgrade_qualified_status', 'member_upgrade_qualified_effective_date'],
                'idx_member_upgrade_qualified_schedule'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('member_upgrade_qualified')
            ->whereIn('member_upgrade_qualified_status', ['scheduled', 'applied'])
            ->update(['member_upgrade_qualified_status' => 'approved']);
        DB::table('member_upgrade_qualified')
            ->where('member_upgrade_qualified_status', 'cancelled')
            ->update(['member_upgrade_qualified_status' => 'rejected']);

        Schema::table('member_upgrade_qualified', function (Blueprint $table) {
            $table->dropIndex('idx_member_upgrade_qualified_schedule');
            $table->dropColumn([
                'member_upgrade_qualified_approved_datetime',
                'member_upgrade_qualified_effective_date',
                'member_upgrade_qualified_applied_datetime',
            ]);
            $table->enum('member_upgrade_qualified_status', ['requested', 'approved', 'rejected'])
                ->default('requested')
                ->comment('Status proses kualifikasi upgrade')
                ->change();
        });
    }
};
