<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use App\Models\Product;
use App\Models\ReturnModel;
use App\Support\InstantShipping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $return = $this->route('return');
        $usesPickup = $return instanceof ReturnModel
            && $return->return_shipping_method === 'pickup';

        return [
            'delivery_note_number' => [
                Rule::requiredIf($this->filled('shipping_method') && $this->input('shipping_method') !== 'pickup'),
                Rule::prohibitedIf($this->input('shipping_method') === 'pickup'),
                'nullable',
                'string',
                'max:50',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'pickup_pin' => [
                Rule::requiredIf($usesPickup),
                Rule::prohibitedIf(! $usesPickup),
                'nullable',
                'digits:5',
            ],
            'shipping_cost_bearer' => [Rule::requiredIf($this->filled('shipping_method')), 'nullable', Rule::in(['warehouse', 'member'])],
            'shipping_method' => ['nullable', Rule::in([
                'courier_express',
                'courier_instant',
                'courier_manual',
                'pickup',
            ])],
            'shipping_cost' => ['prohibited'],
            'items' => [Rule::requiredIf($this->filled('shipping_method')), 'nullable', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists((new Product)->getTable(), 'product_id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => ['required', 'date', 'after:today'],
            'courier' => [Rule::requiredIf($this->filled('shipping_method')), 'nullable', 'array'],
            'courier.cost' => [
                Rule::requiredIf($this->filled('shipping_method') && $this->input('shipping_method') !== 'pickup'),
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
                Rule::requiredIf(in_array($this->input('shipping_method'), ['courier_express', 'pickup'], true)),
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
                Rule::requiredIf($this->input('shipping_method') === 'courier_instant'),
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
            'delivery_note_number' => 'nomor surat jalan retur',
            'note' => 'catatan retur',
            'pickup_pin' => 'kode verifikasi pickup',
            'shipping_cost_bearer' => 'penanggung biaya kirim',
            'shipping_method' => 'metode pengiriman',
            'courier.cost' => 'biaya pengiriman',
            'items' => 'rincian batch barang retur',
            'items.*.product_id' => 'produk retur',
            'items.*.quantity' => 'jumlah produk retur',
            'items.*.batch_number' => 'nomor batch produk retur',
            'items.*.expiry_date' => 'tanggal kedaluwarsa produk retur',
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
            'items.*.expiry_date.after' => 'Tanggal kedaluwarsa produk retur harus setelah hari ini.',
            'courier.pickup_schedule.after' => 'Jadwal pengambilan paket harus setelah waktu saat ini.',
            'pickup_pin.prohibited' => 'Kode verifikasi pickup hanya diisi untuk retur dengan metode ambil di perusahaan.',
            'pickup_pin.required' => 'Kode verifikasi pickup wajib diisi saat menyetujui retur ambil di tempat.',
        ];
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
