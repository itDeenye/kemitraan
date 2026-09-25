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
        Schema::table('config', function (Blueprint $table) {
            $table->string('config_scope', 31)
                ->default('system')
                ->after('config_type')
                ->index()
                ->comment('Kelompok konfigurasi (system|commission)');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config', function (Blueprint $table) {
            $table->dropIndex(['config_scope']);
            $table->dropColumn('config_scope');
        });
    }
};
