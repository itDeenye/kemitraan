<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
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
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array', 'max:50'],
            'filter.*' => ['nullable'],
            'filter.category_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductCategory)->getTable(), 'product_category_id')
                    ->where(fn (Builder $query): Builder => $query->where('product_category_is_active', 1)),
            ],
            'filter.is_active' => ['nullable', 'boolean'],
            'filter.is_publish' => ['nullable', 'boolean'],
        ];
    }
}
