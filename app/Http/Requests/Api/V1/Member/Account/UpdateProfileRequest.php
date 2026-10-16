<?php

namespace App\Http\Requests\Api\V1\Member\Account;

use App\Models\MemberAccount;
use App\Support\MediaUrl;
use App\Support\MemberIdentity;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:16'],
            'gender' => ['nullable', 'string', 'in:Laki-laki,Perempuan'],
            'birth_date' => ['nullable', 'date'],
            'identity_type' => ['nullable', Rule::in(MemberIdentity::TYPES)],
            'identity_no' => ['nullable', 'string', 'max:20'],
            'identity_image_url' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'payment_proof_image_url' => ['prohibited'],
            'nib' => ['nullable', 'string', 'max:20'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $account = $this->user();
            if ($account instanceof MemberAccount && $account->member !== null) {
                $member = $account->member;
                MemberIdentity::validateNumber($validator,
                    $this->input('identity_type') ?? $member->member_identity_type,
                    $this->input('identity_no', $member->member_identity_no), $member);
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor ponsel',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            'identity_type' => 'jenis identitas',
            'identity_no' => 'nomor identitas',
            'identity_image_url' => 'gambar identitas',
            'image_url' => 'foto profil',
            'payment_proof_image_url' => 'bukti transfer pendaftaran',
            'nib' => 'NIB',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identity_type'))) {
            $this->merge(['identity_type' => strtoupper(trim($this->input('identity_type')))]);
        }
        if (is_string($this->input('identity_image_url'))) {
            $this->merge(['identity_image_url' => MediaUrl::canonicalPrivateUrl($this->input('identity_image_url'))]);
        }
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }
}
