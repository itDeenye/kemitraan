<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListMemberDeactivationOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'search' => 'pencarian sponsor pengganti',
            'limit' => 'jumlah opsi sponsor',
        ];
    }
}
