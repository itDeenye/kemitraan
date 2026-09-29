<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\Member;
use App\Models\RefBank;
use App\Models\RefCity;
use App\Models\RefCountry;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDistributorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'mobile_phone' => ['required', 'string', 'regex:/^\+62[0-9]{8,13}$/'],
            'gender' => ['required', 'in:Laki-laki,Perempuan'],
            'birth_date' => ['required', 'date', 'before:today'],
            'address' => ['required', 'string', 'max:255'],
            'domicile_address' => ['required', 'string', 'max:255'],
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
            'country_id' => [
                'sometimes',
                'integer',
                Rule::exists((new RefCountry)->getTable(), 'country_id')
                    ->where('country_is_active', '1'),
            ],
            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists((new RefBank)->getTable(), 'bank_id')
                    ->where('bank_is_active', 1),
            ],
            'bank_account_name' => ['nullable', 'required_with:bank_id', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'required_with:bank_id', 'string', 'max:50'],
            'bank_city' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:50'],
            'identity_type' => ['required', 'in:KTP,SIM,PASPOR'],
            'identity_no' => ['required', 'string', 'max:20'],
            'identity_image_url' => ['nullable', 'string', 'max:255'], // Optional kalau admin input manual bisa nyusul
            'status' => ['required', 'integer', 'in:1,2'], // 1: Aktif, 2: Non-aktif
            'instagram' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateRegion($validator);
                $this->validateUniqueApplicant($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_phone')) {
            $this->merge(['mobile_phone' => PhoneNumber::normalize($this->input('mobile_phone'))]);
        }
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'email',
            'mobile_phone' => 'nomor WhatsApp',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            'address' => 'alamat KTP',
            'domicile_address' => 'alamat domisili',
            'province_id' => 'provinsi',
            'city_id' => 'kota/kabupaten',
            'district_id' => 'kecamatan',
            'subdistrict_id' => 'kelurahan/desa',
            'country_id' => 'negara',
            'bank_id' => 'bank',
            'bank_account_name' => 'nama pemilik rekening',
            'bank_account_number' => 'nomor rekening',
            'bank_city' => 'kota bank',
            'bank_branch' => 'cabang bank',
            'identity_type' => 'jenis identitas',
            'identity_no' => 'nomor identitas',
            'identity_image_url' => 'dokumen identitas',
            'status' => 'status',
            'instagram' => 'instagram',
            'facebook' => 'facebook',
            'tiktok' => 'tiktok',
            'password' => 'kata sandi',
            'password_confirmation' => 'konfirmasi kata sandi',
        ];
    }

    private function validateRegion(Validator $validator): void
    {
        $provinceId = $this->input('province_id');
        $cityId = $this->input('city_id');
        $districtId = $this->input('district_id');
        $subdistrictId = $this->input('subdistrict_id');

        if (! is_string($provinceId) || ! is_string($cityId) || ! is_string($districtId)
            || ! is_numeric($subdistrictId)) {
            return;
        }

        $valid = RefCity::query()
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

        if (! $valid) {
            $validator->errors()->add(
                'province_id',
                'Wilayah provinsi, kota, kecamatan, dan kelurahan tidak saling sesuai.'
            );
        }
    }

    private function validateUniqueApplicant(Validator $validator): void
    {
        $mobilePhone = $this->input('mobile_phone');
        $identityNo = $this->input('identity_no');

        if (is_string($mobilePhone) && $mobilePhone !== '') {
            $exists = Member::query()
                ->where('member_mobilephone', $mobilePhone)
                ->where('member_status', '!=', 3)
                ->exists();
            if ($exists) {
                $validator->errors()->add('mobile_phone', 'Nomor WhatsApp sudah digunakan.');
            }
        }

        if (is_string($identityNo) && $identityNo !== '') {
            $exists = Member::query()
                ->where('member_identity_no', $identityNo)
                ->where('member_status', '!=', 3)
                ->exists();
            if ($exists) {
                $validator->errors()->add('identity_no', 'Nomor identitas sudah digunakan.');
            }
        }
    }
}
