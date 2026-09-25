<?php

namespace App\Http\Requests\Api\V1\Callback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StcShippingCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => [
                'required',
                'string',
                Rule::in([
                    'processed_packages',
                    'shipped_packages',
                    'canceled_packages',
                    'finished_packages',
                    'returned_packages',
                    'problem_packages',
                    'return_finished_package',
                ]),
            ],
            'data' => ['required', 'array', 'min:1'],
            'data.*.order_id' => ['required', 'string', 'max:50', 'distinct'],
            'data.*.awb' => ['nullable', 'string', 'max:50'],
            'data.*.date' => ['nullable', 'date'],
            'data.*.shipped_at' => ['nullable', 'date'],
            'data.*.finished_at' => ['nullable', 'date'],
            'data.*.returned_at' => ['nullable', 'date'],
            'data.*.rejected_at' => ['nullable', 'date'],
            'data.*.problem_at' => ['nullable', 'date'],
            'data.*.return_finished_at' => ['nullable', 'date'],
            'data.*.reason' => ['nullable', 'string', 'max:255'],
            'data.*.sorting_code' => ['nullable', 'string', 'max:100'],
            'payment' => ['sometimes', 'nullable', 'array'],
            'packages' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'method' => 'jenis pemberitahuan pengiriman',
            'data' => 'data pengiriman',
            'data.*.order_id' => 'nomor pesanan pengiriman',
            'data.*.awb' => 'nomor resi',
            'data.*.date' => 'tanggal status',
            'data.*.shipped_at' => 'tanggal pengiriman',
            'data.*.finished_at' => 'tanggal selesai',
            'data.*.returned_at' => 'tanggal retur',
            'data.*.rejected_at' => 'tanggal pembatalan',
            'data.*.problem_at' => 'tanggal kendala pengiriman',
            'data.*.return_finished_at' => 'tanggal selesai pengembalian',
            'data.*.reason' => 'alasan status',
            'data.*.sorting_code' => 'kode sortir',
        ];
    }
}
