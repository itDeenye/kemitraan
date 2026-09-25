<?php

namespace App\Http\Requests\Api\V1\Admin\Company;

use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\Warehouse;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique((new Warehouse)->getTable(), 'warehouse_name')
                    ->ignore($this->route('warehouse')),
            ],
            'legal_name' => ['required', 'string', 'max:200'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'province_id' => [
                'required',
                'string',
                'max:2',
                Rule::exists((new RefProvince)->getTable(), 'province_id'),
            ],
            'city_id' => [
                'required',
                'string',
                'max:4',
                Rule::exists((new RefCity)->getTable(), 'city_id'),
            ],
            'district_id' => [
                'required',
                'string',
                'max:6',
                Rule::exists((new RefDistrict)->getTable(), 'district_id'),
            ],
            'subdistrict_id' => [
                'required',
                'integer',
                Rule::exists((new RefSubdistrict)->getTable(), 'subdistrict_id'),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->regionIsValid()) {
                    $validator->errors()->add(
                        'province_id',
                        'Wilayah provinsi, kota, kecamatan, dan kelurahan tidak saling sesuai.'
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama gudang',
            'legal_name' => 'nama legal perusahaan',
            'npwp' => 'NPWP',
            'phone' => 'nomor telepon',
            'email' => 'email',
            'logo_url' => 'tautan logo',
            'address' => 'alamat',
            'province_id' => 'provinsi',
            'city_id' => 'kota/kabupaten',
            'district_id' => 'kecamatan',
            'subdistrict_id' => 'kelurahan/desa',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'is_active' => 'status aktif',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = (string) $this->input('phone');
            if (preg_match('/^(?:\+62|62|08)/', $phone) === 1) {
                $this->merge(['phone' => PhoneNumber::normalize($phone)]);
            }
        }
    }

    private function regionIsValid(): bool
    {
        $provinceId = $this->input('province_id');
        $cityId = $this->input('city_id');
        $districtId = $this->input('district_id');
        $subdistrictId = $this->input('subdistrict_id');

        if (! is_string($provinceId) || ! is_string($cityId) || ! is_string($districtId)) {
            return true;
        }

        if (! is_numeric($subdistrictId)) {
            return true;
        }

        return RefCity::query()
            ->where('city_id', $cityId)
            ->where('city_province_id', $provinceId)
            ->exists()
            && RefDistrict::query()
                ->where('district_id', $districtId)
                ->where('district_city_id', $cityId)
                ->exists()
            && RefSubdistrict::query()
                ->where('subdistrict_id', (int) $subdistrictId)
                ->where('subdistrict_district_id', (int) $districtId)
                ->exists();
    }
}
