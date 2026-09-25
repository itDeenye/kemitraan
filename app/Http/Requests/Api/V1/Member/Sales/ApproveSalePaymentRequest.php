<?php

namespace App\Http\Requests\Api\V1\Member\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt_url' => ['nullable', 'string', 'max:255'],
            'spread_receipt_url' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'receipt_url' => 'bukti pembayaran',
            'spread_receipt_url' => 'bukti spread payment',
            'note' => 'catatan verifikasi',
        ];
    }
}
