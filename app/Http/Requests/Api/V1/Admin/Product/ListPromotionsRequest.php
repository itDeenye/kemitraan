<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPromotionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array', 'max:50'],
            'filter.*' => ['nullable'],
            'filter.type' => ['nullable', Rule::in(['discount', 'bundling'])],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}
