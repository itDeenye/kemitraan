<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use App\Models\Trx;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'receipt_url' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'spread_receipt_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $trx = $this->route('trx');

                if (! $trx instanceof Trx || (int) $trx->trx_bill_amount <= 0) {
                    return;
                }

                if (blank($this->input('receipt_url'))) {
                    $validator->errors()->add('receipt_url', 'Bukti pembayaran wajib diunggah.');
                }
            },
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
