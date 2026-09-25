<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use App\Models\ProductCategory;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCatalogProductsRequest extends FormRequest
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
            'name' => ['nullable', 'array'],
            'name.eq' => ['nullable', 'string', 'max:100'],
            'product_name' => ['nullable', 'array'],
            'product_name.eq' => ['nullable', 'string', 'max:100'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductCategory)->getTable(), 'product_category_id')
                    ->where(fn (Builder $query): Builder => $query->where('product_category_is_active', 1)),
            ],
            'filter' => ['nullable', 'array'],
            'filter.category_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductCategory)->getTable(), 'product_category_id')
                    ->where(fn (Builder $query): Builder => $query->where('product_category_is_active', 1)),
            ],
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists((new Warehouse)->getTable(), 'warehouse_id')
                    ->where(fn (Builder $query): Builder => $query->where('warehouse_is_active', 1)),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge(['search' => trim((string) $this->input('search'))]);
        }
    }
}
