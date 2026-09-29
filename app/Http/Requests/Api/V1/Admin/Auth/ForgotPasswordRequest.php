<?php

namespace App\Http\Requests\Api\V1\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identifier' => 'email atau username administrator',
        ];
    }

    protected function prepareForValidation(): void
    {
        $identifier = $this->input('identifier', $this->input('email'));

        $this->merge([
            'identifier' => trim((string) $identifier),
        ]);
    }
}
