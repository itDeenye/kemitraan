<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_level', function (Blueprint $table) {
            $table->comment('Master level bisnis member. Terpisah dari member_group yang mengatur hak akses menu.');
            $table->increments('member_level_id')->comment('ID level member');
            $table->string('member_level_code', 20)->comment('Kode unik level');
            $table->string('member_level_name', 100)->comment('Nama level');
            $table->text('member_level_description')->nullable()->comment('Deskripsi level');
            $table->unsignedInteger('member_level_min_order')->default(0)->comment('Minimum nominal order untuk level ini');
            $table->unsignedInteger('member_level_sort_order')->default(0)->comment('Urutan hierarki, angka terkecil paling tinggi');
            $table->unsignedTinyInteger('member_level_is_active')->default(1)->comment('Status aktif level');

            $table->unique('member_level_code');
        });

        DB::table('member_level')->insert([
            [
                'member_level_id' => 1,
                'member_level_code' => 'DST',
                'member_level_name' => 'Distributor',
                'member_level_description' => 'Level kemitraan Distributor',
                'member_level_min_order' => 0,
                'member_level_sort_order' => 1,
                'member_level_is_active' => 1,
            ],
            [
                'member_level_id' => 2,
                'member_level_code' => 'AGT',
                'member_level_name' => 'Agent',
                'member_level_description' => 'Level kemitraan Agent',
                'member_level_min_order' => 0,
                'member_level_sort_order' => 2,
                'member_level_is_active' => 1,
            ],
            [
                'member_level_id' => 3,
                'member_level_code' => 'RSL',
                'member_level_name' => 'Reseller',
                'member_level_description' => 'Level kemitraan Reseller',
                'member_level_min_order' => 0,
                'member_level_sort_order' => 3,
                'member_level_is_active' => 1,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('member_level');
    }
};
