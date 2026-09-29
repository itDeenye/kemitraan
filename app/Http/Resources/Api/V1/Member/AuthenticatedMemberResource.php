<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Models\MemberAccount;
use App\Services\Partnership\MemberPasswordService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AuthenticatedMemberResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MemberAccount $account */
        $account = $this->resource;
        $member = $account->member;

        return [
            'account_id' => $account->member_account_id,
            'username' => $account->member_account_username,
            'last_login_at' => $account->member_account_last_login_datetime?->toAtomString(),
            'has_bank_account' => $member?->activeBankAccounts->isNotEmpty() ?? false,
            'is_default_password' => app(MemberPasswordService::class)->isBirthDatePassword($account),
            'role' => [
                'id' => $account->group?->member_group_id,
                'name' => $account->group?->member_group_name,
            ],
            'member' => [
                'id' => $member?->member_id,
                'code' => $member?->member_code,
                'name' => $member?->member_name,
                'email' => $member?->member_email,
                'mobile_phone' => $member?->member_mobilephone,
                'level' => $member?->level ? [
                    'id' => $member->level->member_level_id,
                    'code' => $member->level->member_level_code,
                    'name' => $member->level->member_level_name,
                    'min_order' => (int) $member->level->member_level_min_order,
                ] : null,
                'image' => MediaUrl::publicUrl($member?->member_image),
                'is_stockist' => $member?->stockist !== null,
            ],
        ];
    }
}
