<?php

namespace App\Http\Requests\Api\V1\Reference;

use App\Models\RefDistrict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSubdistrictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'district_id' => [
                'required',
                'integer',
                Rule::exists((new RefDistrict)->getTable(), 'district_id'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['district_id' => 'kecamatan'];
    }
}
