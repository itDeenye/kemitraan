<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\MemberUpgradeQualified;
use App\Support\BusinessConfig;
use Illuminate\Http\Request;

class AdminMemberUpgradeResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $upgrade = $this->resource instanceof MemberUpgradeQualified ? $this->resource : null;
        $status = (string) $this->value('status', 'member_upgrade_qualified_status');

        return [
            'id' => $this->value('id', 'member_upgrade_qualified_id'),
            'member' => [
                'id' => $upgrade?->member?->member_id ?? $this->resource->member_id,
                'code' => $upgrade?->member?->member_code ?? $this->resource->member_code,
                'name' => $upgrade?->member?->member_name ?? $this->resource->member_name,
            ],
            'from_level' => [
                'id' => $upgrade?->fromLevel?->member_level_id ?? $this->resource->from_level_id,
                'code' => $upgrade?->fromLevel?->member_level_code ?? $this->resource->from_level_code,
                'name' => $upgrade?->fromLevel?->member_level_name ?? $this->resource->from_level_name,
            ],
            'to_level' => [
                'id' => $upgrade?->toLevel?->member_level_id ?? $this->resource->to_level_id,
                'code' => $upgrade?->toLevel?->member_level_code ?? $this->resource->to_level_code,
                'name' => $upgrade?->toLevel?->member_level_name ?? $this->resource->to_level_name,
            ],
            'period' => [
                'from' => $this->value('from_period', 'member_upgrade_qualified_from_year_month'),
                'to' => $this->value('to_period', 'member_upgrade_qualified_to_year_month'),
            ],
            'status' => [
                'code' => $status,
                'label' => $this->statusLabel($status),
            ],
            'processed_by' => $this->administrator($upgrade),
            'effective_date' => $upgrade
                ? $upgrade->member_upgrade_qualified_effective_date?->toDateString()
                : $this->resource->effective_date,
            'created_at' => $upgrade
                ? $upgrade->member_upgrade_qualified_created_datetime?->toAtomString()
                : $this->resource->created_at,
            ...($upgrade === null ? [] : [
                'approved_at' => $upgrade->member_upgrade_qualified_approved_datetime?->toAtomString(),
                'applied_at' => $upgrade->member_upgrade_qualified_applied_datetime?->toAtomString(),
                'achievements' => $this->achievements($upgrade),
                'network_transfers' => AdminMemberNetworkSwitchResource::collection(
                    $upgrade->networkTransfers
                ),
            ]),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof MemberUpgradeQualified
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    /** @return array<string, mixed>|null */
    private function administrator(?MemberUpgradeQualified $upgrade): ?array
    {
        $administratorId = $upgrade
            ? (int) $upgrade->member_upgrade_qualified_admin_id
            : (int) $this->resource->administrator_id;

        if ($administratorId === 0) {
            return null;
        }

        return [
            'id' => $administratorId,
            'name' => $upgrade?->administrator?->administrator_name
                ?? $this->resource->administrator_name,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function achievements(MemberUpgradeQualified $upgrade): array
    {
        $from = (int) $upgrade->member_upgrade_qualified_from_year_month;
        $to = (int) $upgrade->member_upgrade_qualified_to_year_month;
        $minimumPoint = $this->monthlyPointMinimum($upgrade);

        return $upgrade->member->achievements
            ->filter(function ($achievement) use ($from, $to): bool {
                $period = (((int) $achievement->member_achievement_year % 100) * 100)
                    + (int) $achievement->member_achievement_month;

                return $period >= $from && $period <= $to;
            })
            ->sortBy(fn ($achievement): int => ((int) $achievement->member_achievement_year * 100)
                + (int) $achievement->member_achievement_month)
            ->values()
            ->map(fn ($achievement, int $index): array => [
                'sequence' => $index + 1,
                'label' => 'Bulan ke-'.($index + 1),
                'period' => sprintf(
                    '%04d-%02d',
                    $achievement->member_achievement_year,
                    $achievement->member_achievement_month,
                ),
                'year' => $achievement->member_achievement_year,
                'month' => $achievement->member_achievement_month,
                'point' => $achievement->member_achievement_point,
                'minimum_point' => $minimumPoint,
                'is_point_requirement_met' => (int) $achievement->member_achievement_point >= $minimumPoint,
                'customer_count' => $achievement->member_achievement_customer_count,
                'total_transaction_amount' => $achievement->member_achievement_total_trx_amount,
            ])
            ->values()
            ->all();
    }

    private function monthlyPointMinimum(MemberUpgradeQualified $upgrade): int
    {
        return match ($upgrade->fromLevel?->member_level_code) {
            'RSL' => (int) BusinessConfig::get('upgrade.upgrade_reseller_min_qty_per_month', 50),
            'AGT' => (int) BusinessConfig::get('upgrade.upgrade_agent_min_qty_per_month', 270),
            default => 0,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'requested' => 'Menunggu Persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'scheduled' => 'Dijadwalkan',
            'applied' => 'Diterapkan',
            'cancelled' => 'Dibatalkan',
            default => $status,
        };
    }
}
