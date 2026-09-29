<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use Illuminate\Validation\Rule;

trait HasDataTableRules
{
    /** @return array<string, array<int, string>> */
    protected function dataTableRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pagination' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'pagination_bool' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'filter' => ['nullable', 'array', 'max:50'],
            'filter.*' => ['nullable'],
        ];
    }
}
