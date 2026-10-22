<?php

namespace App\Http\Requests\Api\V1\Admin\Stc;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListShippingCostsRequest extends FormRequest
{
    use HasDataTableRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            ...$this->dataTableRules(),
            'sort' => ['nullable', Rule::in(['id', '-id', 'datetime', '-datetime', 'code', '-code', 'uncharged_cost', '-uncharged_cost'])],
            'filter.method' => ['nullable', Rule::when(is_array($this->input('filter.method')),
                ['array:eq,ne'], [Rule::in(['courier_express', 'courier_instant', 'courier_manual'])])],
            'filter.method.*' => ['nullable', Rule::in(['courier_express', 'courier_instant', 'courier_manual'])],
            'filter.is_preorder' => ['nullable', Rule::when(is_array($this->input('filter.is_preorder')), ['array:eq,ne'], ['boolean'])],
            'filter.is_preorder.*' => ['nullable', 'boolean'],
            'filter.datetime' => ['nullable', 'array:gte,lt'],
            'filter.datetime.gte' => ['nullable', 'date_format:Y-m-d'],
            'filter.datetime.lt' => ['nullable', 'date_format:Y-m-d'],
        ];
        foreach (['code', 'buyer_name', 'buyer_code'] as $field) {
            $key = 'filter.'.$field;
            $rules[$key] = ['nullable', Rule::when(is_array($this->input($key)), ['array:eq,ne,like'], ['string', 'max:100'])];
            $rules[$key.'.*'] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }
}
