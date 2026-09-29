<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_note_number' => ['prohibited'],
            'note' => ['prohibited'],
            'pickup_pin' => ['prohibited'],
            'items' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'delivery_note_number' => 'nomor surat jalan retur',
            'note' => 'catatan penerimaan retur',
            'pickup_pin' => 'kode pengambilan',
            'items' => 'rincian barang retur',
        ];
    }
}
