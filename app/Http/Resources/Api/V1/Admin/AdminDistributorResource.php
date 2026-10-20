<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\Member\MemberAddressResource;
use App\Http\Resources\Api\V1\Member\MemberBankAccountResource;
use App\Http\Resources\ApiResource;
use App\Models\Member;
use App\Services\Reference\CityNameFormatter;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminDistributorResource extends ApiResource
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
        $cityName = $member
            ? CityNameFormatter::displayName($member->city?->city_name, $member->city?->city_type)
            : CityNameFormatter::displayName($this->resource->city_name ?? null, $this->resource->city_type ?? null);

        return [
            'id' => $this->value('id', 'member_id'),
            'code' => $this->value('code', 'member_code'),
            'name' => $this->value('name', 'member_name'),
            'email' => $this->value('email', 'member_email'),
            'mobile_phone' => $this->value('mobile_phone', 'member_mobilephone'),
            'image' => MediaUrl::publicUrl($this->value('image', 'member_image')),
            'nib' => $this->value('nib', 'member_nib'),
            'npwp' => $this->value('npwp', 'member_npwp'),
            'addresses' => $this->when($member !== null, fn () => MemberAddressResource::collection($member->addresses)),
            'bank_accounts' => $this->when($member !== null, fn () => MemberBankAccountResource::collection($member->bankAccounts)),
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
            'region' => $member
                ? ($cityName ? $cityName.', '.$member->province?->province_name : null)
                : ($cityName ? $cityName.', '.$this->resource->province_name : null),
            'address' => $this->when($member !== null, fn (): array => [
                'address' => $member->member_address,
                'subdistrict_id' => $member->member_subdistrict_id,
                'district_id' => $member->member_district_id,
                'city_id' => $member->member_city_id,
                'province_id' => $member->member_province_id,
                'country_id' => $member->member_country_id,
            ]),
            'bank' => $this->when($member !== null, fn (): array => [
                'bank_id' => $member->member_bank_id,
                'bank_name' => $member->member_bank_name,
                'account_name' => $member->member_bank_account_name,
                'account_number' => $member->member_bank_account_no,
                'city' => $member->member_bank_city,
                'branch' => $member->member_bank_branch,
            ]),
            'social_media' => $this->when($member !== null, fn (): array => [
                'instagram' => $member->member_instagram,
                'facebook' => $member->member_facebook,
                'tiktok' => $member->member_tiktok,
            ]),
            'identity' => [
                'type' => $this->value('identity_type', 'member_identity_type'),
                'no' => $this->value('identity_no', 'member_identity_no'),
                'image' => MediaUrl::temporaryPrivateUrl($this->value('identity_image', 'member_identity_image'), 'admin'),
            ],
            'birth_date' => $this->when($member !== null, fn (): ?string => $member->member_birth_date?->toDateString()),
            'gender' => $this->when($member !== null, fn (): ?string => $member->member_gender),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof Member
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
