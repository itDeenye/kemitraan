<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('bank_company', function (Blueprint $table): void {
                $table->string('bank_company_type')
                    ->default('company')
                    ->comment('Tipe rekening: company atau spread_payment')
                    ->change();
            });

            DB::table('bank_company')
                ->where('bank_company_type', 'partnership')
                ->update(['bank_company_type' => 'spread_payment']);

            return;
        }

        DB::statement(
            'ALTER TABLE `bank_company` MODIFY `bank_company_type` '
            ."ENUM('company', 'partnership', 'spread_payment') NOT NULL DEFAULT 'company' "
            ."COMMENT 'Tipe rekening: company atau spread_payment'"
        );

        DB::table('bank_company')
            ->where('bank_company_type', 'partnership')
            ->update(['bank_company_type' => 'spread_payment']);

        DB::statement(
            'ALTER TABLE `bank_company` MODIFY `bank_company_type` '
            ."ENUM('company', 'spread_payment') NOT NULL DEFAULT 'company' "
            ."COMMENT 'Tipe rekening: company atau spread_payment'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('bank_company', function (Blueprint $table): void {
                $table->string('bank_company_type')
                    ->default('company')
                    ->comment('Tipe rekening: company atau partnership')
                    ->change();
            });

            DB::table('bank_company')
                ->where('bank_company_type', 'spread_payment')
                ->update(['bank_company_type' => 'partnership']);

            return;
        }

        DB::statement(
            'ALTER TABLE `bank_company` MODIFY `bank_company_type` '
            ."ENUM('company', 'partnership', 'spread_payment') NOT NULL DEFAULT 'company' "
            ."COMMENT 'Tipe rekening: company atau partnership'"
        );

        DB::table('bank_company')
            ->where('bank_company_type', 'spread_payment')
            ->update(['bank_company_type' => 'partnership']);

        DB::statement(
            'ALTER TABLE `bank_company` MODIFY `bank_company_type` '
            ."ENUM('company', 'partnership') NOT NULL DEFAULT 'company' "
            ."COMMENT 'Tipe rekening: company atau partnership'"
        );
    }
};
