<?php

namespace App\Http\Requests\Api\V1\Member\Return;

use App\Models\GoodsReceive;
use App\Models\GoodsReceiveDetail;
use App\Models\MemberAddress;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $shippingMethod = $this->input('shipping_method');
        $usesExpress = $shippingMethod === 'courier_express';
        $usesPickup = $shippingMethod === 'pickup';

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
            'description' => ['required', 'string', 'max:500'],
            'image_urls' => ['nullable', 'array', 'max:10'],
            'image_urls.*' => ['required', 'string', 'max:255'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'delivery_note_number' => [
                'required',
                'string',
                'max:50',
            ],
            'shipping_method' => ['required', Rule::in(['courier_express', 'pickup'])],
            'courier' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf($usesPickup),
                'nullable',
                'array',
            ],
            'courier.courier_code' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'string',
                'max:30',
            ],
            'courier.courier_name' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'string',
                'max:100',
            ],
            'courier.service_type' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'string',
                'max:30',
            ],
            'courier.cost' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf($usesPickup),
                'nullable',
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.etd' => [
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'string',
                'max:30',
            ],
            'courier.drop_off_available' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'boolean',
            ],
            'courier.force_insurance' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'boolean',
            ],
            'courier.insurance' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.logo_url' => [
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                'url',
                'max:2048',
            ],
            'courier.pickup_method' => [
                Rule::requiredIf($usesExpress),
                Rule::prohibitedIf(! $usesExpress),
                'nullable',
                Rule::in(['DROP-OFF', 'PICKUP']),
            ],
            'courier.pickup_schedule' => [
                'nullable',
                'date',
                'after:now',
            ],
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
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'goods_receive_id' => 'penerimaan barang',
            'address_id' => 'alamat pengambilan barang',
            'description' => 'keterangan retur',
            'image_urls' => 'foto bukti',
            'image_urls.*' => 'tautan foto bukti',
            'video_url' => 'tautan video bukti',
            'delivery_note_number' => 'nomor surat jalan retur',
            'shipping_method' => 'metode pengiriman retur',
            'courier' => 'pilihan pengiriman retur',
            'courier.courier_code' => 'kode kurir',
            'courier.courier_name' => 'nama layanan kurir',
            'courier.service_type' => 'tipe layanan kurir',
            'courier.cost' => 'biaya pengiriman retur',
            'courier.etd' => 'estimasi pengiriman',
            'courier.drop_off_available' => 'dukungan drop-off',
            'courier.force_insurance' => 'status wajib asuransi',
            'courier.insurance' => 'biaya asuransi',
            'courier.pickup_method' => 'metode serah paket',
            'courier.pickup_schedule' => 'jadwal pengiriman retur',
            'items' => 'produk retur',
            'items.*.goods_receive_detail_id' => 'batch produk penerimaan',
            'items.*.product_id' => 'produk retur',
            'items.*.quantity' => 'jumlah produk',
            'items.*.batch_number' => 'nomor batch produk retur',
            'items.*.reason' => 'alasan retur produk',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'courier.pickup_schedule.after' => 'Jadwal pengiriman retur harus setelah waktu saat ini.',
        ];
    }
}
