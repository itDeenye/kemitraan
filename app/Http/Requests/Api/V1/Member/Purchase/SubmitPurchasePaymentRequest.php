<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt_url' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'spread_receipt_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'receipt_url' => 'bukti pembayaran',
            'note' => 'catatan pembayaran',
            'spread_receipt_url' => 'bukti spread payment',
        ];
    }
}
