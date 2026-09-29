<?php

namespace App\Services\Dashboard;

use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\Product;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxSpreadPayment;
use App\Models\WarehouseStock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    private const LOW_STOCK_MAXIMUM = 5;

    /** @var list<string> */
    private const ORDER_STATUSES = [
        'waiting_stock_screening',
        'waiting_payment',
        'waiting_payment_approval',
        'processing',
        'shipped',
        'reship_required',
        'ready_to_pickup',
        'received',
        'completed',
        'cancelled',
        'rejected',
    ];

    /** @param array<string, mixed> $params */
    public function analytics(array $params): array
    {
        [$dateFrom, $dateTo] = $this->period($params);
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $stockTable = (new WarehouseStock)->getTable();
        $productTable = (new Product)->getTable();
        $detailTable = (new TrxDetail)->getTable();
        $spreadTable = (new TrxSpreadPayment)->getTable();

        $orders = DB::table($trxTable)
            ->whereBetween('trx_datetime', [$dateFrom, $dateTo])
            ->whereNotIn('trx_status', ['cancelled', 'rejected'])
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(trx_grand_total_nett_price), 0) as turnover')
            ->selectRaw("COUNT(CASE WHEN trx_status NOT IN ('completed', 'cancelled', 'rejected') THEN 1 END) as active_orders")
            ->first();

        $productsSold = DB::table($detailTable)
            ->join($trxTable, "{$trxTable}.trx_id", '=', "{$detailTable}.trx_detail_trx_id")
            ->whereBetween("{$trxTable}.trx_datetime", [$dateFrom, $dateTo])
            ->whereNotIn("{$trxTable}.trx_status", [
                'waiting_stock_screening',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'rejected',
            ])
            ->sum("{$detailTable}.trx_detail_qty");

        $totalCommission = DB::table($spreadTable)
            ->whereBetween('trx_spread_payment_created_datetime', [$dateFrom, $dateTo])
            ->where('trx_spread_payment_status', '!=', 'rejected')
            ->sum('trx_spread_payment_amount');

        $summary = [
            'total_partners' => DB::table($memberTable)->where('member_status', '!=', 3)->count(),
            'turnover' => (int) ($orders?->turnover ?? 0),
            'total_orders' => (int) ($orders?->total_orders ?? 0),
            'total_commission' => (int) $totalCommission,
            'products_sold' => (int) $productsSold,
            'active_orders' => (int) ($orders?->active_orders ?? 0),
            'warehouse_stock' => (int) DB::table($stockTable)->sum('warehouse_stock_balance'),
        ];

        $statusTotals = DB::table($trxTable)
            ->whereBetween('trx_datetime', [$dateFrom, $dateTo])
            ->select(['trx_status as status'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('trx_status')
            ->pluck('total', 'status');

        $orderStatuses = collect(self::ORDER_STATUSES)
            ->map(fn (string $status): array => [
                'code' => $status,
                'label' => $this->orderStatusLabel($status),
                'total' => (int) ($statusTotals[$status] ?? 0),
            ]);

        $salesTrend = DB::table($trxTable)
            ->whereBetween('trx_datetime', [$dateFrom, $dateTo])
            ->whereNotIn('trx_status', ['cancelled', 'rejected'])
            ->selectRaw('DATE(trx_datetime) as date')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(trx_grand_total_nett_price), 0) as turnover')
            ->groupByRaw('DATE(trx_datetime)')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => $row->date,
                'total_orders' => (int) $row->total_orders,
                'turnover' => (int) $row->turnover,
            ]);

        $memberGrowth = DB::table($memberTable)
            ->where('member_status', '!=', 3)
            ->whereBetween('member_join_datetime', [$dateFrom, $dateTo])
            ->selectRaw('DATE(member_join_datetime) as date')
            ->selectRaw('COUNT(*) as total_members')
            ->groupByRaw('DATE(member_join_datetime)')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => $row->date,
                'total_members' => (int) $row->total_members,
            ]);

        $levelDistribution = DB::table($levelTable)
            ->leftJoin($memberTable, function ($join) use ($levelTable, $memberTable): void {
                $join->on("{$memberTable}.member_member_level_id", '=', "{$levelTable}.member_level_id")
                    ->where("{$memberTable}.member_status", '!=', 3);
            })
            ->select([
                "{$levelTable}.member_level_id as id",
                "{$levelTable}.member_level_code as code",
                "{$levelTable}.member_level_name as name",
            ])
            ->selectRaw("COUNT({$memberTable}.member_id) as total_members")
            ->groupBy([
                "{$levelTable}.member_level_id",
                "{$levelTable}.member_level_code",
                "{$levelTable}.member_level_name",
                "{$levelTable}.member_level_sort_order",
            ])
            ->orderBy("{$levelTable}.member_level_sort_order")
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'total_members' => (int) $row->total_members,
            ]);

        $highlightedMembers = DB::table($memberTable)
            ->leftJoin("{$memberTable} as downline", function ($join) use ($memberTable): void {
                $join->on('downline.member_parent_member_id', '=', "{$memberTable}.member_id")
                    ->where('downline.member_status', '!=', 3);
            })
            ->leftJoin($levelTable, "{$levelTable}.member_level_id", '=', "{$memberTable}.member_member_level_id")
            ->where("{$memberTable}.member_status", '!=', 3)
            ->select([
                "{$memberTable}.member_id as id",
                "{$memberTable}.member_code as code",
                "{$memberTable}.member_name as name",
                "{$levelTable}.member_level_code as level_code",
                "{$levelTable}.member_level_name as level_name",
            ])
            ->selectRaw('COUNT(downline.member_id) as total_direct_downlines')
            ->groupBy([
                "{$memberTable}.member_id",
                "{$memberTable}.member_code",
                "{$memberTable}.member_name",
                "{$levelTable}.member_level_code",
                "{$levelTable}.member_level_name",
            ])
            ->orderByDesc('total_direct_downlines')
            ->orderByDesc("{$memberTable}.member_id")
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'level' => ['code' => $row->level_code, 'name' => $row->level_name],
                'total_direct_downlines' => (int) $row->total_direct_downlines,
            ]);

        $recentOrders = DB::table($trxTable)
            ->leftJoin("{$memberTable} as buyer_member", function ($join) use ($trxTable): void {
                $join->on('buyer_member.member_id', '=', "{$trxTable}.trx_buyer_id")
                    ->whereIn("{$trxTable}.trx_buyer_type", ['distributor', 'agent', 'reseller']);
            })
            ->leftJoin('customer', function ($join) use ($trxTable): void {
                $join->on('customer.customer_id', '=', "{$trxTable}.trx_buyer_id")
                    ->where("{$trxTable}.trx_buyer_type", 'customer');
            })
            ->whereBetween("{$trxTable}.trx_datetime", [$dateFrom, $dateTo])
            ->orderByDesc("{$trxTable}.trx_datetime")
            ->orderByDesc("{$trxTable}.trx_id")
            ->limit(10)
            ->get([
                "{$trxTable}.trx_id as id",
                "{$trxTable}.trx_code as code",
                "{$trxTable}.trx_buyer_type as buyer_type",
                "{$trxTable}.trx_grand_total_nett_price as total",
                "{$trxTable}.trx_status as status",
                "{$trxTable}.trx_datetime as ordered_at",
                DB::raw('COALESCE(buyer_member.member_name, customer.customer_name) as buyer_name'),
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'buyer' => [
                    'type' => $row->buyer_type,
                    'name' => $row->buyer_name,
                ],
                'total' => (int) $row->total,
                'ordered_at' => $row->ordered_at,
                'status' => [
                    'code' => $row->status,
                    'label' => $this->orderStatusLabel($row->status),
                ],
            ]);

        $stockAlerts = DB::table($productTable)
            ->leftJoin($stockTable, "{$stockTable}.warehouse_stock_product_id", '=', "{$productTable}.product_id")
            ->where("{$productTable}.product_is_active", 1)
            ->where("{$productTable}.product_is_deleted", 0)
            ->select([
                "{$productTable}.product_id as id",
                "{$productTable}.product_code as code",
                "{$productTable}.product_name as name",
            ])
            ->selectRaw("COALESCE(SUM({$stockTable}.warehouse_stock_balance), 0) as balance")
            ->groupBy([
                "{$productTable}.product_id",
                "{$productTable}.product_code",
                "{$productTable}.product_name",
            ])
            ->havingRaw("COALESCE(SUM({$stockTable}.warehouse_stock_balance), 0) <= ?", [self::LOW_STOCK_MAXIMUM])
            ->orderBy('balance')
            ->orderBy("{$productTable}.product_name")
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'balance' => (int) $row->balance,
            ]);

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'summary' => $summary,
            'order_statuses' => $orderStatuses,
            'sales_trend' => $salesTrend,
            'member_growth' => $memberGrowth,
            'level_distribution' => $levelDistribution,
            'highlighted_members' => $highlightedMembers,
            'recent_orders' => $recentOrders,
            'stock_alerts' => $stockAlerts,
        ];
    }

    private function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'waiting_stock_screening' => 'Menunggu Screening Stok',
            'waiting_payment' => 'Menunggu Pembayaran',
            'waiting_payment_approval' => 'Menunggu Verifikasi Pembayaran',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'reship_required' => 'Perlu Dikirim Ulang',
            'ready_to_pickup' => 'Siap Diambil',
            'received' => 'Siap Diterima',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'rejected' => 'Ditolak',
            default => $status,
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
