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
