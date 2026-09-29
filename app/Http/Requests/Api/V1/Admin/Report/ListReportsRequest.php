<?php

namespace App\Http\Requests\Api\V1\Admin\Report;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListReportsRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'filter.member_id' => ['nullable', 'integer', 'min:1'],
            'filter.member_level_id' => ['nullable', 'integer', 'min:1'],
            'filter.warehouse_id' => ['nullable', 'integer', 'min:1'],
            'filter.product_id' => ['nullable', 'integer', 'min:1'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['date_from' => 'tanggal awal', 'date_to' => 'tanggal akhir'];
    }
}
