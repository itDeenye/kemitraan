<?php

namespace App\Services\Partnership;

use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;

class MemberOnboardingContacts
{
    /** @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    public function addresses(array $data, bool $distributor = false): array
    {
        $address = [
            'label' => $distributor ? 'KTP' : 'Domisili',
            'recipient' => $data['name'],
            'phone' => $data['mobile_phone'],
            'full_address' => $data['address'],
            'province_id' => $data['province_id'],
            'city_id' => $data['city_id'],
            'district_id' => $data['district_id'],
            'subdistrict_id' => $data['subdistrict_id'],
            'country_id' => $data['country_id'] ?? 1,
            'is_default' => ! $distributor,
        ];

        return $distributor ? [$address, [
            ...$address, 'label' => 'Domisili',
            'full_address' => $data['domicile_address'], 'is_default' => true,
            'province_id' => $data['domicile_province_id'] ?? $address['province_id'],
            'city_id' => $data['domicile_city_id'] ?? $address['city_id'],
            'district_id' => $data['domicile_district_id'] ?? $address['district_id'],
            'subdistrict_id' => $data['domicile_subdistrict_id'] ?? $address['subdistrict_id'],
            'country_id' => $data['domicile_country_id'] ?? $address['country_id'],
        ]] : [$address];
    }

    /** @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    public function bankAccounts(array $data): array
    {
        if (isset($data['bank_accounts'])) {
            return $this->withDefault($data['bank_accounts'], true);
        }
        if (empty($data['bank_id'])) {
            return [];
        }

        return [[
            'bank_id' => $data['bank_id'],
            'account_name' => $data['bank_account_name'],
            'account_number' => $data['bank_account_number'],
            'city' => $data['bank_city'] ?? null,
            'branch' => $data['bank_branch'] ?? null,
            'is_active' => true,
            'is_default' => true,
        ]];
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function withDefault(array $items, bool $bank = false): array
    {
        $items = array_values($items);
        $default = null;
        $first = null;
        foreach ($items as $index => &$item) {
            if ($bank) {
                $item['is_active'] = (bool) ($item['is_active'] ?? true);
            }
            $item['is_default'] = (bool) ($item['is_default'] ?? false);
            if (! $bank || $item['is_active']) {
                $first ??= $index;
                if ($item['is_default']) {
                    $default = $index;
                }
            }
        }
        unset($item);
        $default ??= $first;
        foreach ($items as $index => &$item) {
            $item['is_default'] = $index === $default;
        }

        return $items;
    }

    /** @param array<int, array<string, mixed>> $addresses
     * @param  array<int, array<string, mixed>>  $bankAccounts
     */
    public function store(Member $member, array $addresses, array $bankAccounts): void
    {
        foreach ($addresses as $address) {
            MemberAddress::query()->create([
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => $address['label'] ?? '',
                'member_address_recipient' => $address['recipient'],
                'member_address_phone' => $address['phone'],
                'member_address_full' => $address['full_address'],
                'member_address_province_id' => $address['province_id'],
                'member_address_city_id' => $address['city_id'],
                'member_address_district_id' => $address['district_id'],
                'member_address_subdistrict_id' => $address['subdistrict_id'],
                'member_address_country_id' => $address['country_id'] ?? 1,
                'member_address_is_default' => $address['is_default'],
            ]);
        }
        foreach ($bankAccounts as $bank) {
            MemberBankAccount::query()->create([
                'member_bank_account_member_id' => $member->getKey(),
                'member_bank_account_bank_id' => $bank['bank_id'],
                'member_bank_account_name' => $bank['account_name'],
                'member_bank_account_number' => $bank['account_number'],
                'member_bank_account_city' => $bank['city'] ?? null,
                'member_bank_account_branch' => $bank['branch'] ?? null,
                'member_bank_account_is_active' => $bank['is_active'] ?? true,
                'member_bank_account_is_default' => $bank['is_default'],
            ]);
        }
    }
}
