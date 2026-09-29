<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
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

class SaveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $member = $this->route('member');
        $memberId = $member instanceof Member ? (int) $member->getKey() : 0;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:100'],
            'mobile_phone' => ['required', 'string', 'regex:/^\+62[0-9]{8,13}$/'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'addresses' => ['present', 'array'],
            'addresses.*.id' => [
                'nullable',
                'integer',
                'distinct:strict',
                Rule::exists((new MemberAddress)->getTable(), 'member_address_id')
                    ->where('member_address_member_id', $memberId),
            ],
            'addresses.*.label' => ['nullable', 'string', 'max:50'],
            'addresses.*.recipient' => ['required', 'string', 'max:150'],
            'addresses.*.phone' => ['required', 'string', 'max:20'],
            'addresses.*.full_address' => ['required', 'string', 'max:500'],
            'addresses.*.province_id' => ['required', 'string', 'max:2', Rule::exists((new RefProvince)->getTable(), 'province_id')],
            'addresses.*.city_id' => ['required', 'string', 'max:4', Rule::exists((new RefCity)->getTable(), 'city_id')],
            'addresses.*.district_id' => ['required', 'string', 'max:6', Rule::exists((new RefDistrict)->getTable(), 'district_id')],
            'addresses.*.subdistrict_id' => ['required', 'integer', Rule::exists((new RefSubdistrict)->getTable(), 'subdistrict_id')],
            'addresses.*.country_id' => ['nullable', 'integer', Rule::exists((new RefCountry)->getTable(), 'country_id')],
            'addresses.*.is_default' => ['required', 'boolean'],
            'bank_accounts' => ['present', 'array'],
            'bank_accounts.*.id' => [
                'nullable',
                'integer',
                'distinct:strict',
                Rule::exists((new MemberBankAccount)->getTable(), 'member_bank_account_id')
                    ->where('member_bank_account_member_id', $memberId),
            ],
            'bank_accounts.*.bank_id' => ['required', 'integer', Rule::exists((new RefBank)->getTable(), 'bank_id')],
            'bank_accounts.*.account_name' => ['required', 'string', 'max:100'],
            'bank_accounts.*.account_number' => ['required', 'string', 'max:50'],
            'bank_accounts.*.city' => ['nullable', 'string', 'max:50'],
            'bank_accounts.*.branch' => ['nullable', 'string', 'max:50'],
            'bank_accounts.*.is_active' => ['required', 'boolean'],
            'bank_accounts.*.is_default' => ['required', 'boolean'],
            'address' => ['prohibited'],
            'subdistrict_id' => ['prohibited'],
            'district_id' => ['prohibited'],
            'city_id' => ['prohibited'],
            'province_id' => ['prohibited'],
            'country_id' => ['prohibited'],
            'bank_id' => ['prohibited'],
            'bank_name' => ['prohibited'],
            'bank_account_name' => ['prohibited'],
            'bank_account_number' => ['prohibited'],
            'bank_city' => ['prohibited'],
            'bank_branch' => ['prohibited'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'integer', Rule::in([0, 1, 2])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama mitra',
            'email' => 'email mitra',
            'mobile_phone' => 'nomor ponsel',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            'addresses' => 'daftar alamat',
            'addresses.*.id' => 'ID alamat',
            'addresses.*.label' => 'label alamat',
            'addresses.*.recipient' => 'nama penerima',
            'addresses.*.phone' => 'nomor telepon penerima',
            'addresses.*.full_address' => 'alamat lengkap',
            'addresses.*.province_id' => 'provinsi alamat',
            'addresses.*.city_id' => 'kota/kabupaten alamat',
            'addresses.*.district_id' => 'kecamatan alamat',
            'addresses.*.subdistrict_id' => 'kelurahan/desa alamat',
            'addresses.*.country_id' => 'negara alamat',
            'addresses.*.is_default' => 'status alamat utama',
            'bank_accounts' => 'daftar rekening',
            'bank_accounts.*.id' => 'ID rekening',
            'bank_accounts.*.bank_id' => 'bank',
            'bank_accounts.*.account_name' => 'nama pemilik rekening',
            'bank_accounts.*.account_number' => 'nomor rekening',
            'bank_accounts.*.city' => 'kota bank',
            'bank_accounts.*.branch' => 'cabang bank',
            'bank_accounts.*.is_active' => 'status rekening',
            'bank_accounts.*.is_default' => 'status rekening default',
            'image_url' => 'tautan foto',
            'status' => 'status mitra',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_phone')) {
            $this->merge(['mobile_phone' => PhoneNumber::normalize($this->input('mobile_phone'))]);
        }

        if (is_array($this->input('addresses'))) {
            $addresses = array_map(function (mixed $address): mixed {
                if (is_array($address) && array_key_exists('phone', $address)) {
                    $address['phone'] = PhoneNumber::normalize($address['phone']);
                }

                return $address;
            }, $this->input('addresses'));

            $this->merge(['addresses' => $addresses]);
        }
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $addresses = is_array($this->input('addresses')) ? $this->input('addresses') : [];
                $bankAccounts = is_array($this->input('bank_accounts')) ? $this->input('bank_accounts') : [];

                if ($this->defaultCount($addresses) > 1) {
                    $validator->errors()->add('addresses', 'Hanya satu alamat yang dapat dijadikan alamat utama.');
                }

                if ($this->defaultCount($bankAccounts) > 1) {
                    $validator->errors()->add('bank_accounts', 'Hanya satu rekening yang dapat dijadikan rekening default.');
                }

                foreach ($addresses as $index => $address) {
                    if (is_array($address) && ! $this->regionIsValid($address)) {
                        $validator->errors()->add(
                            "addresses.{$index}.province_id",
                            'Wilayah provinsi, kota, kecamatan, dan kelurahan tidak saling sesuai.',
                        );
                    }
                }

                foreach ($bankAccounts as $index => $bankAccount) {
                    if (! is_array($bankAccount)) {
                        continue;
                    }

                    if ($this->isTruthy($bankAccount['is_default'] ?? false)
                        && ! $this->isTruthy($bankAccount['is_active'] ?? false)) {
                        $validator->errors()->add(
                            "bank_accounts.{$index}.is_default",
                            'Rekening default harus berstatus aktif.',
                        );
                    }
                }
            },
        ];
    }

    /** @param array<int, mixed> $items */
    private function defaultCount(array $items): int
    {
        return count(array_filter(
            $items,
            fn (mixed $item): bool => is_array($item)
                && $this->isTruthy($item['is_default'] ?? false),
        ));
    }

    /** @param array<string, mixed> $address */
    private function regionIsValid(array $address): bool
    {
        $provinceId = $address['province_id'] ?? null;
        $cityId = $address['city_id'] ?? null;
        $districtId = $address['district_id'] ?? null;
        $subdistrictId = $address['subdistrict_id'] ?? null;

        if (! is_scalar($provinceId)
            || ! is_scalar($cityId)
            || ! is_scalar($districtId)
            || ! is_numeric($subdistrictId)) {
            return true;
        }

        return RefCity::query()
            ->where('city_id', (string) $cityId)
            ->where('city_province_id', (string) $provinceId)
            ->exists()
            && RefDistrict::query()
                ->where('district_id', (string) $districtId)
                ->where('district_city_id', (string) $cityId)
                ->exists()
            && RefSubdistrict::query()
                ->where('subdistrict_id', (int) $subdistrictId)
                ->where('subdistrict_district_id', (int) $districtId)
                ->exists();
    }

    private function isTruthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
