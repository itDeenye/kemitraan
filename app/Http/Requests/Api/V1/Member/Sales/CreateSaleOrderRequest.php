<?php

namespace App\Http\Requests\Api\V1\Member\Sales;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSaleOrderRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists((new Customer)->getTable(), 'customer_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('customer_member_id', $memberId)
                        ->where('customer_is_deleted', 0)),
            ],
            'payment_method' => ['required', Rule::in(['cash'])],
            'bank_account_id' => ['prohibited'],
            'shipping_method' => [
                'required',
                Rule::in(['pickup']),
            ],
            'shipping_cost' => ['prohibited'],
            'courier' => ['prohibited'],
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
            'items.*.batches' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.batches.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batches.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.batches.*.expiry_date' => ['required', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'pelanggan',
            'payment_method' => 'metode pembayaran',
            'bank_account_id' => 'rekening tujuan',
            'shipping_method' => 'metode pengiriman',
            'courier.cost' => 'biaya pengiriman',
            'courier.name' => 'nama kurir',
            'courier.service' => 'layanan kurir',
            'courier.type' => 'tipe layanan kurir',
            'courier.etd' => 'estimasi pengiriman',
            'courier.force_insurance' => 'status wajib asuransi',
            'courier.insurance' => 'biaya asuransi',
            'items' => 'produk',
            'items.*.product_id' => 'produk',
            'items.*.quantity' => 'jumlah produk',
            'items.*.batches' => 'rincian batch produk',
            'items.*.batches.*.quantity' => 'jumlah per batch',
            'items.*.batches.*.batch_number' => 'nomor batch',
            'items.*.batches.*.expiry_date' => 'tanggal kedaluwarsa batch',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_method.in' => 'Metode pembayaran POS hanya tersedia secara tunai.',
            'bank_account_id.prohibited' => 'Rekening tujuan tidak digunakan untuk transaksi POS.',
            'shipping_method.in' => 'Metode penyerahan POS hanya tersedia ambil di tempat.',
            'shipping_cost.prohibited' => 'Biaya pengiriman tidak digunakan untuk transaksi POS.',
            'courier.prohibited' => 'Data kurir tidak digunakan untuk transaksi POS.',
            'items.*.batches.*.expiry_date.after' => 'Tanggal kedaluwarsa batch harus setelah hari ini.',
        ];
    }
}
