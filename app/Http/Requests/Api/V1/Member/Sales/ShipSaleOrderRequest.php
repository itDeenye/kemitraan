<?php

namespace App\Http\Requests\Api\V1\Member\Sales;

use App\Models\Product;
use App\Models\Trx;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipSaleOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $trx = $this->route('trx');
        $shippingMethod = $trx instanceof Trx ? $trx->trx_shipping_method : null;
        $isExpress = $shippingMethod === 'courier_express';
        $isManual = $shippingMethod === 'courier_manual';
        $isPickup = $shippingMethod === 'pickup';
        $isCustomerPickup = $isPickup && $trx instanceof Trx && $trx->trx_buyer_type === 'customer';

        return [
            'delivery_note_number' => $isCustomerPickup
                ? ['prohibited']
                : ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists((new Product)->getTable(), 'product_id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => ['required', 'date', 'after:today'],
            'pickup_pin' => $isCustomerPickup
                ? ['prohibited']
                : ['nullable', Rule::requiredIf($isPickup), 'digits:5'],
            'pickup_method' => [
                'nullable',
                Rule::requiredIf($isExpress),
                'string',
                Rule::in(['PICKUP', 'DROP-OFF']),
            ],
            'pickup_schedule' => [
                'nullable',
                Rule::requiredIf($isExpress),
                'date',
                'after:now',
            ],
            'tracking_number' => $isManual
                ? ['required', 'string', 'max:100']
                : ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'delivery_note_number' => 'nomor surat jalan',
            'items' => 'rincian batch pengiriman',
            'items.*.product_id' => 'produk pengiriman',
            'items.*.quantity' => 'jumlah pengiriman',
            'items.*.batch_number' => 'nomor batch produk',
            'items.*.expiry_date' => 'tanggal kedaluwarsa batch',
            'pickup_pin' => 'kode pengambilan',
            'pickup_method' => 'metode serah paket',
            'pickup_schedule' => 'jadwal pengambilan paket',
            'tracking_number' => 'nomor resi',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.expiry_date.after' => 'Tanggal kedaluwarsa batch harus setelah hari ini.',
            'tracking_number.required' => 'Nomor resi wajib diisi untuk pengiriman kurir manual.',
            'tracking_number.prohibited' => 'Nomor resi tidak boleh diisi untuk metode ini. Nomor resi express diterbitkan oleh STC.',
            'pickup_schedule.required' => 'Jadwal pengiriman wajib diisi untuk pengiriman express.',
            'pickup_pin.required' => 'Kode pengambilan wajib diisi untuk metode ambil di tempat.',
            'pickup_pin.digits' => 'Kode pengambilan harus terdiri dari 5 angka.',
            'pickup_pin.prohibited' => 'Kode pengambilan tidak digunakan untuk penjualan langsung ke pelanggan.',
            'delivery_note_number.prohibited' => 'Nomor surat jalan tidak digunakan untuk transaksi POS.',
            'items.*.expiry_date.prohibited' => 'Tanggal kedaluwarsa tidak digunakan untuk transaksi POS.',
        ];
    }
}
