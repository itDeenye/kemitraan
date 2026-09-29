<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListStockistsRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->dataTableRules();
    }
}
