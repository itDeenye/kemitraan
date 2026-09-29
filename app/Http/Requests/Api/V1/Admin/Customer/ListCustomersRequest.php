<?php

namespace App\Http\Requests\Api\V1\Admin\Customer;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomersRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->dataTableRules(), 'filter.member_id' => ['nullable', 'integer', 'min:1'], 'filter.gender' => ['nullable', 'in:L,P'], 'filter.province_id' => ['nullable', 'string', 'max:2'], 'filter.city_id' => ['nullable', 'string', 'max:4'], 'filter.member_level' => ['nullable', 'string', 'max:100'], 'filter.member_level_id' => ['nullable', 'integer', Rule::exists((new MemberLevel)->getTable(), 'member_level_id')]];
    }
}
