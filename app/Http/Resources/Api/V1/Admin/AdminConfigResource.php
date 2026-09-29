<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Config;
use Illuminate\Http\Request;
use JsonException;

class AdminConfigResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = (string) $this->value('type', 'config_type');
        $typedValue = $this->typedValue($this->value('value', 'config_value'), $type);

        return [
            'id' => $this->value('id', 'config_id'),
            'label' => $this->label((string) $this->value('key', 'config_key')),
            'key' => $this->value('key', 'config_key'),
            'scope' => $this->value('scope', 'config_scope'),
            'value' => $typedValue,
            'entries' => is_array($typedValue)
                ? collect($typedValue)->map(fn (mixed $value, string $key): array => [
                    'label' => $this->label($key),
                    'key' => $key,
                    'value' => $value,
                ])->values()->all()
                : [],
            'type' => $type,
            'created_at' => $this->value('created_at', 'config_created_datetime'),
            'updated_at' => $this->value('updated_at', 'config_updated_datetime'),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof Config
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    private function typedValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => $this->decodeJson((string) $value),
            default => (string) $value,
        };
    }

    private function decodeJson(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function label(string $key): string
    {
        $labels = [
            'reward_monthly' => 'Reward Bulanan',
            'reward_monthly_min_qty_distributor' => 'Kuantitas Pembelian Distributor',
            'reward_monthly_min_qty_agent' => 'Kuantitas Pembelian Agent',
            'reward_monthly_min_qty_reseller' => 'Kuantitas Pembelian Reseller',
            'reward_stockist' => 'Reward Stokis',
            'reward_stockist_min_amount' => 'Minimum Omzet Reward Stokis',
            'reward_stockist_percentage_basis_points' => 'Persentase Reward Stokis',
            'upgrade' => 'Kualifikasi Upgrade',
            'upgrade_reseller_min_qty_per_month' => 'Minimum Kuantitas Reseller per Bulan',
            'upgrade_reseller_min_total_qty' => 'Minimum Total Kuantitas Reseller',
            'upgrade_reseller_min_customer_count' => 'Minimum Jumlah Pelanggan Reseller',
            'upgrade_agent_min_qty_per_month' => 'Minimum Kuantitas Agent per Bulan',
            'upgrade_agent_min_total_qty' => 'Minimum Total Kuantitas Agent',
            'upgrade_agent_min_customer_count' => 'Minimum Jumlah Pelanggan Agent',
            'upgrade_agent_min_reseller_count' => 'Minimum Jumlah Reseller Agent',
            'return' => 'Pengembalian Barang',
            'return_max_days' => 'Batas Hari Pengembalian',
            'partnership' => 'Kemitraan',
            'reward_point_per_item' => 'Poin per Produk',
            'spread_payment_percentage' => 'Persentase Spread Payment',
            'development' => 'Pengembangan',
            'frontend_enabled' => 'Frontend Aktif',
        ];

        return $labels[$key] ?? str($key)->replace(['.', '_', '-'], ' ')->title()->toString();
    }
}
