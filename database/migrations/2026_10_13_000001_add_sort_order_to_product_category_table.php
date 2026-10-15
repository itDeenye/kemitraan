<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_category', function (Blueprint $table): void {
            $table->unsignedSmallInteger('product_category_sort_order')
                ->default(0)
                ->after('product_category_is_active')
                ->index()
                ->comment('Urutan kategori pada katalog');
        });

        DB::table('product_category')
            ->orderBy('product_category_id')
            ->pluck('product_category_id')
            ->each(function (int $categoryId, int $index): void {
                DB::table('product_category')
                    ->where('product_category_id', $categoryId)
                    ->update(['product_category_sort_order' => $index + 1]);
            });
    }

    public function down(): void
    {
        Schema::table('product_category', function (Blueprint $table): void {
            $table->dropColumn('product_category_sort_order');
        });
    }
};
