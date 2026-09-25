<?php

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWarehouseStockLogsRequest extends FormRequest
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
            'filter.warehouse_id' => ['nullable', 'integer', 'min:1'],
            'filter.product_id' => ['nullable', 'integer', 'min:1'],
            'filter.type' => ['nullable', Rule::in(['in', 'out'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['filter.type' => 'jenis mutasi', 'date_from' => 'tanggal awal', 'date_to' => 'tanggal akhir'];
    }
}
