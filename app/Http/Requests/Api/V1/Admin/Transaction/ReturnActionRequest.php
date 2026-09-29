<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'shipping_cost_bearer' => ['sometimes', Rule::in(['warehouse', 'member'])],
            'pickup_pin' => ['nullable', 'digits:5'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'note' => 'catatan retur',
            'shipping_cost_bearer' => 'penanggung biaya kirim',
            'pickup_pin' => 'kode pengambilan',
        ];
    }
}
