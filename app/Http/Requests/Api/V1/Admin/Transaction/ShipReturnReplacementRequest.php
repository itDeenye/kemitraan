<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use App\Models\Product;
use App\Models\ReturnModel;
use App\Support\InstantShipping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipReturnReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $shippingMethods = $this->returnUsesExpressShipping()
            ? ['courier_express']
            : ['courier_express', 'courier_instant', 'courier_manual', 'pickup'];

        return [
            'delivery_note_number' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
            'shipping_method' => ['required', Rule::in($shippingMethods)],
            'shipping_cost' => ['prohibited'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists((new Product)->getTable(), 'product_id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => ['required', 'date', 'after:today'],
            'courier' => [
                Rule::requiredIf($this->input('shipping_method') !== 'pickup'),
                'nullable',
                'array',
            ],
            'courier.cost' => [
                Rule::requiredIf($this->input('shipping_method') !== 'pickup'),
                Rule::prohibitedIf($this->input('shipping_method') === 'pickup'),
                'nullable',
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.name' => [
                Rule::requiredIf($this->usesCourier()),
                'nullable',
                'string',
                'max:20',
                ...($this->usesInstant() ? [Rule::in(InstantShipping::COURIER_CODES)] : []),
            ],
            'courier.service' => [Rule::requiredIf($this->usesCourier()), 'nullable', 'string', 'max:10'],
            'courier.type' => [Rule::requiredIf($this->usesStc()), 'nullable', 'string', 'max:10'],
            'courier.etd' => ['nullable', 'string', 'max:20'],
            'courier.pickup_method' => [
                Rule::requiredIf($this->input('shipping_method') === 'courier_express'),
                'nullable',
                Rule::in(['DROP-OFF', 'PICKUP']),
            ],
            'courier.pickup_schedule' => [
                Rule::requiredIf($this->input('shipping_method') === 'courier_express'),
                'nullable',
                'date',
                'after:now',
            ],
            'courier.insurance' => [
                Rule::prohibitedIf($this->usesInstant()),
                'sometimes',
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.force_insurance' => [
                Rule::prohibitedIf($this->usesInstant()),
                'sometimes',
                'boolean',
            ],
            'courier.vehicle' => [
                Rule::requiredIf($this->usesInstant()),
                'nullable',
                'string',
                'max:20',
                Rule::in(InstantShipping::VEHICLES),
            ],
            'courier.admin_fee' => [
                Rule::requiredIf($this->usesInstant()),
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.origin_latitude' => [Rule::requiredIf($this->usesInstant()), 'nullable', 'numeric', 'between:-90,90'],
            'courier.origin_longitude' => [Rule::requiredIf($this->usesInstant()), 'nullable', 'numeric', 'between:-180,180'],
            'courier.destination_latitude' => [Rule::requiredIf($this->usesInstant()), 'nullable', 'numeric', 'between:-90,90'],
            'courier.destination_longitude' => [Rule::requiredIf($this->usesInstant()), 'nullable', 'numeric', 'between:-180,180'],
            'courier.tracking_number' => [
                Rule::requiredIf($this->input('shipping_method') === 'courier_manual'),
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'delivery_note_number' => 'nomor surat jalan pengganti',
            'note' => 'catatan pengiriman pengganti',
            'shipping_method' => 'metode pengiriman',
            'courier.cost' => 'biaya pengiriman pengganti',
            'items' => 'rincian batch barang pengganti',
            'items.*.product_id' => 'produk pengganti',
            'items.*.quantity' => 'jumlah produk pengganti',
            'items.*.batch_number' => 'nomor batch produk pengganti',
            'items.*.expiry_date' => 'tanggal kedaluwarsa produk pengganti',
            'courier.name' => 'nama kurir',
            'courier.service' => 'layanan kurir',
            'courier.type' => 'tipe layanan kurir',
            'courier.pickup_method' => 'metode pengambilan paket',
            'courier.pickup_schedule' => 'jadwal pengambilan paket',
            'courier.vehicle' => 'kendaraan kurir instan',
            'courier.origin_latitude' => 'garis lintang asal',
            'courier.origin_longitude' => 'garis bujur asal',
            'courier.destination_latitude' => 'garis lintang tujuan',
            'courier.destination_longitude' => 'garis bujur tujuan',
            'courier.tracking_number' => 'nomor resi',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.expiry_date.after' => 'Tanggal kedaluwarsa produk pengganti harus setelah hari ini.',
            'courier.pickup_schedule.after' => 'Jadwal pengambilan paket harus setelah waktu saat ini.',
            'shipping_method.in' => $this->returnUsesExpressShipping()
                ? 'Barang pengganti wajib dikirim menggunakan Kurir Ekspres karena retur sebelumnya menggunakan Kurir Ekspres.'
                : 'Metode pengiriman barang pengganti tidak valid.',
        ];
    }

    private function returnUsesExpressShipping(): bool
    {
        $return = $this->route('return');

        return $return instanceof ReturnModel
            && $return->return_shipping_method === 'courier_express';
    }

    private function usesCourier(): bool
    {
        return in_array($this->input('shipping_method'), [
            'courier_express',
            'courier_instant',
            'courier_manual',
        ], true);
    }

    private function usesStc(): bool
    {
        return in_array($this->input('shipping_method'), ['courier_express', 'courier_instant'], true);
    }

    private function usesInstant(): bool
    {
        return $this->input('shipping_method') === 'courier_instant';
    }
}
