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
        Schema::table('member', function (Blueprint $table) {
            $table->string('member_code', 30)
                ->comment('Kode unik member berurutan global dengan format DNY-000001')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->string('member_code', 30)
                ->comment('Unique ID format aaaa/bbbb/cccc atau ST-aaaa/bbbb/cccc')
                ->change();
        });
    }
};
