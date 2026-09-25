<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\SiteAdministrator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdateAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'different:current_password', 'confirmed'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $administrator = $this->user();
                $currentPassword = $this->input('current_password');

                if (
                    $administrator instanceof SiteAdministrator
                    && is_string($currentPassword)
                    && ! Hash::check($currentPassword, $administrator->administrator_password)
                ) {
                    $validator->errors()->add('current_password', 'Kata sandi lama tidak sesuai.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'current_password' => 'kata sandi lama',
            'password' => 'kata sandi baru',
            'password_confirmation' => 'konfirmasi kata sandi baru',
        ];
    }
}
