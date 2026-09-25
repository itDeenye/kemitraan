<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: trx
 * Modul: Transaksi
 *
 * Header transaksi penjualan/pembelian. Mencatat seller/buyer,
 * metode pembayaran, metode pengiriman, total harga, diskon, ongkir,
 * dan status lifecycle order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx', function (Blueprint $table) {
            $table->comment('Header transaksi penjualan/pembelian. Mencatat seller/buyer, metode pembayaran, metode pengiriman, total harga, diskon, ongkir, dan status lifecycle order.');
            $table->increments('trx_id')->comment('ID transaksi');
            $table->string('trx_code', 20)->comment('Kode transaksi');
            $table->unsignedInteger('trx_parent_trx_id')->default(0)->comment('ID Transaksi parent/downline yang memicu replenishment PO berantai ini');
            $table->tinyInteger('trx_is_preorder')->default(0)->comment('Flag apakah transaksi ini merupakan Pre-Order (PO) karena kekurangan stok');
            $table->enum('trx_seller_type', ['warehouse', 'distributor', 'agent', 'reseller'])->comment('Tipe penjual (warehouse=pusat/gudang, distributor, agent, reseller)');
            $table->unsignedInteger('trx_seller_id')->comment('ID penjual (warehouse_id atau member_id)');
            $table->enum('trx_buyer_type', ['distributor', 'agent', 'reseller', 'customer'])->comment('Tipe pembeli (distributor, agent, reseller, customer=end-user retail)');
            $table->unsignedInteger('trx_buyer_id')->comment('ID pembeli (member_id atau customer_id)');
            $table->enum('trx_type', ['registration', 'activation', 'stock', 'retail', 'upgrade'])->comment('Jenis transaksi');
            $table->unsignedInteger('trx_reference_id')->default(0)->comment('ID referensi tambahan (misal: ID registrasi/upgrade jika ada)');
            $table->unsignedInteger('trx_total_price')->comment('Total harga produk setelah diskon produk');
            $table->decimal('trx_discount', 4, 0)->unsigned()->default(0)->comment('Persentase diskon total');
            $table->unsignedInteger('trx_discount_value')->default(0)->comment('Nilai diskon total');
            $table->unsignedInteger('trx_grand_total_price')->default(0)->comment('Total harga setelah diskon');
            $table->unsignedInteger('trx_shipping_charge')->default(0)->comment('Biaya kirim');
            $table->unsignedInteger('trx_payment_charge')->default(0)->comment('Biaya layanan pembayaran');
            $table->unsignedInteger('trx_grand_total_nett_price')->default(0)->comment('Total harga setelah biaya');
            $table->unsignedInteger('trx_bill_remaining')->default(0)->comment('Nominal sisa tagihan');
            $table->unsignedInteger('trx_bill_augment')->default(0)->comment('Nominal tambahan tagihan (kode unik)');
            $table->unsignedInteger('trx_bill_amount')->default(0)->comment('Total tagihan');
            $table->enum('trx_payment_method', ['transfer', 'cash'])->nullable()->comment('Metode pembayaran');
            $table->enum('trx_shipping_method', ['courier_express', 'courier_instant', 'pickup', 'courier_manual'])->nullable()->comment('Metode pengiriman');
            $table->enum('trx_status', [
                'waiting_payment', 'waiting_payment_approval', 'cancelled',
                'processing', 'delivered', 'ready_to_pickup', 'picked_up',
                'received', 'completed',
            ])->default('waiting_payment')->comment('Status transaksi');
            $table->dateTime('trx_status_datetime')->comment('Waktu terakhir perubahan status');
            $table->dateTime('trx_datetime')->comment('Waktu dibuatnya transaksi');

            $table->index('trx_buyer_id');
            $table->index('trx_code');
            $table->index('trx_seller_id');
            $table->index('trx_parent_trx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx');
    }
};
