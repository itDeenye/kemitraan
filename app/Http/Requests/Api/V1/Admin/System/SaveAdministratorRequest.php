<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $administrator = $this->route('administrator');

        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists((new SiteAdministratorGroup)->getTable(), 'administrator_group_id')
                    ->where('administrator_group_is_active', 1),
            ],
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique((new SiteAdministrator)->getTable(), 'administrator_username')
                    ->ignore($administrator),
            ],
            'password' => $this->isMethod('post')
                ? ['required', 'string', 'min:8', 'max:255', 'confirmed']
                : ['prohibited'],
            'name' => ['required', 'string', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:50',
                Rule::unique((new SiteAdministrator)->getTable(), 'administrator_email')
                    ->ignore($administrator),
            ],
            'image_url' => ['nullable', 'string', 'max:255'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_phone')) {
            $this->merge(['mobile_phone' => PhoneNumber::normalize($this->input('mobile_phone'))]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'role_id' => 'grup akses administrator',
            'username' => 'username',
            'password' => 'kata sandi',
            'password_confirmation' => 'konfirmasi kata sandi',
            'name' => 'nama administrator',
            'email' => 'email',
            'image_url' => 'tautan foto',
            'mobile_phone' => 'nomor ponsel',
            'is_active' => 'status aktif',
        ];
    }
}
