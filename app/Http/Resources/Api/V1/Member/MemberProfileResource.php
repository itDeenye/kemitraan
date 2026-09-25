<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberProfileResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var MemberAccount $account */
        $account = $this->resource;
        $member = $account->member;

        return [
            'account_id' => (int) $account->member_account_id,
            'username' => $account->member_account_username,
            'last_login_at' => $account->member_account_last_login_datetime?->toAtomString(),
            'role' => [
                'id' => $account->group?->member_group_id,
                'name' => $account->group?->member_group_name,
            ],
            'member' => $member ? $this->memberData($member) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function memberData(Member $member): array
    {
        return [
            'id' => (int) $member->member_id,
            'code' => $member->member_code,
            'name' => $member->member_name,
            'email' => $member->member_email,
            'mobile_phone' => $member->member_mobilephone,
            'gender' => $member->member_gender,
            'birth_date' => $member->member_birth_date?->format('Y-m-d'),
            'level' => $member->level ? [
                'id' => (int) $member->level->member_level_id,
                'code' => $member->level->member_level_code,
                'name' => $member->level->member_level_name,
                'min_order' => (int) $member->level->member_level_min_order,
            ] : null,
            'parent' => $member->parent ? [
                'id' => (int) $member->parent->member_id,
                'code' => $member->parent->member_code,
                'name' => $member->parent->member_name,
            ] : null,
            'identity' => [
                'type' => $member->member_identity_type,
                'number' => $member->member_identity_no,
                'image' => $member->member_identity_image,
            ],
            'nib' => $member->member_nib,
            'image' => MediaUrl::publicUrl($member->member_image),
            'social_media' => [
                'instagram' => $member->member_instagram,
                'facebook' => $member->member_facebook,
                'tiktok' => $member->member_tiktok,
            ],
            'joined_at' => $member->member_join_datetime?->toAtomString(),
            'status' => [
                'code' => (int) $member->member_status,
                'label' => $this->memberStatusLabel((int) $member->member_status),
            ],
            'is_stockist' => $member->stockist !== null,
            'stockist' => $member->stockist ? [
                'id' => (int) $member->stockist->stockist_id,
                'name' => $member->stockist->stockist_name,
                'is_active' => (bool) $member->stockist->stockist_is_active,
            ] : null,
            'default_address' => $this->addressData($member->defaultAddress),
            'default_bank_account' => $this->bankAccountData($member->defaultBankAccount),
        ];
    }

    /** @return array<string, mixed>|null */
    private function addressData(?MemberAddress $address): ?array
    {
        if (! $address) {
            return null;
        }

        return (new MemberAddressResource($address))->resolve(request());
    }

    /** @return array<string, mixed>|null */
    private function bankAccountData(?MemberBankAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return (new MemberBankAccountResource($account))->resolve(request());
    }

    private function memberStatusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Aktif',
            2 => 'Ditangguhkan',
            3 => 'Dihapus',
            default => 'Tidak Aktif',
        };
    }
}
