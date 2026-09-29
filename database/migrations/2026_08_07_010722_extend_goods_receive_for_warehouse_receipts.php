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
        Schema::table('goods_receive', function (Blueprint $table) {
            $table->unsignedInteger('goods_receive_administrator_id')
                ->nullable()
                ->after('goods_receive_id')
                ->comment('ID administrator pembuat penerimaan warehouse');
            $table->string('goods_receive_supplier_name', 150)
                ->nullable()
                ->after('goods_receive_seller_id')
                ->comment('Nama supplier pengirim barang ke warehouse');
            $table->dateTime('goods_receive_status_datetime')
                ->nullable()
                ->after('goods_receive_status')
                ->comment('Waktu perubahan status terakhir');
            $table->enum('goods_receive_buyer_type', ['warehouse', 'distributor', 'agent', 'reseller'])
                ->comment('Tipe pembeli atau penerima stok')
                ->change();
            $table->enum('goods_receive_seller_type', ['supplier', 'warehouse', 'distributor', 'agent', 'reseller'])
                ->comment('Tipe penjual atau pengirim barang')
                ->change();
            $table->enum('goods_receive_status', ['transit', 'partial', 'completed', 'returned', 'cancelled'])
                ->default('transit')
                ->comment('Status penerimaan barang')
                ->change();

            $table->index('goods_receive_administrator_id', 'idx_goods_receive_admin');
            $table->index(
                ['goods_receive_buyer_type', 'goods_receive_buyer_id'],
                'idx_goods_receive_buyer_type_id',
            );
            $table->index(
                ['goods_receive_status', 'goods_receive_status_datetime'],
                'idx_goods_receive_status_date',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receive', function (Blueprint $table) {
            $table->dropIndex('idx_goods_receive_admin');
            $table->dropIndex('idx_goods_receive_buyer_type_id');
            $table->dropIndex('idx_goods_receive_status_date');
            $table->enum('goods_receive_buyer_type', ['distributor', 'agent', 'reseller'])
                ->comment('Tipe pembeli atau penerima stok')
                ->change();
            $table->enum('goods_receive_seller_type', ['warehouse', 'distributor', 'agent', 'reseller'])
                ->comment('Tipe penjual atau pengirim barang')
                ->change();
            $table->enum('goods_receive_status', ['transit', 'partial', 'completed', 'returned'])
                ->default('transit')
                ->comment('Status penerimaan barang')
                ->change();
            $table->dropColumn([
                'goods_receive_administrator_id',
                'goods_receive_supplier_name',
                'goods_receive_status_datetime',
            ]);
        });
    }
};
