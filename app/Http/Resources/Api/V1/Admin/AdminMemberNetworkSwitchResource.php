<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\MemberNetworkSwitch;
use Illuminate\Http\Request;

class AdminMemberNetworkSwitchResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transfer = $this->resource instanceof MemberNetworkSwitch ? $this->resource : null;
        $status = (string) $this->value('status', 'network_switch_status');

        return [
            'id' => $this->value('id', 'network_switch_transfer_id'),
            'member' => [
                'id' => $transfer?->member?->member_id ?? $this->resource->member_id,
                'code' => $transfer?->member?->member_code ?? $this->resource->member_code,
                'name' => $transfer?->member?->member_name ?? $this->resource->member_name,
            ],
            'from_parent' => $this->parent('from', $transfer),
            'to_parent' => $this->parent('to', $transfer),
            'from_level' => $this->level('from', $transfer),
            'to_level' => $this->level('to', $transfer),
            'status' => [
                'code' => $status,
                'label' => match ($status) {
                    'scheduled' => 'Dijadwalkan',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => $status,
                },
            ],
            'processed_by' => $this->administrator($transfer),
            'effective_date' => $transfer
                ? $transfer->network_switch_effective_date?->toDateString()
                : $this->resource->effective_date,
            'created_at' => $transfer
                ? $transfer->network_switch_created_datetime?->toAtomString()
                : $this->resource->created_at,
            ...($transfer === null ? [] : [
                'type' => $transfer->network_switch_type,
                'upgrade_qualified_id' => $transfer->network_switch_upgrade_qualified_id ?: null,
                'approved_at' => $transfer->network_switch_approved_datetime?->toAtomString(),
                'applied_at' => $transfer->network_switch_applied_datetime?->toAtomString(),
                'note' => $transfer->network_switch_transfer_note,
            ]),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof MemberNetworkSwitch
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    /** @return array<string, mixed>|null */
    private function parent(string $direction, ?MemberNetworkSwitch $transfer): ?array
    {
        $relation = $direction === 'from' ? 'fromParent' : 'toParent';
        $parent = $transfer?->{$relation};
        $codeField = "{$direction}_parent_code";
        $nameField = "{$direction}_parent_name";

        if ($transfer && (int) $transfer->getAttribute("network_switch_{$direction}_parent_member_id") === 0) {
            return null;
        }

        if (! $transfer && $this->resource->{$codeField} === null) {
            return null;
        }

        return [
            'id' => $parent?->member_id
                ?? ($transfer?->getAttribute("network_switch_{$direction}_parent_member_id")),
            'code' => $parent?->member_code ?? $this->resource->{$codeField},
            'name' => $parent?->member_name ?? $this->resource->{$nameField},
            ...($transfer ? ['level_code' => $parent?->level?->member_level_code] : []),
        ];
    }

    /** @return array<string, mixed> */
    private function level(string $direction, ?MemberNetworkSwitch $transfer): array
    {
        $relation = $direction === 'from' ? 'fromLevel' : 'toLevel';
        $level = $transfer?->{$relation};

        return [
            'id' => $level?->member_level_id ?? $this->resource->{"{$direction}_level_id"},
            'code' => $level?->member_level_code ?? $this->resource->{"{$direction}_level_code"},
            'name' => $level?->member_level_name ?? $this->resource->{"{$direction}_level_name"},
        ];
    }

    /** @return array<string, mixed>|null */
    private function administrator(?MemberNetworkSwitch $transfer): ?array
    {
        $administratorId = $transfer
            ? (int) $transfer->network_switch_admin_id
            : (int) $this->resource->administrator_id;

        if ($administratorId === 0) {
            return null;
        }

        return [
            'id' => $administratorId,
            'name' => $transfer?->administrator?->administrator_name
                ?? $this->resource->administrator_name,
        ];
    }
}
