<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->dataTableRules(),
            'filter.status' => ['nullable', Rule::in(['pending', 'submitted', 'approved', 'rejected'])],
            'filter.transaction_status' => ['nullable', 'array'],
            'filter.transaction_status.eq' => ['nullable', Rule::in([
                'rejected', 'waiting_stock_screening', 'waiting_payment', 'waiting_payment_approval',
                'cancelled', 'processing', 'shipped', 'reship_required', 'ready_to_pickup',
                'received', 'completed',
            ])],
            'filter.transaction_status.ne' => ['nullable', Rule::in([
                'rejected', 'waiting_stock_screening', 'waiting_payment', 'waiting_payment_approval',
                'cancelled', 'processing', 'shipped', 'reship_required', 'ready_to_pickup',
                'received', 'completed',
            ])],
            'filter.bank_id' => ['nullable', 'integer', 'min:1'],
            'filter.buyer_type' => ['nullable', Rule::in(['distributor', 'agent', 'reseller', 'customer'])],
            'filter.buyer_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'filter.buyer_type' => 'tipe pembeli',
            'filter.buyer_id' => 'ID pembeli',
        ];
    }
}
