<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReturnsRequest extends FormRequest
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
            'filter.status' => ['nullable', Rule::in([
                'submitted',
                'waiting_member_shipment',
                'return_in_transit',
                'return_shipping_failed',
                'received_by_company',
                'replacement_in_transit',
                'replacement_shipping_failed',
                'rejected',
                'completed',
            ])],
            'filter.member_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
