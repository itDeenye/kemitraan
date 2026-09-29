<?php

namespace App\Http\Requests\Api\V1\Member\Sales;

use App\Models\Customer;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Support\PhoneNumber;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSaleCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'whatsapp' => [
                'required',
                'string',
                'max:20',
                Rule::unique((new Customer)->getTable(), 'customer_whatsapp'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['required', 'string', 'max:500'],
            'province_id' => [
                'required',
                'integer',
                Rule::exists((new RefProvince)->getTable(), 'province_id'),
            ],
            'city_id' => [
                'required',
                'integer',
                Rule::exists((new RefCity)->getTable(), 'city_id')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'city_province_id',
                        $this->integer('province_id'),
                    )),
            ],
            'district_id' => [
                'required',
                'integer',
                Rule::exists((new RefDistrict)->getTable(), 'district_id')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'district_city_id',
                        $this->integer('city_id'),
                    )),
            ],
            'subdistrict_id' => [
                'required',
                'integer',
                Rule::exists((new RefSubdistrict)->getTable(), 'subdistrict_id')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'subdistrict_district_id',
                        $this->integer('district_id'),
                    )),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp' => PhoneNumber::normalize($this->input('whatsapp')),
            'phone' => PhoneNumber::normalize($this->input('phone')),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama pelanggan',
            'whatsapp' => 'WhatsApp pelanggan',
            'phone' => 'nomor telepon pelanggan',
            'gender' => 'jenis kelamin pelanggan',
            'birth_date' => 'tanggal lahir pelanggan',
            'address' => 'alamat pelanggan',
            'province_id' => 'provinsi pelanggan',
            'city_id' => 'kota pelanggan',
            'district_id' => 'kecamatan pelanggan',
            'subdistrict_id' => 'kelurahan pelanggan',
        ];
    }
}
