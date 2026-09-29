<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('return', function (Blueprint $table) {
            $table->enum('return_status', [
                'submitted',
                'approved',
                'waiting_member_shipment',
                'return_in_transit',
                'return_shipping_failed',
                'received_by_company',
                'replacement_in_transit',
                'replacement_shipping_failed',
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
                'return_shipping_failed',
                'received_by_company',
                'replacement_in_transit',
                'replacement_shipping_failed',
                'completed',
                'rejected',
            ])->comment('Status workflow retur')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('return')
            ->where('return_status', 'return_shipping_failed')
            ->update(['return_status' => 'submitted']);
        DB::table('return')
            ->where('return_status', 'replacement_shipping_failed')
            ->update(['return_status' => 'received_by_company']);
        DB::table('return_status_log')
            ->where('return_status_log_status', 'return_shipping_failed')
            ->update(['return_status_log_status' => 'submitted']);
        DB::table('return_status_log')
            ->where('return_status_log_status', 'replacement_shipping_failed')
            ->update(['return_status_log_status' => 'received_by_company']);

        Schema::table('return', function (Blueprint $table) {
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
    }
};
