<?php

namespace App\Http\Requests\Api\V1\Member\Account;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'identity_type' => ['nullable', Rule::in(['KTP', 'SIM', 'PASPOR'])],
            'identity_no' => ['nullable', 'string', 'max:20'],
            'nib' => ['nullable', 'string', 'max:20'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
        ];
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
            'nib' => 'NIB',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }
}
