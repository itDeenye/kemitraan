<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePromotionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['discount', 'bundling'])],
            'value' => ['required', 'integer', 'min:0'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'terms' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new Product)->getTable(), 'product_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('product_is_active', 1)
                        ->where('product_is_deleted', 0)),
            ],
            'products.*.qty' => ['sometimes', 'integer', 'min:1'],
            'products.*.discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
