<?php

namespace App\Services\Dashboard;

use App\Exceptions\ProcessException;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\Trx;
use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MemberDashboardService
{
    /** @param array<string, mixed> $params */
    public function dashboard(int $memberId, array $params): array
    {
        $member = Member::query()
            ->select(['member_id', 'member_code', 'member_member_level_id', 'member_name', 'member_status'])
            ->with('level:member_level_id,member_level_code,member_level_name')
            ->findOrFail($memberId);
        [$dateFrom, $dateTo] = $this->period($params);
        $sellerType = $this->memberType($member->level?->member_level_code);

        $salesSummary = Trx::query()
            ->where('trx_seller_id', $memberId)
            ->where('trx_seller_type', $sellerType)
            ->whereBetween('trx_datetime', [$dateFrom, $dateTo])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN trx_status NOT IN '
                ."('waiting_stock_screening', 'waiting_payment', 'waiting_payment_approval', 'rejected', 'cancelled') "
                .'THEN trx_grand_total_price ELSE 0 END), 0) as product_value'
            )
            ->first();

        $purchaseProductValue = Trx::query()
            ->where('trx_buyer_id', $memberId)
            ->where('trx_buyer_type', $sellerType)
            ->whereBetween('trx_datetime', [$dateFrom, $dateTo])
            ->whereNotIn('trx_status', [
                'waiting_stock_screening',
                'waiting_payment',
                'waiting_payment_approval',
                'rejected',
                'cancelled',
            ])
            ->sum('trx_grand_total_price');

        $salesProductValue = (int) ($salesSummary?->product_value ?? 0);
        $purchaseProductValue = (int) $purchaseProductValue;
        $grossProfit = max(0, $salesProductValue - $purchaseProductValue);

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'member' => [
                'id' => (int) $member->getKey(),
                'code' => $member->member_code,
                'name' => $member->member_name,
                'level' => [
                    'id' => (int) $member->member_member_level_id,
                    'code' => $member->level?->member_level_code,
                    'name' => $member->level?->member_level_name,
                ],
            ],
            'summary' => [
                'gross_profit' => $grossProfit,
                'purchases' => [
                    'product_value' => $purchaseProductValue,
                ],
                'sales' => [
                    'product_value' => $salesProductValue,
                ],
            ],
            'latest_relationships' => $member->level?->member_level_code === 'RSL'
                ? $this->latestCustomers($memberId)
                : $this->latestDownlines($memberId),
        ];
    }

    /** @return array{type: string, results: list<array<string, mixed>>} */
    private function latestDownlines(int $memberId): array
    {
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $results = DB::table($memberTable)
            ->leftJoin($levelTable, "{$levelTable}.member_level_id", '=', "{$memberTable}.member_member_level_id")
            ->where("{$memberTable}.member_parent_member_id", $memberId)
            ->where("{$memberTable}.member_status", '!=', 3)
            ->orderByDesc("{$memberTable}.member_join_datetime")
            ->orderByDesc("{$memberTable}.member_id")
            ->limit(3)
            ->get([
                "{$memberTable}.member_id as id",
                "{$memberTable}.member_code as code",
                "{$memberTable}.member_name as name",
                "{$memberTable}.member_image as image",
                "{$memberTable}.member_mobilephone as mobile_phone",
                "{$memberTable}.member_status as status",
                "{$memberTable}.member_join_datetime as joined_at",
                "{$levelTable}.member_level_code as level_code",
                "{$levelTable}.member_level_name as level_name",
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'image' => MediaUrl::publicUrl($row->image),
                'mobile_phone' => $row->mobile_phone,
                'status' => (int) $row->status,
                'joined_at' => $row->joined_at,
                'level' => ['code' => $row->level_code, 'name' => $row->level_name],
            ])
            ->all();

        return ['type' => 'downline', 'results' => $results];
    }

    /** @return array{type: string, results: list<array<string, mixed>>} */
    private function latestCustomers(int $memberId): array
    {
        $results = Customer::query()
            ->where('customer_member_id', $memberId)
            ->where('customer_is_deleted', 0)
            ->latest('customer_created_datetime')
            ->latest('customer_id')
            ->limit(3)
            ->get([
                'customer_id',
                'customer_name',
                'customer_whatsapp',
                'customer_phone',
                'customer_address',
                'customer_created_datetime',
            ])
            ->map(fn (Customer $customer): array => [
                'id' => (int) $customer->getKey(),
                'name' => $customer->customer_name,
                'whatsapp' => $customer->customer_whatsapp,
                'phone' => $customer->customer_phone,
                'address' => $customer->customer_address,
                'created_at' => $customer->customer_created_datetime?->toAtomString(),
            ])
            ->all();

        return ['type' => 'customer', 'results' => $results];
    }

    private function memberType(?string $levelCode): string
    {
        return match ($levelCode) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => throw new ProcessException('Tingkat kemitraan Anda tidak dikenali.'),
        };
    }

    /** @param array<string, mixed> $params
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(array $params): array
    {
        $year = (int) ($params['year'] ?? now()->year);
        $month = (int) ($params['month'] ?? now()->month);
        $anchor = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
        $dateFrom = isset($params['date_from'])
            ? CarbonImmutable::parse($params['date_from'], config('app.timezone'))->startOfDay()
            : $anchor->startOfMonth();
        $dateTo = isset($params['date_to'])
            ? CarbonImmutable::parse($params['date_to'], config('app.timezone'))->endOfDay()
            : $anchor->endOfMonth();

        return [$dateFrom, $dateTo];
    }
}
