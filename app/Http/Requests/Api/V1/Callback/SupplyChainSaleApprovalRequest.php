<?php

namespace App\Http\Requests\Api\V1\Callback;

use Illuminate\Foundation\Http\FormRequest;

class SupplyChainSaleApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:30'],
            'delivery_note_number' => ['required', 'string', 'max:50'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_code' => ['required', 'string', 'max:100'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.batch_number' => ['required', 'string', 'max:50'],
            'products.*.expiry_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'invoice_number' => 'nomor faktur Supply Chain',
            'delivery_note_number' => 'nomor surat jalan',
            'products' => 'rincian batch produk',
            'products.*.product_code' => 'kode produk',
            'products.*.quantity' => 'jumlah produk per batch',
            'products.*.batch_number' => 'nomor batch',
            'products.*.expiry_date' => 'tanggal kedaluwarsa',
        ];
    }
}
