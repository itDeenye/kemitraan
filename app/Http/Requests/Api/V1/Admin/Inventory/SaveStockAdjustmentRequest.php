<?php

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists((new Warehouse)->getTable(), 'warehouse_id')->where('warehouse_is_active', 1)],
            'note' => ['required', 'string', 'max:1000'],
            'details' => ['required', 'array', 'min:1', 'max:100'],
            'details.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new Product)->getTable(), 'product_id')->where('product_is_deleted', 0),
            ],
            'details.*.batch_number' => ['required', 'string', 'max:50'],
            'details.*.type' => ['required', Rule::in(['in', 'out'])],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'details.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'gudang',
            'note' => 'alasan penyesuaian',
            'details' => 'rincian produk',
            'details.*.product_id' => 'produk',
            'details.*.batch_number' => 'nomor batch',
            'details.*.type' => 'jenis penyesuaian',
            'details.*.quantity' => 'jumlah penyesuaian',
            'details.*.unit_price' => 'harga satuan',
            'details.*.note' => 'catatan detail',
        ];
    }
}
