<?php

namespace App\Http\Requests\Api\V1\Admin\Company;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListWarehousesRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->dataTableRules(), 'filter.province_id' => ['nullable', 'string', 'max:2'], 'filter.city_id' => ['nullable', 'string', 'max:4'], 'filter.is_active' => ['nullable', 'boolean']];
    }
}
