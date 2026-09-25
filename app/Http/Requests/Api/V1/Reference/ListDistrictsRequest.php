<?php

namespace App\Http\Requests\Api\V1\Reference;

use App\Models\RefCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDistrictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city_id' => [
                'required',
                'string',
                Rule::exists((new RefCity)->getTable(), 'city_id'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['city_id' => 'kota/kabupaten'];
    }
}
