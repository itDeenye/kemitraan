<?php

namespace App\Http\Requests\Api\V1\Admin\Stc;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStcTransactionsRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->dataTableRules(),
            'sort' => ['nullable', Rule::in([
                'id', '-id', 'datetime', '-datetime', 'amount', '-amount',
                'type', '-type', 'category', '-category',
            ])],
            'filter.type' => ['nullable', Rule::in(['in', 'out'])],
            'filter.category' => ['nullable', Rule::in(['topup', 'trx', 'wd', 'void', 'adj'])],
            'filter.note' => ['nullable', Rule::when(is_array($this->input('filter.note')),
                ['array:eq,ne,like', 'max:1'], ['string', 'max:100'])],
            'filter.note.*' => ['nullable', 'string', 'max:100'],
            'filter.datetime' => ['nullable', 'array:gte,lt'],
            'filter.datetime.gte' => ['nullable', 'date_format:Y-m-d'],
            'filter.datetime.lt' => ['nullable', 'date_format:Y-m-d',
                Rule::when($this->filled('filter.datetime.gte'), ['after:filter.datetime.gte'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sort' => 'urutan data',
            'filter.type' => 'tipe mutasi',
            'filter.category' => 'kategori mutasi',
            'date_from' => 'tanggal awal',
            'date_to' => 'tanggal akhir',
        ];
    }
}
