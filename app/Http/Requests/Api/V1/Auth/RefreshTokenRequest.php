<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'refresh_token' => 'data sesi',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'refresh_token' => trim((string) $this->input('refresh_token')),
        ]);
    }
}
