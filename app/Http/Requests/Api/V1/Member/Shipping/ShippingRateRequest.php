<?php

namespace App\Http\Requests\Api\V1\Member\Shipping;

use App\Models\Customer;
use App\Models\MemberAddress;
use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $memberId = (int) $this->user()?->member_account_member_id;

        return [
            'address_id' => [
                'required_without:customer_id',
                'prohibits:customer_id',
                'integer',
                Rule::exists((new MemberAddress)->getTable(), 'member_address_id')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'member_address_member_id',
                        $memberId,
                    )),
            ],
            'customer_id' => [
                'required_without:address_id',
                'prohibits:address_id',
                'integer',
                Rule::exists((new Customer)->getTable(), 'customer_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('customer_member_id', $memberId)
                        ->where('customer_is_deleted', 0)),
            ],
            'couriers' => ['sometimes', 'array', 'max:20'],
            'couriers.*' => ['required', 'string', 'distinct', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new Product)->getTable(), 'product_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('product_is_publish', 1)
                        ->where('product_is_active', 1)
                        ->where('product_is_deleted', 0)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'address_id' => 'alamat pengiriman',
            'customer_id' => 'pelanggan',
            'couriers' => 'kurir',
            'items' => 'produk',
            'items.*.product_id' => 'produk',
            'items.*.quantity' => 'jumlah produk',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'address_id.required_without' => 'Alamat pengiriman atau pelanggan wajib dipilih.',
            'customer_id.required_without' => 'Alamat pengiriman atau pelanggan wajib dipilih.',
            'address_id.prohibits' => 'Pilih salah satu tujuan: alamat pengiriman atau pelanggan.',
            'customer_id.prohibits' => 'Pilih salah satu tujuan: alamat pengiriman atau pelanggan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('couriers'))) {
            return;
        }

        $this->merge([
            'couriers' => collect($this->input('couriers'))
                ->map(fn (mixed $courier): string => Str::lower(trim((string) $courier)))
                ->values()
                ->all(),
        ]);
    }
}
