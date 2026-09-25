<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->dropIndex('idx_member_agent_id');
            $table->dropIndex('idx_member_distributor_id');
            $table->dropColumn([
                'member_agent_member_id',
                'member_distributor_member_id',
            ]);
        });

        Schema::table('member_registration', function (Blueprint $table) {
            $table->dropIndex('idx_reg_agent_id');
            $table->dropIndex('idx_reg_distributor_id');
            $table->dropColumn([
                'member_registration_agent_member_id',
                'member_registration_distributor_member_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->unsignedInteger('member_agent_member_id')->default(0)->after('member_parent_member_id')->comment('ID Member Upline Level Agent');
            $table->unsignedInteger('member_distributor_member_id')->default(0)->after('member_agent_member_id')->comment('ID Member Upline Level Distributor');
            $table->index('member_agent_member_id', 'idx_member_agent_id');
            $table->index('member_distributor_member_id', 'idx_member_distributor_id');
        });

        Schema::table('member_registration', function (Blueprint $table) {
            $table->unsignedInteger('member_registration_agent_member_id')->default(0)->after('member_registration_parent_member_id')->comment('ID Member Upline Level Agent');
            $table->unsignedInteger('member_registration_distributor_member_id')->default(0)->after('member_registration_agent_member_id')->comment('ID Member Upline Level Distributor');
            $table->index('member_registration_agent_member_id', 'idx_reg_agent_id');
            $table->index('member_registration_distributor_member_id', 'idx_reg_distributor_id');
        });
    }
};
