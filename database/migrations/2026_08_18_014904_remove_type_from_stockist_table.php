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
        Schema::table('stockist', function (Blueprint $table) {
            $table->dropColumn('stockist_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stockist', function (Blueprint $table) {
            $table->enum('stockist_type', ['mobile', 'master'])
                ->default('master')
                ->after('stockist_member_id')
                ->comment('Tipe stockist lama');
        });
    }
};
