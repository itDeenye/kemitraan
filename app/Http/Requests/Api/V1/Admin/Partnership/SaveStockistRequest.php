<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\Member;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\Stockist;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveStockistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                Rule::exists((new Member)->getTable(), 'member_id'),
                Rule::unique((new Stockist)->getTable(), 'stockist_member_id')
                    ->ignore($this->route('stockist')),
            ],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'mobile_phone' => ['required', 'string', 'regex:/^\+62[0-9]{8,13}$/'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'province_id' => [
                'required',
                'integer',
                Rule::exists((new RefProvince)->getTable(), 'province_id'),
            ],
            'city_id' => [
                'required',
                'integer',
                Rule::exists((new RefCity)->getTable(), 'city_id'),
            ],
            'district_id' => [
                'required',
                'integer',
                Rule::exists((new RefDistrict)->getTable(), 'district_id'),
            ],
            'subdistrict_id' => [
                'required',
                'integer',
                Rule::exists((new RefSubdistrict)->getTable(), 'subdistrict_id'),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->memberIsEligible()) {
                    $validator->errors()->add(
                        'member_id',
                        'Member yang dipilih harus merupakan Distributor aktif.'
                    );
                }

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
            'member_id' => 'mitra Distributor',
            'name' => 'nama stockist',
            'email' => 'email',
            'address' => 'alamat',
            'mobile_phone' => 'nomor telepon',
            'image_url' => 'tautan foto',
            'province_id' => 'provinsi',
            'city_id' => 'kota/kabupaten',
            'district_id' => 'kecamatan',
            'subdistrict_id' => 'kelurahan/desa',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'note' => 'catatan',
            'is_active' => 'status aktif',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_phone')) {
            $this->merge(['mobile_phone' => PhoneNumber::normalize($this->input('mobile_phone'))]);
        }
    }

    private function memberIsEligible(): bool
    {
        if (! is_numeric($this->input('member_id'))) {
            return true;
        }

        return Member::query()
            ->whereKey((int) $this->input('member_id'))
            ->where('member_status', 1)
            ->whereHas('level', fn ($query) => $query
                ->where('member_level_code', 'DST')
                ->where('member_level_is_active', 1))
            ->exists();
    }

    private function regionIsValid(): bool
    {
        $provinceId = $this->input('province_id');
        $cityId = $this->input('city_id');
        $districtId = $this->input('district_id');
        $subdistrictId = $this->input('subdistrict_id');

        if (! is_numeric($provinceId) || ! is_numeric($cityId)
            || ! is_numeric($districtId) || ! is_numeric($subdistrictId)) {
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
                ->where('subdistrict_id', $subdistrictId)
                ->where('subdistrict_district_id', $districtId)
                ->exists();
    }
}
