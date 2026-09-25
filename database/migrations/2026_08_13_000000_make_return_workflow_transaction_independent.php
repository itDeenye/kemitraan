<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('return')->where('return_status', 'under_review')->update(['return_status' => 'submitted']);
        DB::table('return_status_log')
            ->where('return_status_log_status', 'under_review')
            ->update(['return_status_log_status' => 'submitted']);
        DB::table('trx')->where('trx_type', 'return_replacement')->update(['trx_type' => 'stock']);

        Schema::table('return', function (Blueprint $table) {
            $table->unsignedInteger('return_member_address_id')->default(0)->after('return_member_id')->comment('ID alamat pickup dan penerimaan pengganti');
            $table->string('return_pickup_name', 100)->default('')->after('return_member_address_id')->comment('Nama penerima pada alamat member');
            $table->string('return_pickup_phone', 20)->default('')->after('return_pickup_name')->comment('Nomor telepon alamat member');
            $table->text('return_pickup_address')->nullable()->after('return_pickup_phone')->comment('Snapshot alamat member');
            $table->unsignedInteger('return_pickup_province_id')->default(0)->after('return_pickup_address')->comment('ID provinsi alamat member');
            $table->unsignedInteger('return_pickup_city_id')->default(0)->after('return_pickup_province_id')->comment('ID kota alamat member');
            $table->unsignedInteger('return_pickup_district_id')->default(0)->after('return_pickup_city_id')->comment('ID kecamatan alamat member');
            $table->unsignedInteger('return_pickup_subdistrict_id')->default(0)->after('return_pickup_district_id')->comment('ID kelurahan alamat member');
            $table->dateTime('return_approved_datetime')->nullable()->after('return_approved_by')->comment('Tanggal persetujuan admin');
            $table->unsignedInteger('return_received_by')->default(0)->after('return_approved_datetime')->comment('ID admin penerima barang retur');
            $table->dateTime('return_received_datetime')->nullable()->after('return_received_by')->comment('Tanggal barang diterima Company');
            $table->enum('return_replacement_shipping_method', ['courier_express'])->nullable()->after('return_received_datetime')->comment('Metode pengiriman pengganti');
            $table->unsignedInteger('return_replacement_shipping_cost')->default(0)->after('return_replacement_shipping_method')->comment('Ongkir barang pengganti');
            $table->unsignedInteger('return_replacement_shipped_by')->default(0)->after('return_replacement_shipping_cost')->comment('ID admin pengirim pengganti');
            $table->dateTime('return_replacement_shipped_datetime')->nullable()->after('return_replacement_shipped_by')->comment('Tanggal pengiriman barang pengganti');
            $table->dateTime('return_completed_datetime')->nullable()->after('return_replacement_shipped_datetime')->comment('Tanggal retur selesai');
        });

        $this->backfillAddressSnapshots();

        Schema::table('return', function (Blueprint $table) {
            $table->dropIndex('return_return_trx_id_index');
            $table->dropUnique('return_replacement_trx_unique');
            $table->dropColumn(['return_trx_id', 'return_replacement_trx_id']);
            $table->enum('return_status', [
                'submitted',
                'approved',
                'waiting_member_shipment',
                'return_in_transit',
                'received_by_company',
                'replacement_in_transit',
                'completed',
                'rejected',
            ])->default('submitted')->comment('Status workflow retur')->change();
        });

        Schema::table('return_status_log', function (Blueprint $table) {
            $table->enum('return_status_log_status', [
                'submitted',
                'approved',
                'waiting_member_shipment',
                'return_in_transit',
                'received_by_company',
                'replacement_in_transit',
                'completed',
                'rejected',
            ])->comment('Status workflow retur')->change();
        });

        Schema::table('shipping_courier_express', function (Blueprint $table) {
            $table->enum('shipping_courier_express_leg', ['return_to_company', 'replacement_to_member'])
                ->nullable()
                ->after('shipping_courier_express_ref_id')
                ->comment('Tahap pengiriman khusus retur');
            $table->index(
                ['shipping_courier_express_ref_type', 'shipping_courier_express_ref_id', 'shipping_courier_express_leg'],
                'idx_express_return_leg',
            );
        });
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'return')
            ->update(['shipping_courier_express_leg' => 'return_to_company']);

        Schema::table('trx', function (Blueprint $table) {
            $table->enum('trx_type', ['registration', 'activation', 'stock', 'retail', 'upgrade'])
                ->comment('Jenis transaksi')
                ->change();
            $table->enum('trx_status', [
                'rejected',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'shipped',
                'ready_to_pickup',
                'received',
                'completed',
            ])->default('waiting_payment')
                ->comment('Status transaksi tanpa persetujuan pesanan dan tanpa tahap pengemasan')
                ->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Workflow retur tanpa transaksi tidak dapat dikembalikan otomatis.');
    }

    private function backfillAddressSnapshots(): void
    {
        DB::table('return')->orderBy('return_id')->each(function (object $return): void {
            $address = DB::table('member_address')
                ->where('member_address_member_id', $return->return_member_id)
                ->orderByDesc('member_address_is_default')
                ->orderBy('member_address_id')
                ->first();
            $member = DB::table('member')->where('member_id', $return->return_member_id)->first();

            DB::table('return')->where('return_id', $return->return_id)->update([
                'return_member_address_id' => (int) ($address?->member_address_id ?? 0),
                'return_pickup_name' => (string) ($address?->member_address_recipient ?? $member?->member_name ?? ''),
                'return_pickup_phone' => (string) ($address?->member_address_phone ?? $member?->member_mobilephone ?? ''),
                'return_pickup_address' => (string) ($address?->member_address_full ?? $member?->member_address ?? ''),
                'return_pickup_province_id' => (int) ($address?->member_address_province_id ?? $member?->member_province_id ?? 0),
                'return_pickup_city_id' => (int) ($address?->member_address_city_id ?? $member?->member_city_id ?? 0),
                'return_pickup_district_id' => (int) ($address?->member_address_district_id ?? $member?->member_district_id ?? 0),
                'return_pickup_subdistrict_id' => (int) ($address?->member_address_subdistrict_id ?? $member?->member_subdistrict_id ?? 0),
            ]);
        });
    }
};
