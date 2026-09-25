<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\Member\MemberAddressResource;
use App\Http\Resources\Api\V1\Member\MemberBankAccountResource;
use App\Http\Resources\ApiResource;
use App\Models\Member;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminMemberResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = (int) $this->value('status', 'member_status');
        $member = $this->resource instanceof Member ? $this->resource : null;
        $account = $member?->accounts->first();

        return [
            'id' => $this->value('id', 'member_id'),
            'code' => $this->value('code', 'member_code'),
            'name' => $this->value('name', 'member_name'),
            'email' => $this->value('email', 'member_email'),
            'mobile_phone' => $this->value('mobile_phone', 'member_mobilephone'),
            'gender' => $this->when(
                $member !== null,
                fn (): ?string => $member->member_gender,
            ),
            'birth_date' => $this->when(
                $member !== null,
                fn (): ?string => $member->member_birth_date?->toDateString(),
            ),
            'level' => $this->level($member),
            'status' => [
                'code' => $status,
                'label' => match ($status) {
                    1 => 'active',
                    2 => 'suspended',
                    default => 'inactive',
                },
            ],
            'joined_at' => $member
                ? $member->member_join_datetime?->toAtomString()
                : $this->resource->joined_at,
            'image' => MediaUrl::publicUrl($this->value('image', 'member_image')),
            'image_filename' => $this->when(
                $member !== null,
                fn (): ?string => $member->member_image_filename,
            ),
            'username' => $member?->accounts->first()?->member_account_username,
            'account' => $this->when($member !== null, fn (): ?array => $account ? [
                'id' => (int) $account->member_account_id,
                'username' => $account->member_account_username,
                'role' => $account->group ? [
                    'id' => (int) $account->group->member_group_id,
                    'name' => $account->group->member_group_name,
                ] : null,
                'last_login_at' => $account->member_account_last_login_datetime?->toAtomString(),
            ] : null),
            'parent' => $member?->parent ? [
                'id' => $member->parent->member_id,
                'code' => $member->parent->member_code,
                'name' => $member->parent->member_name,
            ] : null,
            ...($member === null ? [] : [
                'identity' => [
                    'type' => $member->member_identity_type,
                    'number' => $member->member_identity_no,
                    'image' => MediaUrl::publicUrl($member->member_identity_image),
                    'image_filename' => $member->member_identity_image_filename,
                ],
                'nib' => $member->member_nib,
                'social_media' => [
                    'instagram' => $member->member_instagram,
                    'facebook' => $member->member_facebook,
                    'tiktok' => $member->member_tiktok,
                ],
                'is_stockist' => $member->stockist !== null,
                'stockist' => $member->stockist ? [
                    'id' => (int) $member->stockist->stockist_id,
                    'name' => $member->stockist->stockist_name,
                    'is_active' => (bool) $member->stockist->stockist_is_active,
                ] : null,
                'network' => $member->getAttribute('network'),
            ]),
            'addresses' => $this->when(
                $member !== null,
                fn () => MemberAddressResource::collection($member->addresses),
            ),
            'bank_accounts' => $this->when(
                $member !== null,
                fn () => MemberBankAccountResource::collection($member->bankAccounts),
            ),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof Member
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    /** @return array<string, mixed>|null */
    private function level(?Member $member): ?array
    {
        if ($member) {
            return $member->level ? [
                'id' => $member->level->member_level_id,
                'code' => $member->level->member_level_code,
                'name' => $member->level->member_level_name,
                'min_order' => (int) $member->level->member_level_min_order,
            ] : null;
        }

        return $this->resource->level_id ? [
            'id' => $this->resource->level_id,
            'code' => $this->resource->level_code,
            'name' => $this->resource->level,
            'min_order' => (int) $this->resource->level_min_order,
        ] : null;
    }
}
