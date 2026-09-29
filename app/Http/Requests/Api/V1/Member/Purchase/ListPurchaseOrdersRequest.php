<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPurchaseOrdersRequest extends FormRequest
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
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', Rule::in([
                'rejected',
                'waiting_stock_screening',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'shipped',
                'reship_required',
                'ready_to_pickup',
                'received',
                'completed',
            ])],
            'filter.is_preorder' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
