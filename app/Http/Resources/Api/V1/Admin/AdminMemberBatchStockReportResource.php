<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AdminMemberBatchStockReportResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $memberId = (int) $this->member_id;
        $productId = (int) $this->product_id;
        $batchNumber = filled($this->batch_number) ? (string) $this->batch_number : null;
        $expiryDate = filled($this->expiry_date)
            ? CarbonImmutable::parse($this->expiry_date)->startOfDay()
            : null;
        $daysUntilExpiry = $expiryDate
            ? (int) CarbonImmutable::today()->diffInDays($expiryDate, false)
            : null;

        return [
            'id' => implode(':', [$memberId, $productId, $batchNumber ?? 'unassigned']),
            'member' => [
                'id' => $memberId,
                'code' => $this->member_code,
                'name' => $this->member_name,
                'status' => (int) $this->member_status,
                'level' => [
                    'id' => (int) $this->member_level_id,
                    'code' => $this->member_level_code,
                    'name' => $this->member_level_name,
                ],
            ],
            'product' => [
                'id' => $productId,
                'code' => $this->product_code,
                'name' => $this->product_name,
                'category' => [
                    'id' => (int) $this->category_id,
                    'name' => $this->category_name,
                ],
                'unit' => $this->unit,
            ],
            'batch' => [
                'number' => $batchNumber,
                'expiry_date' => $expiryDate?->toDateString(),
                'expiry_status' => $this->expiryStatus($daysUntilExpiry),
                'days_until_expiry' => $daysUntilExpiry,
            ],
            'balance' => (int) $this->balance,
        ];
    }

    private function expiryStatus(?int $daysUntilExpiry): string
    {
        return match (true) {
            $daysUntilExpiry === null => 'unknown',
            $daysUntilExpiry < 0 => 'expired',
            $daysUntilExpiry <= 90 => 'expiring_soon',
            default => 'safe',
        };
    }
}
