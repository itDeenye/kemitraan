<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use App\Models\Trx;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmGoodsReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $trx = $this->route('trx');
        $requiresDeliveryNote = $trx instanceof Trx
            && $trx->trx_seller_type === 'warehouse';

        return [
            'delivery_note_number' => [
                Rule::requiredIf($requiresDeliveryNote),
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
            'delivery_note_number' => 'nomor surat jalan',
        ];
    }
}
