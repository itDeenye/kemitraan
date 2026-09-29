<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\Config;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $config = $this->route('config');

        return match ($config instanceof Config ? $config->config_key : null) {
            'reward_monthly' => [
                'value' => ['required', 'array:reward_monthly_min_qty_distributor,reward_monthly_min_qty_agent,reward_monthly_min_qty_reseller'],
                'value.reward_monthly_min_qty_distributor' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'value.reward_monthly_min_qty_agent' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'value.reward_monthly_min_qty_reseller' => ['required', 'integer', 'min:0', 'max:4294967295'],
            ],
            'reward_stockist' => [
                'value' => ['required', 'array:reward_stockist_min_amount,reward_stockist_percentage_basis_points'],
                'value.reward_stockist_min_amount' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'value.reward_stockist_percentage_basis_points' => ['required', 'integer', 'between:0,10000'],
            ],
            'partnership' => [
                'value' => ['required', 'array:reward_point_per_item,spread_payment_percentage'],
                'value.reward_point_per_item' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'value.spread_payment_percentage' => ['required', 'numeric', 'between:0,100'],
            ],
            default => ['value' => ['required', 'array']],
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'value' => 'nilai konfigurasi',
            'value.reward_monthly_min_qty_distributor' => 'minimum kuantitas Distributor',
            'value.reward_monthly_min_qty_agent' => 'minimum kuantitas Agent',
            'value.reward_monthly_min_qty_reseller' => 'minimum kuantitas Reseller',
            'value.reward_stockist_min_amount' => 'minimum omzet Reward Stokis',
            'value.reward_stockist_percentage_basis_points' => 'persentase Reward Stokis',
            'value.reward_point_per_item' => 'poin per produk',
            'value.spread_payment_percentage' => 'persentase spread payment',
        ];
    }
}
