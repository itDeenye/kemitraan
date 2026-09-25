<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('trx_detail')) {
            return;
        }

        $columns = array_values(array_filter([
            \Illuminate\Support\Facades\Schema::hasColumn('trx_detail', 'trx_detail_qty_preorder')
                ? 'trx_detail_qty_preorder' : null,
            \Illuminate\Support\Facades\Schema::hasColumn('trx_detail', 'trx_detail_qty_reserved')
                ? 'trx_detail_qty_reserved' : null,
        ]));

        if ($columns !== []) {
            \Illuminate\Support\Facades\Schema::table('trx_detail', function (\Illuminate\Database\Schema\Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('trx_detail')) {
            return;
        }

        \Illuminate\Support\Facades\Schema::table('trx_detail', function (\Illuminate\Database\Schema\Blueprint $table): void {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('trx_detail', 'trx_detail_qty_preorder')) {
                $table->unsignedInteger('trx_detail_qty_preorder')->default(0);
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('trx_detail', 'trx_detail_qty_reserved')) {
                $table->unsignedInteger('trx_detail_qty_reserved')->default(0);
            }
        });
    }
};
