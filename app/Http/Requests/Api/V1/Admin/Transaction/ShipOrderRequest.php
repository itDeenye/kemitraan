<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use App\Models\Product;
use App\Models\Trx;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $trx = $this->route('trx');
        $shippingMethod = $trx instanceof Trx ? $trx->trx_shipping_method : null;
        $isExpress = $shippingMethod === 'courier_express';
        $isManual = $shippingMethod === 'courier_manual';
        $isPickup = $shippingMethod === 'pickup';
        $isExpressReship = $isExpress && $trx instanceof Trx
            && $trx->trx_status === 'reship_required';

        return [
            'delivery_note_number' => ['required', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists((new Product)->getTable(), 'product_id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => ['required', 'date', 'after:today'],
            'pickup_pin' => [
                'nullable',
                Rule::requiredIf($isPickup),
                'digits:5',
            ],
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
            'courier' => [
                'nullable',
                Rule::requiredIf($isExpressReship),
                'array',
            ],
            'courier.courier_code' => [
                Rule::requiredIf($isExpressReship),
                'string',
                'max:30',
            ],
            'courier.courier_name' => [
                Rule::requiredIf($isExpressReship),
                'string',
                'max:100',
            ],
            'courier.service_type' => [
                Rule::requiredIf($isExpressReship),
                'string',
                'max:30',
            ],
            'courier.cost' => [Rule::requiredIf($isExpressReship), 'integer', 'min:0'],
            'courier.etd' => ['nullable', 'string', 'max:30'],
            'courier.drop_off_available' => [Rule::requiredIf($isExpressReship), 'boolean'],
            'courier.force_insurance' => [Rule::requiredIf($isExpressReship), 'boolean'],
            'courier.insurance' => [Rule::requiredIf($isExpressReship), 'integer', 'min:0'],
            'courier.logo_url' => ['nullable', 'string', 'max:500'],
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
            'courier' => 'pilihan kurir',
            'courier.courier_code' => 'kode kurir',
            'courier.courier_name' => 'nama kurir',
            'courier.service_type' => 'jenis layanan kurir',
            'courier.cost' => 'ongkos kirim ulang',
            'courier.etd' => 'estimasi pengiriman',
            'courier.drop_off_available' => 'dukungan drop-off',
            'courier.force_insurance' => 'status kewajiban asuransi',
            'courier.insurance' => 'biaya asuransi',
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
            'pickup_schedule.after' => 'Jadwal pengiriman harus setelah waktu saat ini.',
            'pickup_pin.required' => 'Kode pengambilan wajib diisi untuk metode pengambilan di tempat.',
        ];
    }
}
