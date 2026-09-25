<?php

namespace App\Http\Requests\Api\V1\Member\Return;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReturnReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_note_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pickup_pin' => ['prohibited'],
            'items' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'delivery_note_number' => 'nomor surat jalan barang pengganti',
            'pickup_pin' => 'kode pengambilan',
            'items' => 'rincian produk',
        ];
    }
}
