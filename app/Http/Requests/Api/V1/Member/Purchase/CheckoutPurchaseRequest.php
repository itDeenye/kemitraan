<?php

namespace App\Http\Requests\Api\V1\Member\Purchase;

use App\Models\BankCompany;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\Product;
use App\Support\InstantShipping;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $memberId = (int) $this->user()?->member_account_member_id;
        $usesExpress = $this->input('shipping_method') === 'courier_express';
        $usesManual = $this->input('shipping_method') === 'courier_manual';
        $usesExpressRate = $usesExpress || $usesManual;
        $usesInstant = $this->usesInstant();
        $usesCourier = $this->input('shipping_method') !== 'pickup';

        return [
            'address_id' => [
                'nullable',
                'required_unless:shipping_method,pickup',
                'integer',
                Rule::exists((new MemberAddress)->getTable(), 'member_address_id')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'member_address_member_id',
                        $memberId
                    )),
            ],
            'bank_company_id' => [
                'nullable',
                'nullable',
                'integer',
                Rule::exists((new BankCompany)->getTable(), 'bank_company_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('bank_company_type', 'company')
                        ->where('bank_company_bank_is_active', 1)),
            ],
            'bank_account_id' => [
                'nullable',
                'nullable',
                'integer',
                Rule::exists((new MemberBankAccount)->getTable(), 'member_bank_account_id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('member_bank_account_is_active', 1)),
            ],
            'shipping_method' => [
                'required',
                Rule::in(['courier_express', 'courier_instant', 'courier_manual', 'pickup']),
            ],
            'shipping_cost' => ['prohibited'],
            'courier' => ['nullable', 'required_unless:shipping_method,pickup', 'array'],
            'courier.name' => [
                'nullable',
                Rule::prohibitedIf($usesExpressRate),
                Rule::requiredIf($usesInstant),
                'string',
                'max:50',
                ...($usesInstant ? [Rule::in(InstantShipping::COURIER_CODES)] : []),
            ],
            'courier.service' => [
                'nullable',
                Rule::prohibitedIf($usesExpressRate),
                Rule::requiredIf($usesInstant),
                'string',
                'max:50',
            ],
            'courier.type' => [
                'nullable',
                Rule::prohibitedIf($usesExpressRate),
                Rule::requiredIf($usesInstant),
                'string',
                'max:30',
            ],
            'courier.courier_code' => [
                Rule::requiredIf($usesExpressRate),
                Rule::prohibitedIf(! $usesExpressRate),
                'nullable',
                'string',
                'max:30',
            ],
            'courier.courier_name' => [
                Rule::requiredIf($usesExpressRate),
                Rule::prohibitedIf(! $usesExpressRate),
                'nullable',
                'string',
                'max:100',
            ],
            'courier.service_type' => [
                Rule::requiredIf($usesExpressRate),
                Rule::prohibitedIf(! $usesExpressRate),
                'nullable',
                'string',
                'max:30',
            ],
            'courier.cost' => [
                Rule::requiredIf($usesCourier),
                Rule::prohibitedIf(! $usesCourier),
                'nullable',
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.etd' => [
                'nullable',
                Rule::requiredIf($usesInstant),
                'string',
                'max:30',
            ],
            'courier.drop' => ['prohibited'],
            'courier.drop_off_available' => [
                Rule::requiredIf($usesExpressRate),
                Rule::prohibitedIf(! $usesExpressRate),
                'nullable',
                'boolean',
            ],
            'courier.force_insurance' => [
                Rule::requiredIf($usesExpressRate),
                Rule::prohibitedIf(! $usesExpressRate),
                'boolean',
            ],
            'courier.insurance' => [
                Rule::requiredIf($usesExpressRate),
                Rule::prohibitedIf(! $usesExpressRate),
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.logo_url' => [
                Rule::prohibitedIf(! $usesExpressRate),
                'nullable',
                'url',
                'max:2048',
            ],
            'courier.vehicle' => [
                Rule::requiredIf($this->usesInstant()),
                'nullable',
                'string',
                'max:20',
                Rule::in(InstantShipping::VEHICLES),
            ],
            'courier.admin_fee' => [
                Rule::requiredIf($this->usesInstant()),
                'integer',
                'min:0',
                'max:999999999',
            ],
            'courier.origin_latitude' => [
                Rule::requiredIf($this->usesInstant()),
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'courier.origin_longitude' => [
                Rule::requiredIf($this->usesInstant()),
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'courier.destination_latitude' => [
                Rule::requiredIf($this->usesInstant()),
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'courier.destination_longitude' => [
                Rule::requiredIf($this->usesInstant()),
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'use_voucher' => ['sometimes', 'boolean'],
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
            'address_id' => 'alamat pengiriman',
            'bank_company_id' => 'bank tujuan transfer',
            'bank_account_id' => 'rekening penjual tujuan transfer',
            'shipping_method' => 'metode pengiriman',
            'shipping_cost' => 'biaya pengiriman',
            'courier.name' => 'nama kurir',
            'courier.service' => 'layanan kurir',
            'courier.type' => 'tipe layanan kurir',
            'courier.courier_code' => 'kode kurir',
            'courier.courier_name' => 'nama layanan kurir',
            'courier.service_type' => 'tipe layanan kurir',
            'courier.cost' => 'biaya pengiriman',
            'courier.etd' => 'estimasi pengiriman',
            'courier.drop_off_available' => 'opsi pengantaran paket ke gerai kurir',
            'courier.force_insurance' => 'status wajib asuransi',
            'courier.insurance' => 'biaya asuransi',
            'courier.vehicle' => 'kendaraan kurir instan',
            'courier.admin_fee' => 'biaya admin kurir instan',
            'courier.origin_latitude' => 'garis lintang asal',
            'courier.origin_longitude' => 'garis bujur asal',
            'courier.destination_latitude' => 'garis lintang tujuan',
            'courier.destination_longitude' => 'garis bujur tujuan',
            'items' => 'produk',
            'items.*.product_id' => 'produk',
            'items.*.quantity' => 'jumlah produk',
        ];
    }

    private function usesInstant(): bool
    {
        return $this->input('shipping_method') === 'courier_instant';
    }
}
