<?php

namespace App\Http\Requests\Api\V1\Member\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SearchSaleCustomersRequest extends FormRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'search' => 'pencarian pelanggan',
            'limit' => 'jumlah pelanggan',
        ];
    }
}
