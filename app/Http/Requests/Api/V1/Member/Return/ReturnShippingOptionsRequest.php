<?php

namespace App\Http\Requests\Api\V1\Member\Return;

use App\Models\GoodsReceive;
use App\Models\GoodsReceiveDetail;
use App\Models\MemberAddress;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReturnShippingOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'goods_receive_id' => [
                'required',
                'integer',
                Rule::exists((new GoodsReceive)->getTable(), 'goods_receive_id'),
            ],
            'address_id' => [
                'required',
                'integer',
                Rule::exists((new MemberAddress)->getTable(), 'member_address_id'),
            ],
            'couriers' => ['sometimes', 'array', 'max:20'],
            'couriers.*' => ['required', 'string', 'distinct', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.goods_receive_detail_id' => [
                'sometimes',
                'nullable',
                'integer',
                'distinct',
                Rule::exists((new GoodsReceiveDetail)->getTable(), 'goods_receive_detail_id'),
            ],
            'items.*.product_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists((new Product)->getTable(), 'product_id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batch_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'goods_receive_id' => 'penerimaan barang',
            'address_id' => 'alamat pengiriman retur',
            'couriers' => 'kurir',
            'couriers.*' => 'kode kurir',
            'items' => 'produk retur',
            'items.*.goods_receive_detail_id' => 'batch produk penerimaan',
            'items.*.product_id' => 'produk retur',
            'items.*.quantity' => 'jumlah produk retur',
            'items.*.batch_number' => 'nomor batch produk retur',
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
