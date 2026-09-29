<?php

namespace App\Http\Requests\Api\V1\Reference;

use App\Models\RefProvince;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'province_id' => [
                'required',
                'string',
                Rule::exists((new RefProvince)->getTable(), 'province_id'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['province_id' => 'provinsi'];
    }
}
