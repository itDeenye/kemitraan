<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_price' => ['required', 'integer', 'min:0'],
            'member_prices' => ['required', 'array', 'min:1'],
            'member_prices.*.member_level_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new MemberLevel)->getTable(), 'member_level_id')->where('member_level_is_active', 1),
            ],
            'member_prices.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_price' => 'harga pelanggan',
            'member_prices' => 'harga per tingkat mitra',
            'member_prices.*.member_level_id' => 'tingkat mitra',
            'member_prices.*.price' => 'harga mitra',
        ];
    }
}
