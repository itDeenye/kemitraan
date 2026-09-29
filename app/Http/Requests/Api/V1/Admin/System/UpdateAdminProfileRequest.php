<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\SiteAdministrator;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:50',
                Rule::unique((new SiteAdministrator)->getTable(), 'administrator_email')
                    ->ignore($this->user()),
            ],
            'image_url' => ['nullable', 'string', 'max:255'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['mobile_phone' => 'nomor ponsel'];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_phone')) {
            $this->merge(['mobile_phone' => PhoneNumber::normalize($this->input('mobile_phone'))]);
        }
    }
}
