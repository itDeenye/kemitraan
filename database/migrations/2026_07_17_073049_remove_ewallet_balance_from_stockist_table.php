<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stockist', function (Blueprint $table) {
            $table->dropColumn('stockist_ewallet_balance');
        });
    }

    public function down(): void
    {
        Schema::table('stockist', function (Blueprint $table) {
            $table->integer('stockist_ewallet_balance')
                ->default(0)
                ->after('stockist_note')
                ->comment('Saldo dana stockist legacy');
        });
    }
};
