<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required_with:items',
                'integer',
                'distinct',
                Rule::exists((new Product)->getTable(), 'product_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('product_is_publish', 1)
                        ->where('product_is_active', 1)
                        ->where('product_is_deleted', 0)),
            ],
            'items.*.quantity' => [
                'required_with:items',
                'integer',
                'min:1',
                'max:100000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'items' => 'produk checkout',
            'items.*.product_id' => 'produk',
            'items.*.quantity' => 'jumlah produk',
        ];
    }
}
