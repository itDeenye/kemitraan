<?php

namespace App\Http\Requests\Api\V1\Admin\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'date_from' => 'tanggal awal',
            'date_to' => 'tanggal akhir',
            'month' => 'bulan',
            'year' => 'tahun',
        ];
    }
}
