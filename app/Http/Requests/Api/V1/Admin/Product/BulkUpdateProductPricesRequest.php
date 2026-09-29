<?php

namespace App\Http\Requests\Api\V1\Admin\Product;

use App\Models\MemberLevel;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*' => ['required', 'array'],
            'products.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new Product)->getTable(), 'product_id')->where('product_is_deleted', 0),
            ],
            'products.*.customer_price' => ['required', 'integer', 'min:0'],
            'products.*.member_prices' => ['required', 'array', 'min:1'],
            'products.*.member_prices.*.member_level_id' => [
                'required',
                'integer',
                Rule::exists((new MemberLevel)->getTable(), 'member_level_id')
                    ->where('member_level_is_active', 1),
            ],
            'products.*.member_prices.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array{products: mixed} */
    public function validationData(): array
    {
        return ['products' => $this->json()->all()];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->json()->all() as $productIndex => $product) {
                if (! is_array($product) || ! is_array($product['member_prices'] ?? null)) {
                    continue;
                }

                $seenLevelIds = [];
                foreach ($product['member_prices'] as $priceIndex => $memberPrice) {
                    if (! is_array($memberPrice) || ! array_key_exists('member_level_id', $memberPrice)) {
                        continue;
                    }

                    $levelId = (string) $memberPrice['member_level_id'];
                    if (isset($seenLevelIds[$levelId])) {
                        $validator->errors()->add(
                            "products.{$productIndex}.member_prices.{$priceIndex}.member_level_id",
                            'Kolom level mitra memiliki nilai duplikat.',
                        );
                    }

                    $seenLevelIds[$levelId] = true;
                }
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'products' => 'daftar harga produk',
            'products.*' => 'harga produk',
            'products.*.product_id' => 'produk',
            'products.*.customer_price' => 'harga pelanggan',
            'products.*.member_prices' => 'harga per tingkat mitra',
            'products.*.member_prices.*.member_level_id' => 'tingkat mitra',
            'products.*.member_prices.*.price' => 'harga mitra',
        ];
    }
}
