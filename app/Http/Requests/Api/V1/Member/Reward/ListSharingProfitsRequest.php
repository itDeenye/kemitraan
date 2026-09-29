<?php

namespace App\Http\Requests\Api\V1\Member\Reward;

use Illuminate\Foundation\Http\FormRequest;

class ListSharingProfitsRequest extends FormRequest
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
            'field_search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', 'string', 'in:pending,approved,paid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'search' => 'pencarian',
            'sort' => 'pengurutan',
            'page' => 'halaman',
            'limit' => 'jumlah data',
            'filter.status' => 'status pembayaran',
        ];
    }
}
