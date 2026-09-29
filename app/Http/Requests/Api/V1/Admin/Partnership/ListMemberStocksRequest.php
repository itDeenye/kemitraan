<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemberStocksRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->dataTableRules(), 'filter.member_id' => ['nullable', 'integer', 'min:1'], 'filter.member_level' => ['nullable', 'string', 'max:100'], 'filter.member_level_id' => ['nullable', 'integer', Rule::exists((new MemberLevel)->getTable(), 'member_level_id')], 'filter.product_id' => ['nullable', 'integer', 'min:1'], 'filter.category_id' => ['nullable', 'integer', 'min:1']];
    }
}
