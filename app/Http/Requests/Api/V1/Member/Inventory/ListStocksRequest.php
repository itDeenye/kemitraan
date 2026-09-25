<?php

namespace App\Http\Requests\Api\V1\Member\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ListStocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.product_id' => ['nullable', 'integer', 'min:1'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
