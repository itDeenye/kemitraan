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
        Schema::table('bank_company', function (Blueprint $table): void {
            $table->enum('bank_company_type', ['company', 'partnership'])
                ->default('company')
                ->after('bank_company_id')
                ->comment('Tipe rekening: company atau partnership');
            $table->index('bank_company_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_company', function (Blueprint $table): void {
            $table->dropIndex(['bank_company_type']);
            $table->dropColumn('bank_company_type');
        });
    }
};
