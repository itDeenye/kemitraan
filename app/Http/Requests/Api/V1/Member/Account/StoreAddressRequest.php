<?php

namespace App\Http\Requests\Api\V1\Member\Account;

use App\Models\RefCity;
use App\Models\RefCountry;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'full_address' => ['required', 'string', 'max:500'],
            'province_id' => ['required', 'string', 'max:2', Rule::exists((new RefProvince)->getTable(), 'province_id')],
            'city_id' => ['required', 'string', 'max:4', Rule::exists((new RefCity)->getTable(), 'city_id')],
            'district_id' => ['required', 'string', 'max:6', Rule::exists((new RefDistrict)->getTable(), 'district_id')],
            'subdistrict_id' => ['required', 'integer', Rule::exists((new RefSubdistrict)->getTable(), 'subdistrict_id')],
            'country_id' => ['nullable', 'integer', Rule::exists((new RefCountry)->getTable(), 'country_id')],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
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
            'label' => 'label alamat',
            'recipient' => 'nama penerima',
            'phone' => 'nomor telepon penerima',
            'full_address' => 'alamat lengkap',
            'province_id' => 'provinsi',
            'city_id' => 'kota/kabupaten',
            'district_id' => 'kecamatan',
            'subdistrict_id' => 'kelurahan/desa',
            'country_id' => 'negara',
            'is_default' => 'status alamat utama',
        ];
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
