<?php

namespace App\Http\Requests\Api\V1\Admin\Report;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPartnershipSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'sort' => 'nullable|string',
            'search' => 'nullable|string|max:100',
            'field_search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'filter' => ['nullable', 'array', 'max:50'],
            'filter.*' => ['nullable'],
            'filter.status' => 'nullable|string',
            'filter.is_preorder' => 'nullable|boolean',
            'filter.seller_type' => ['nullable', Rule::in(['distributor', 'agent', 'reseller'])],
            'filter.buyer_type' => ['nullable', Rule::in(['distributor', 'agent', 'reseller', 'customer'])],
            'code' => 'nullable|array',
            'code.like' => 'nullable|string|max:100',
            'buyer_name' => 'nullable|array',
            'buyer_name.like' => 'nullable|string|max:100',
            'buyer_code' => 'nullable|array',
            'buyer_code.like' => 'nullable|string|max:100',
            'seller_name' => 'nullable|array',
            'seller_name.like' => 'nullable|string|max:100',
            'seller_code' => 'nullable|array',
            'seller_code.like' => 'nullable|string|max:100',
            'status' => 'nullable|array',
            'status.eq' => 'nullable|string|max:50',
            'is_preorder' => 'nullable|array',
            'is_preorder.eq' => 'nullable|boolean',
            'pagination_bool' => 'nullable|in:true,false,1,0',
        ];
    }
}
