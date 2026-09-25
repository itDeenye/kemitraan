<?php

namespace App\Services\Account;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberAccountService
{
    public function updateProfile(Member $member, array $data): void
    {
        $member->update([
            'member_name' => $data['name'],
            'member_email' => $data['email'] ?? '',
            'member_mobilephone' => $data['phone'] ?? '',
            'member_gender' => $data['gender'] ?? 'Laki-laki',
            'member_birth_date' => $data['birth_date'] ?? null,
            'member_identity_type' => $data['identity_type'] ?? $member->member_identity_type,
            'member_identity_no' => $data['identity_no'] ?? '',
            'member_nib' => $data['nib'] ?? null,
            'member_instagram' => $data['instagram'] ?? null,
            'member_facebook' => $data['facebook'] ?? null,
            'member_tiktok' => $data['tiktok'] ?? null,
        ]);
    }

    public function updateProfilePhoto(Member $member, ?string $imageUrl): void
    {
        $filename = $imageUrl === null
            ? null
            : basename((string) parse_url($imageUrl, PHP_URL_PATH));

        $member->update([
            'member_image' => $imageUrl,
            'member_image_filename' => $filename ?: null,
        ]);
    }

    public function updatePassword(MemberAccount $account, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $account->member_account_password)) {
            throw new ProcessException('Kata sandi saat ini tidak sesuai.');
        }

        $account->update([
            'member_account_password' => Hash::make($newPassword),
        ]);
    }

    /** @return Collection<int, MemberAddress> */
    public function getAddresses(int $memberId): Collection
    {
        return MemberAddress::query()
            ->with(['province', 'city', 'district', 'subdistrict', 'country'])
            ->where('member_address_member_id', $memberId)
            ->orderByDesc('member_address_is_default')
            ->orderBy('member_address_id')
            ->get();
    }

    public function createAddress(int $memberId, array $data): MemberAddress
    {
        return DB::transaction(function () use ($memberId, $data) {
            $isDefault = $data['is_default'] ?? 0;
            if ($isDefault) {
                MemberAddress::query()
                    ->where('member_address_member_id', $memberId)
                    ->update(['member_address_is_default' => 0]);
            } else {
                $hasAddress = MemberAddress::query()
                    ->where('member_address_member_id', $memberId)
                    ->exists();
                if (! $hasAddress) {
                    $isDefault = 1;
                }
            }

            return MemberAddress::query()->create([
                'member_address_member_id' => $memberId,
                'member_address_label' => $data['label'] ?? '',
                'member_address_recipient' => $data['recipient'],
                'member_address_phone' => $data['phone'],
                'member_address_full' => $data['full_address'],
                'member_address_province_id' => $data['province_id'] ?? 0,
                'member_address_city_id' => $data['city_id'] ?? 0,
                'member_address_district_id' => $data['district_id'] ?? 0,
                'member_address_subdistrict_id' => $data['subdistrict_id'] ?? 0,
                'member_address_country_id' => $data['country_id'] ?? 1,
                'member_address_is_default' => $isDefault ? 1 : 0,
            ])->load(['province', 'city', 'district', 'subdistrict', 'country']);
        });
    }

    public function updateAddress(MemberAddress $address, array $data): void
    {
        DB::transaction(function () use ($address, $data): void {
            $memberId = $address->member_address_member_id;

            $address->update([
                'member_address_label' => $data['label'] ?? $address->member_address_label,
                'member_address_recipient' => $data['recipient'] ?? $address->member_address_recipient,
                'member_address_phone' => $data['phone'] ?? $address->member_address_phone,
                'member_address_full' => $data['full_address'] ?? $address->member_address_full,
                'member_address_province_id' => $data['province_id'] ?? $address->member_address_province_id,
                'member_address_city_id' => $data['city_id'] ?? $address->member_address_city_id,
                'member_address_district_id' => $data['district_id'] ?? $address->member_address_district_id,
                'member_address_subdistrict_id' => $data['subdistrict_id'] ?? $address->member_address_subdistrict_id,
                'member_address_country_id' => $data['country_id'] ?? $address->member_address_country_id,
            ]);

            $hasDefaultAddress = MemberAddress::query()
                ->where('member_address_member_id', $memberId)
                ->where('member_address_is_default', 1)
                ->exists();

            if (! $hasDefaultAddress) {
                $address->update(['member_address_is_default' => 1]);
            }
        });
    }

    public function deleteAddress(MemberAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $isDefault = $address->member_address_is_default == 1;
            $memberId = $address->member_address_member_id;

            $address->delete();

            if ($isDefault) {
                $latestAddress = MemberAddress::query()
                    ->where('member_address_member_id', $memberId)
                    ->first();
                if ($latestAddress) {
                    $latestAddress->update(['member_address_is_default' => 1]);
                }
            }
        });
    }

    public function setAddressAsDefault(MemberAddress $address): void
    {
        DB::transaction(function () use ($address) {
            MemberAddress::query()
                ->where('member_address_member_id', $address->member_address_member_id)
                ->update(['member_address_is_default' => 0]);

            $address->update(['member_address_is_default' => 1]);
        });
    }

    /** @return Collection<int, MemberBankAccount> */
    public function getBanks(int $memberId): Collection
    {
        return MemberBankAccount::query()
            ->with('bank')
            ->where('member_bank_account_member_id', $memberId)
            ->orderByDesc('member_bank_account_is_default')
            ->orderByDesc('member_bank_account_is_active')
            ->orderBy('member_bank_account_id')
            ->get();
    }

    public function createBank(int $memberId, array $data): MemberBankAccount
    {
        return DB::transaction(function () use ($memberId, $data): MemberBankAccount {
            $accounts = MemberBankAccount::query()
                ->where('member_bank_account_member_id', $memberId)
                ->lockForUpdate()
                ->get();
            $isActive = (bool) ($data['is_active'] ?? true);
            $hasDefault = $accounts->contains(fn (MemberBankAccount $account): bool => $account->member_bank_account_is_active
                && $account->member_bank_account_is_default);
            $isDefault = $isActive && ((bool) ($data['is_default'] ?? false) || ! $hasDefault);

            if ($isDefault) {
                $this->clearDefaultBanks($memberId);
            }

            return MemberBankAccount::query()->create([
                'member_bank_account_member_id' => $memberId,
                'member_bank_account_bank_id' => $data['bank_id'],
                'member_bank_account_name' => $data['account_name'],
                'member_bank_account_number' => $data['account_number'],
                'member_bank_account_city' => $data['city'] ?? null,
                'member_bank_account_branch' => $data['branch'] ?? null,
                'member_bank_account_is_active' => $isActive,
                'member_bank_account_is_default' => $isDefault,
            ])->load('bank');
        });
    }

    public function updateBank(MemberBankAccount $bank, array $data): void
    {
        DB::transaction(function () use ($bank, $data): void {
            $bank = MemberBankAccount::query()->lockForUpdate()->findOrFail($bank->getKey());
            $wasDefault = (bool) $bank->member_bank_account_is_default;
            $isActive = (bool) ($data['is_active'] ?? $bank->member_bank_account_is_active);
            $isDefault = (bool) ($data['is_default'] ?? $bank->member_bank_account_is_default);

            if ($isDefault && ! $isActive) {
                throw new ProcessException('Rekening default harus berstatus aktif.');
            }

            if ($isDefault) {
                $this->clearDefaultBanks((int) $bank->member_bank_account_member_id);
            }

            $bank->update([
                'member_bank_account_bank_id' => $data['bank_id'] ?? $bank->member_bank_account_bank_id,
                'member_bank_account_name' => $data['account_name'] ?? $bank->member_bank_account_name,
                'member_bank_account_number' => $data['account_number'] ?? $bank->member_bank_account_number,
                'member_bank_account_city' => $data['city'] ?? $bank->member_bank_account_city,
                'member_bank_account_branch' => $data['branch'] ?? $bank->member_bank_account_branch,
                'member_bank_account_is_active' => $isActive,
                'member_bank_account_is_default' => $isDefault,
            ]);

            if (! $isDefault && $wasDefault) {
                $this->promoteReplacementBank((int) $bank->member_bank_account_member_id);
            }
        });
    }

    public function deleteBank(MemberBankAccount $bank): void
    {
        DB::transaction(function () use ($bank): void {
            $bank = MemberBankAccount::query()->lockForUpdate()->findOrFail($bank->getKey());
            $wasDefault = (bool) $bank->member_bank_account_is_default;
            $memberId = (int) $bank->member_bank_account_member_id;
            $bankId = (int) $bank->getKey();

            $bank->delete();

            if ($wasDefault) {
                $this->promoteReplacementBank($memberId, $bankId);
            }
        });
    }

    public function setBankAsDefault(MemberBankAccount $bank): void
    {
        if (! $bank->member_bank_account_is_active) {
            throw new ProcessException('Rekening tidak aktif tidak dapat dijadikan default.');
        }

        DB::transaction(function () use ($bank): void {
            MemberBankAccount::query()
                ->where('member_bank_account_member_id', $bank->member_bank_account_member_id)
                ->lockForUpdate()
                ->get();

            $this->clearDefaultBanks((int) $bank->member_bank_account_member_id);
            $bank->update(['member_bank_account_is_default' => 1]);
        });
    }

    private function clearDefaultBanks(int $memberId): void
    {
        MemberBankAccount::query()
            ->where('member_bank_account_member_id', $memberId)
            ->update(['member_bank_account_is_default' => 0]);
    }

    private function promoteReplacementBank(int $memberId, ?int $excludedBankId = null): void
    {
        $query = MemberBankAccount::query()
            ->where('member_bank_account_member_id', $memberId)
            ->where('member_bank_account_is_active', 1);

        if ($excludedBankId !== null) {
            $query->where('member_bank_account_id', '!=', $excludedBankId);
        }

        $query
            ->orderBy('member_bank_account_id')
            ->first()
            ?->update(['member_bank_account_is_default' => 1]);
    }
}
