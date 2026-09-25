<?php

namespace App\Http\Requests\Api\V1\Member\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image_url' => ['present', 'nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'image_url' => 'foto profil',
        ];
    }
}
