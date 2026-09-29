<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Models\MemberLevel;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists((new ProductCategory)->getTable(), 'product_category_id')],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique((new Product)->getTable(), 'product_code')->ignore($this->route('product')),
            ],
            'name' => ['required', 'string', 'max:200'],
            'bpom_number' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'image_url' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
                'url:http,https',
            ],
            'customer_price' => ['required', 'integer', 'min:0'],
            'member_prices' => ['required', 'array', 'min:1'],
            'member_prices.*.member_level_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new MemberLevel)->getTable(), 'member_level_id')
                    ->where('member_level_is_active', 1),
            ],
            'member_prices.*.price' => ['required', 'integer', 'min:0'],
            'weight' => ['required', 'integer', 'min:1'],
            'length' => ['required', 'integer', 'min:1'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
            'unit' => ['sometimes', 'string', 'max:30'],
            'is_package' => ['sometimes', 'boolean'],
            'is_publish' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
