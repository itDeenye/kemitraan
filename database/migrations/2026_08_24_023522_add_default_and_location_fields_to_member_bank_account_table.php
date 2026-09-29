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
        Schema::table('member_bank_account', function (Blueprint $table) {
            $table->string('member_bank_account_city', 50)
                ->nullable()
                ->after('member_bank_account_number')
                ->comment('Kota pembukaan rekening');
            $table->string('member_bank_account_branch', 50)
                ->nullable()
                ->after('member_bank_account_city')
                ->comment('Cabang bank');
            $table->unsignedTinyInteger('member_bank_account_is_default')
                ->default(0)
                ->after('member_bank_account_is_active')
                ->comment('Rekening utama (1=ya, 0=tidak)');

            $table->index(
                ['member_bank_account_member_id', 'member_bank_account_is_default'],
                'idx_member_bank_account_default'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_bank_account', function (Blueprint $table) {
            $table->dropIndex('idx_member_bank_account_default');
            $table->dropColumn([
                'member_bank_account_city',
                'member_bank_account_branch',
                'member_bank_account_is_default',
            ]);
        });
    }
};
