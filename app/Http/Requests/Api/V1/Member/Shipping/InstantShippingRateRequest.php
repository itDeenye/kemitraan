<?php

namespace App\Http\Requests\Api\V1\Member\Shipping;

use App\Models\Product;
use App\Support\InstantShipping;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InstantShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'services' => ['required', 'array', 'min:1', 'max:3'],
            'services.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(InstantShipping::COURIER_CODES),
            ],
            'vehicle' => ['required', Rule::in(InstantShipping::VEHICLES)],
            'timezone' => ['sometimes', Rule::in(InstantShipping::TIMEZONES)],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'origin.address' => ['required', 'string', 'max:1000'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination.address' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new Product)->getTable(), 'product_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('product_is_publish', 1)
                        ->where('product_is_active', 1)
                        ->where('product_is_deleted', 0)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'services' => 'layanan kurir instan',
            'services.*' => 'layanan kurir instan',
            'vehicle' => 'jenis kendaraan',
            'timezone' => 'zona waktu',
            'origin.latitude' => 'garis lintang asal',
            'origin.longitude' => 'garis bujur asal',
            'origin.address' => 'alamat asal',
            'destination.latitude' => 'garis lintang tujuan',
            'destination.longitude' => 'garis bujur tujuan',
            'destination.address' => 'alamat tujuan',
            'items' => 'produk',
            'items.*.product_id' => 'produk',
            'items.*.quantity' => 'jumlah produk',
        ];
    }

    protected function prepareForValidation(): void
    {
        $services = $this->input('services');
        $normalized = is_array($services)
            ? collect($services)
                ->map(fn (mixed $service): string => Str::lower(trim((string) $service)))
                ->values()
                ->all()
            : $services;

        $this->merge([
            'services' => $normalized,
            'vehicle' => Str::lower(trim((string) $this->input('vehicle'))),
            'timezone' => Str::upper(trim((string) $this->input('timezone', 'WIB'))),
        ]);
    }
}
