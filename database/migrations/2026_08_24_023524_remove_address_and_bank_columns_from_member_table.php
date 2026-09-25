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
        Schema::table('member', function (Blueprint $table) {
            $table->dropColumn([
                'member_address',
                'member_subdistrict_id',
                'member_district_id',
                'member_city_id',
                'member_province_id',
                'member_country_id',
                'member_bank_id',
                'member_bank_name',
                'member_bank_account_name',
                'member_bank_account_no',
                'member_bank_city',
                'member_bank_branch',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->string('member_address', 255)->nullable()->comment('Alamat Member');
            $table->unsignedInteger('member_subdistrict_id')->default(0)->comment('Kelurahan Member');
            $table->unsignedInteger('member_district_id')->default(0)->comment('Kecamatan Member');
            $table->unsignedInteger('member_city_id')->default(0)->comment('Kota/Kabupaten Member');
            $table->unsignedInteger('member_province_id')->default(0)->comment('Provinsi Member');
            $table->unsignedInteger('member_country_id')->default(0)->comment('Negara Member');
            $table->unsignedInteger('member_bank_id')->default(0)->comment('ID Ref Bank');
            $table->string('member_bank_name', 100)->nullable()->comment('Nama Bank Member');
            $table->string('member_bank_account_name', 50)->nullable()->comment('Nama Rekening Member');
            $table->string('member_bank_account_no', 50)->nullable()->comment('Nomor Rekening Member');
            $table->string('member_bank_city', 50)->nullable()->comment('Kota Bank Member');
            $table->string('member_bank_branch', 50)->nullable()->comment('Cabang Bank Member');
        });

        DB::table('member')->orderBy('member_id')->chunkById(100, function ($members): void {
            foreach ($members as $member) {
                $address = DB::table('member_address')
                    ->where('member_address_member_id', $member->member_id)
                    ->orderByDesc('member_address_is_default')
                    ->orderBy('member_address_id')
                    ->first();
                $account = DB::table('member_bank_account')
                    ->leftJoin('ref_bank', 'ref_bank.bank_id', '=', 'member_bank_account.member_bank_account_bank_id')
                    ->where('member_bank_account_member_id', $member->member_id)
                    ->orderByDesc('member_bank_account_is_default')
                    ->orderByDesc('member_bank_account_is_active')
                    ->orderBy('member_bank_account_id')
                    ->first();

                DB::table('member')->where('member_id', $member->member_id)->update([
                    'member_address' => $address?->member_address_full,
                    'member_subdistrict_id' => $address?->member_address_subdistrict_id ?? 0,
                    'member_district_id' => $address?->member_address_district_id ?? 0,
                    'member_city_id' => $address?->member_address_city_id ?? 0,
                    'member_province_id' => $address?->member_address_province_id ?? 0,
                    'member_country_id' => $address?->member_address_country_id ?? 0,
                    'member_bank_id' => $account?->member_bank_account_bank_id ?? 0,
                    'member_bank_name' => $account?->bank_name,
                    'member_bank_account_name' => $account?->member_bank_account_name,
                    'member_bank_account_no' => $account?->member_bank_account_number,
                    'member_bank_city' => $account?->member_bank_account_city,
                    'member_bank_branch' => $account?->member_bank_account_branch,
                ]);
            }
        }, 'member_id');
    }
};
