<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('member')
            ->orderBy('member_id')
            ->chunkById(100, function ($members): void {
                foreach ($members as $member) {
                    $this->backfillAddress($member);
                    $this->backfillBankAccount($member);
                }
            }, 'member_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data hasil backfill dipertahankan agar rollback tidak menghapus data member.
    }

    private function backfillAddress(object $member): void
    {
        $addresses = DB::table('member_address')
            ->where('member_address_member_id', $member->member_id)
            ->orderBy('member_address_id')
            ->get();

        $defaultAddress = $addresses->firstWhere('member_address_is_default', 1);
        $legacyAddress = $addresses->first(function (object $address) use ($member): bool {
            return filled($member->member_address)
                && $address->member_address_full === $member->member_address;
        });

        if (! $legacyAddress && filled($member->member_address)) {
            $addressId = DB::table('member_address')->insertGetId([
                'member_address_member_id' => $member->member_id,
                'member_address_label' => 'Alamat Utama',
                'member_address_recipient' => $member->member_name,
                'member_address_phone' => $member->member_mobilephone,
                'member_address_full' => $member->member_address,
                'member_address_subdistrict_id' => $member->member_subdistrict_id,
                'member_address_district_id' => $member->member_district_id,
                'member_address_city_id' => $member->member_city_id,
                'member_address_province_id' => $member->member_province_id,
                'member_address_country_id' => $member->member_country_id,
                'member_address_is_default' => $defaultAddress ? 0 : 1,
            ]);

            if (! $defaultAddress) {
                $defaultAddress = (object) ['member_address_id' => $addressId];
            }
        }

        if (! $defaultAddress) {
            $firstAddress = DB::table('member_address')
                ->where('member_address_member_id', $member->member_id)
                ->orderBy('member_address_id')
                ->first();

            if ($firstAddress) {
                DB::table('member_address')
                    ->where('member_address_id', $firstAddress->member_address_id)
                    ->update(['member_address_is_default' => 1]);
            }
        }
    }

    private function backfillBankAccount(object $member): void
    {
        $accounts = DB::table('member_bank_account')
            ->where('member_bank_account_member_id', $member->member_id)
            ->orderBy('member_bank_account_id')
            ->get();

        $legacyAccount = $accounts->first(function (object $account) use ($member): bool {
            return (int) $account->member_bank_account_bank_id === (int) $member->member_bank_id
                && filled($member->member_bank_account_no)
                && $account->member_bank_account_number === $member->member_bank_account_no;
        });

        if (! $legacyAccount && (int) $member->member_bank_id > 0 && filled($member->member_bank_account_no)) {
            $accountId = DB::table('member_bank_account')->insertGetId([
                'member_bank_account_member_id' => $member->member_id,
                'member_bank_account_bank_id' => $member->member_bank_id,
                'member_bank_account_name' => $member->member_bank_account_name ?: $member->member_name,
                'member_bank_account_number' => $member->member_bank_account_no,
                'member_bank_account_city' => $member->member_bank_city,
                'member_bank_account_branch' => $member->member_bank_branch,
                'member_bank_account_is_active' => 1,
                'member_bank_account_is_default' => 1,
            ]);
            $legacyAccount = (object) ['member_bank_account_id' => $accountId];
        }

        $defaultId = $legacyAccount?->member_bank_account_id
            ?? $accounts->firstWhere('member_bank_account_is_active', 1)?->member_bank_account_id
            ?? $accounts->first()?->member_bank_account_id;

        if ($defaultId) {
            DB::table('member_bank_account')
                ->where('member_bank_account_member_id', $member->member_id)
                ->update(['member_bank_account_is_default' => 0]);
            DB::table('member_bank_account')
                ->where('member_bank_account_id', $defaultId)
                ->update([
                    'member_bank_account_city' => $member->member_bank_city,
                    'member_bank_account_branch' => $member->member_bank_branch,
                    'member_bank_account_is_active' => 1,
                    'member_bank_account_is_default' => 1,
                ]);
        }
    }
};
