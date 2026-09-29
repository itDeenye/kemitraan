<?php

namespace App\Http\Requests\Api\V1\Member\Auth;

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
            'identifier' => 'email atau kode member',
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
