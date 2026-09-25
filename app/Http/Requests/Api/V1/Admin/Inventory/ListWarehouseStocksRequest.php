<?php

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListWarehouseStocksRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->dataTableRules(), 'filter.warehouse_id' => ['nullable', 'integer', 'min:1'], 'filter.product_id' => ['nullable', 'integer', 'min:1'], 'filter.category_id' => ['nullable', 'integer', 'min:1']];
    }
}
