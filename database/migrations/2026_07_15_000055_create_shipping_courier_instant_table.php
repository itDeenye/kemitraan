<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: shipping_courier_instant
 * Modul: Shipping (Polymorphic)
 *
 * Menyimpan data pengiriman menggunakan ojek online instan (Lalamove/GoSend/GrabExpress).
 * Mendukung relasi polymorphic untuk Transaksi (trx) maupun Retur (return).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_courier_instant', function (Blueprint $table) {
            $table->comment('Menyimpan data pengiriman menggunakan ojek online instan (polymorphic untuk trx/return).');
            $table->increments('shipping_courier_instant_id')->comment('ID Detail Pengiriman Instan');
            
            // Referensi Polymorphic
            $table->enum('shipping_courier_instant_ref_type', ['trx', 'return'])->comment('Tipe entitas pengirim (trx=Transaksi, return=Retur)');
            $table->unsignedInteger('shipping_courier_instant_ref_id')->comment('ID entitas pengirim (trx_id atau return_id)');
            
            $table->string('shipping_courier_instant_type', 10)->default('')->comment('Tipe instan');
            $table->string('shipping_courier_instant_expedition_name', 20)->default('')->comment('Nama ekspedisi (Grab/Gojek/Lalamove)');
            $table->string('shipping_courier_instant_expedition_service', 20)->default('')->comment('Layanan ekspedisi (Instant/Same Day)');
            $table->string('shipping_courier_instant_expedition_vehicle', 20)->default('')->comment('Kendaraan (Bike/Car)');
            $table->string('shipping_courier_instant_estimation_hours', 10)->default('')->comment('Estimasi pengiriman (jam)');
            $table->string('shipping_courier_instant_order_id', 50)->default('')->comment('Order ID dari sistem ekspedisi');
            $table->string('shipping_courier_instant_awb', 50)->nullable()->comment('Nomor resi/tracking code');
            $table->unsignedInteger('shipping_courier_instant_admin_fee')->default(0)->comment('Biaya admin aplikasi (Rp)');
            $table->unsignedInteger('shipping_courier_instant_cost')->default(0)->comment('Biaya kirim (Rp)');
            $table->unsignedInteger('shipping_courier_instant_insurance')->default(0)->comment('Biaya asuransi (Rp)');
            $table->unsignedInteger('shipping_courier_instant_package_weight')->default(0)->comment('Total berat paket (gram)');

            // === Asal (Origin) ===
            $table->string('shipping_courier_instant_origin_name', 50)->default('')->comment('Nama pengirim asal');
            $table->string('shipping_courier_instant_origin_phone', 16)->default('')->comment('Telepon pengirim asal');
            $table->text('shipping_courier_instant_origin_address')->comment('Alamat asal');
            $table->string('shipping_courier_instant_origin_address_note', 255)->default('')->comment('Catatan alamat asal');
            $table->decimal('shipping_courier_instant_origin_latitude', 10, 8)->nullable()->comment('Garis lintang asal');
            $table->decimal('shipping_courier_instant_origin_longitude', 11, 8)->nullable()->comment('Garis bujur asal');

            // === Tujuan (Destination) ===
            $table->string('shipping_courier_instant_destination_name', 50)->default('')->comment('Nama penerima');
            $table->string('shipping_courier_instant_destination_phone', 16)->default('')->comment('Telepon penerima');
            $table->text('shipping_courier_instant_destination_address')->comment('Alamat tujuan');
            $table->string('shipping_courier_instant_destination_address_note', 255)->default('')->comment('Catatan alamat tujuan');
            $table->decimal('shipping_courier_instant_destination_latitude', 10, 8)->nullable()->comment('Garis lintang tujuan');
            $table->decimal('shipping_courier_instant_destination_longitude', 11, 8)->nullable()->comment('Garis bujur tujuan');

            $table->index(['shipping_courier_instant_ref_type', 'shipping_courier_instant_ref_id'], 'idx_instant_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_courier_instant');
    }
};
