<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListProductPricesRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->dataTableRules(),
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}
