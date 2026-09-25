<?php

namespace App\Http\Resources\Api\V1\Partnership;

use App\Http\Resources\ApiResource;
use App\Models\MemberRegistration;
use Illuminate\Http\Request;

class MemberRegistrationResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $registration = $this->resource instanceof MemberRegistration ? $this->resource : null;
        $status = (string) $this->value('status', 'member_registration_status');
        $processedById = (int) $this->value(
            'processed_by_id',
            'member_registration_status_administrator_id'
        );

        return [
            'id' => $this->value('id', 'member_registration_id'),
            'level' => [
                'id' => $registration?->level?->member_level_id ?? $this->resource->level_id,
                'code' => $registration?->level?->member_level_code ?? $this->resource->level_code,
                'name' => $registration?->level?->member_level_name ?? $this->resource->level_name,
            ],
            'sponsor' => $this->sponsor($registration),
            'applicant' => [
                'name' => $this->value('name', 'member_registration_name'),
                'email' => $this->value('email', 'member_registration_email'),
                'mobile_phone' => $this->value('mobile_phone', 'member_registration_mobilephone'),
                'username' => $this->value('username', 'member_registration_username'),
                ...($registration === null ? [] : [
                    'gender' => $registration->member_registration_gender,
                    'birth_date' => $registration->member_registration_birth_date?->toDateString(),
                ]),
            ],
            'status' => [
                'code' => $status,
                'label' => match ($status) {
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => 'Menunggu',
                },
            ],
            'processed_by' => $processedById === 0 ? null : [
                'id' => $processedById,
                'name' => $registration?->statusAdministrator?->administrator_name
                    ?? $this->resource->processed_by_name,
            ],
            'submitted_at' => $registration
                ? $registration->member_registration_datetime?->toAtomString()
                : $this->resource->submitted_at,
            'processed_at' => $registration
                ? $registration->member_registration_status_datetime?->toAtomString()
                : $this->resource->processed_at,
            ...($registration === null ? [] : $this->detail($registration)),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof MemberRegistration
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    /** @return array<string, mixed>|null */
    private function sponsor(?MemberRegistration $registration): ?array
    {
        $sponsorId = (int) $this->value('sponsor_id', 'member_registration_upline_member_id');

        if ($sponsorId === 0) {
            return null;
        }

        return [
            'id' => $sponsorId,
            'code' => $registration?->parent?->member_code ?? $this->resource->sponsor_code,
            'name' => $registration?->parent?->member_name ?? $this->resource->sponsor_name,
            'level_code' => $registration?->parent?->level?->member_level_code,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(MemberRegistration $registration): array
    {
        return [
            'identity' => [
                'type' => $registration->member_registration_identity_type,
                'number' => $registration->member_registration_identity_no,
                'image_url' => $registration->member_registration_identity_image,
                'nib' => $registration->member_registration_nib,
            ],
            'address' => [
                'address' => $registration->member_registration_address,
                'province_id' => $registration->member_registration_province_id,
                'province_name' => $registration->province?->province_name,
                'city_id' => $registration->member_registration_city_id,
                'city_name' => $registration->city?->city_name,
                'district_id' => $registration->member_registration_district_id,
                'district_name' => $registration->district?->district_name,
                'subdistrict_id' => $registration->member_registration_subdistrict_id,
                'subdistrict_name' => $registration->subdistrict?->subdistrict_name,
                'postal_code' => $registration->subdistrict?->subdistrict_zip_code,
                'country_id' => $registration->member_registration_country_id,
                'country_name' => $registration->country?->country_name,
            ],
            'bank' => (int) $registration->member_registration_bank_id === 0 ? null : [
                'id' => $registration->member_registration_bank_id,
                'code' => $registration->bank?->bank_code,
                'name' => $registration->bank?->bank_name,
                'account_name' => $registration->member_registration_bank_account_name,
                'account_number' => $registration->member_registration_bank_account_no,
                'city' => $registration->member_registration_bank_city,
                'branch' => $registration->member_registration_bank_branch,
            ],
            'note' => $registration->member_registration_note,
        ];
    }
}
