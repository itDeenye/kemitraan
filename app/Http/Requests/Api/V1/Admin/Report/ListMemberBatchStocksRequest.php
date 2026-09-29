<?php

namespace App\Http\Requests\Api\V1\Admin\Report;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemberBatchStocksRequest extends FormRequest
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
            'filter.member_id' => ['nullable', 'integer', 'min:1'],
            'filter.member_level_id' => ['nullable', 'integer', 'min:1'],
            'filter.product_id' => ['nullable', 'integer', 'min:1'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'filter.batch_number' => ['nullable', 'string', 'max:50'],
            'filter.expiry_date_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.expiry_date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:filter.expiry_date_from',
            ],
            'filter.expiry_status' => [
                'nullable',
                Rule::in(['safe', 'expiring_soon', 'expired', 'unknown']),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'filter.member_id' => 'mitra',
            'filter.member_level_id' => 'tingkat mitra',
            'filter.product_id' => 'produk',
            'filter.category_id' => 'kategori produk',
            'filter.batch_number' => 'nomor batch',
            'filter.expiry_date_from' => 'tanggal kedaluwarsa awal',
            'filter.expiry_date_to' => 'tanggal kedaluwarsa akhir',
            'filter.expiry_status' => 'status kedaluwarsa',
        ];
    }
}
