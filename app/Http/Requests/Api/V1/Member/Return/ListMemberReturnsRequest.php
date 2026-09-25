<?php

namespace App\Http\Requests\Api\V1\Member\Return;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemberReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter.action_required' => [
                'nullable',
                Rule::in(['true', 'false', '1', '0', true, false, 1, 0]),
            ],
            'filter.status' => [
                'nullable',
                Rule::in([
                    'submitted',
                    'approved',
                    'waiting_member_shipment',
                    'return_in_transit',
                    'return_shipping_failed',
                    'received_by_company',
                    'replacement_in_transit',
                    'replacement_shipping_failed',
                    'rejected',
                    'completed',
                ]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['filter.status' => 'status retur'];
    }
}
