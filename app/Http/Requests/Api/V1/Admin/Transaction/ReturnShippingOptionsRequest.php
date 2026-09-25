<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReturnShippingOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'couriers' => ['sometimes', 'array', 'max:20'],
            'couriers.*' => ['required', 'string', 'distinct', 'max:30'],
            'items' => [
                Rule::requiredIf($this->isMethod('post')),
                'array',
                'min:1',
                'max:500',
            ],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'couriers' => 'kurir',
            'couriers.*' => 'kode kurir',
            'items' => 'produk retur yang disetujui',
            'items.*.product_id' => 'produk retur',
            'items.*.quantity' => 'jumlah produk retur yang disetujui',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('couriers'))) {
            return;
        }

        $this->merge([
            'couriers' => collect($this->input('couriers'))
                ->map(fn (mixed $courier): string => Str::lower(trim((string) $courier)))
                ->values()
                ->all(),
        ]);
    }
}
