<?php

namespace App\Services\Inventory;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\MemberAccount;
use App\Models\MemberStock;
use App\Models\MemberStockAdjustment;
use App\Models\MemberStockAdjustmentDetail;
use App\Models\MemberStockLog;
use App\Models\Notification;
use App\Models\Product;
use App\Services\Document\DocumentCodeService;
use Illuminate\Support\Facades\DB;

class MemberInventoryService
{
    public function __construct(private readonly DocumentCodeService $documentCodeService) {}

    /** @param array<string, mixed> $params */
    public function memberStocks(array $params, int $memberId): array
    {
        return DataTable::select([
            'member_stock.member_stock_id as id',
            'member_stock.member_stock_member_id as member_id',
            'member_stock.member_stock_product_id as product_id',
            'product.product_code as product_code',
            'product.product_name as product_name',
            'product.product_product_category_id as category_id',
            'product_category.product_category_name as category_name',
            'product.product_unit as unit',
            'member_stock.member_stock_balance as balance',
            'member_stock.member_stock_transfer_in as transfer_in',
            'member_stock.member_stock_transfer_out as transfer_out',
        ])
            ->from('member_stock')
            ->leftJoin('product', 'product.product_id = member_stock.member_stock_product_id')
            ->leftJoin('product_category', 'product_category.product_category_id = product.product_product_category_id')
            ->where('member_stock.member_stock_member_id', $memberId)
            ->where('product.product_is_deleted', 0)
            ->search(['product_code', 'product_name', 'category_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    /** @param array<string, mixed> $params */
    public function memberStockMutations(array $params, int $memberId): array
    {
        return DataTable::select([
            'member_stock_log.member_stock_log_id as id',
            'member_stock_log.member_stock_log_member_id as member_id',
            'member_stock_log.member_stock_log_product_id as product_id',
            'product.product_code as product_code',
            'product.product_name as product_name',
            'member_stock_log.member_stock_log_type as type',
            'member_stock_log.member_stock_log_quantity as quantity',
            'member_stock_log.member_stock_log_unit_price as unit_price',
            'member_stock_log.member_stock_log_balance as balance',
            'member_stock_log.member_stock_log_note as note',
            'member_stock_log.member_stock_log_datetime as datetime',
        ])
            ->from('member_stock_log')
            ->leftJoin('product', 'product.product_id = member_stock_log.member_stock_log_product_id')
            ->where('member_stock_log.member_stock_log_member_id', $memberId)
            ->search(['product_code', 'product_name', 'note'])
            ->defaultSort('-datetime,-id')
            ->get($params);
    }

    public function notifications(int $memberId): array
    {
        // Expiring products (next 90 days)
        $expiring = DB::table('goods_receive_detail')
            ->join('goods_receive', 'goods_receive.goods_receive_id', '=', 'goods_receive_detail.goods_receive_detail_receive_id')
            ->join('product', 'product.product_id', '=', 'goods_receive_detail.goods_receive_detail_product_id')
            ->join('member_stock', function ($join) use ($memberId) {
                $join->on('member_stock.member_stock_product_id', '=', 'product.product_id')
                    ->where('member_stock.member_stock_member_id', '=', $memberId);
            })
            ->where('goods_receive.goods_receive_buyer_id', $memberId)
            ->where('goods_receive_detail.goods_receive_detail_expire_date', '>=', now()->toDateString())
            ->where('goods_receive_detail.goods_receive_detail_expire_date', '<=', now()->addDays(90)->toDateString())
            ->where('member_stock.member_stock_balance', '>', 0)
            ->select([
                'product.product_id',
                'product.product_name',
                'product.product_code',
                'goods_receive_detail.goods_receive_detail_expire_date as expire_date',
            ])
            ->distinct()
            ->orderBy('expire_date', 'asc')
            ->get();

        // Replenishment needed (balance = 0)
        $replenishment = DB::table('member_stock')
            ->join('product', 'product.product_id', '=', 'member_stock.member_stock_product_id')
            ->where('member_stock.member_stock_member_id', $memberId)
            ->where('member_stock.member_stock_balance', '<=', 0)
            ->select([
                'product.product_id',
                'product.product_name',
                'product.product_code',
                'member_stock.member_stock_balance as current_stock',
            ])
            ->get();

        $transactions = Notification::query()
            ->where('notification_user_type', 'member')
            ->where('notification_user_id', $memberId)
            ->latest('notification_created_datetime')
            ->latest('notification_id')
            ->limit(100)
            ->get()
            ->map(fn (Notification $notification): array => [
                'id' => (int) $notification->getKey(),
                'title' => $notification->notification_title,
                'content' => $notification->notification_content,
                'category' => $notification->notification_category,
                'is_read' => (bool) $notification->notification_is_read,
                'read_at' => $notification->notification_read_datetime,
                'created_at' => $notification->notification_created_datetime,
                'reference' => $this->notificationReference($notification),
                'action' => $this->notificationAction($notification),
            ])
            ->values();

        return [
            'unread_count' => $this->unreadNotificationCount($memberId),
            'transaction_notifications' => $transactions,
            'expiring_products' => $expiring,
            'replenishment_needed' => $replenishment,
        ];
    }

    public function unreadNotificationCount(int $memberId): int
    {
        return Notification::query()
            ->where('notification_user_type', 'member')
            ->where('notification_user_id', $memberId)
            ->where('notification_is_read', 0)
            ->count();
    }

    public function markNotificationRead(int $memberId, Notification $notification): Notification
    {
        $ownedNotification = Notification::query()
            ->whereKey($notification->getKey())
            ->where('notification_user_type', 'member')
            ->where('notification_user_id', $memberId)
            ->firstOrFail();

        if (! $ownedNotification->notification_is_read) {
            $ownedNotification->update([
                'notification_is_read' => 1,
                'notification_read_datetime' => now(),
            ]);
        }

        return $ownedNotification->refresh();
    }

    /** @return array{table: string, id: int}|null */
    private function notificationReference(Notification $notification): ?array
    {
        $table = trim((string) $notification->notification_ref_table);
        $id = (int) $notification->notification_ref_id;

        if ($table === '' || $id < 1) {
            return null;
        }

        return [
            'table' => $table,
            'id' => $id,
        ];
    }

    /** @return array<string, int|string>|null */
    private function notificationAction(Notification $notification): ?array
    {
        $reference = $this->notificationReference($notification);

        if ($reference === null) {
            return null;
        }

        return match ($reference['table']) {
            'trx', 'trx_sale' => [
                'type' => 'open_sale_order',
                'transaction_id' => $reference['id'],
            ],
            'trx_purchase' => [
                'type' => 'open_purchase_order',
                'transaction_id' => $reference['id'],
            ],
            'member_stock' => [
                'type' => 'open_stock',
                'stock_id' => $reference['id'],
            ],
            'return' => [
                'type' => 'open_return',
                'return_id' => $reference['id'],
            ],
            default => null,
        };
    }

    /** @param array<string, mixed> $params */
    public function stockAdjustments(array $params, int $memberId): array
    {
        $adjustmentTable = (new MemberStockAdjustment)->getTable();
        $detailTable = (new MemberStockAdjustmentDetail)->getTable();

        $query = DataTable::select([
            "{$adjustmentTable}.stock_adjustment_id as id",
            "{$adjustmentTable}.stock_adjustment_code as code",
            "{$adjustmentTable}.stock_adjustment_note as note",
            "{$adjustmentTable}.stock_adjustment_datetime as happened_at",
        ])
            ->selectRaw("(SELECT COUNT(*) FROM {$detailTable} WHERE {$detailTable}.stock_adjustment_detail_stock_adjustment_id = {$adjustmentTable}.stock_adjustment_id) as total_items")
            ->selectRaw("(SELECT COALESCE(SUM({$detailTable}.stock_adjustment_detail_qty), 0) FROM {$detailTable} WHERE {$detailTable}.stock_adjustment_detail_stock_adjustment_id = {$adjustmentTable}.stock_adjustment_id) as total_quantity")
            ->from($adjustmentTable)
            ->where("{$adjustmentTable}.stock_adjustment_member_id", $memberId)
            ->search(['code', 'note'])
            ->defaultSort('-id');

        if (isset($params['start_date']) || isset($params['end_date'])) {
            $query->whereBetween("{$adjustmentTable}.stock_adjustment_datetime", [
                ($params['start_date'] ?? '1970-01-01').' 00:00:00',
                ($params['end_date'] ?? '2999-12-31').' 23:59:59',
            ]);
        }

        return $query->get($params);
    }

    public function stockAdjustment(MemberStockAdjustment $adjustment): MemberStockAdjustment
    {
        return $adjustment->load(['details.product']);
    }

    /** @param array<string, mixed> $data */
    public function createStockAdjustment(
        MemberAccount $account,
        array $data,
    ): MemberStockAdjustment {
        return DB::transaction(function () use ($account, $data): MemberStockAdjustment {
            $memberId = (int) $account->member_account_member_id;
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $memberId)
                ->where('member_stock_product_id', $data['product_id'])
                ->lockForUpdate()
                ->first();

            if (! $stock || (int) $stock->member_stock_balance < (int) $data['qty']) {
                throw new ProcessException('Saldo stok tidak mencukupi untuk penyesuaian.');
            }

            $product = Product::query()
                ->whereKey($data['product_id'])
                ->where('product_is_deleted', 0)
                ->firstOrFail();
            $quantity = (int) $data['qty'];
            $newBalance = (int) $stock->member_stock_balance - $quantity;
            $adjustment = MemberStockAdjustment::query()->create([
                'stock_adjustment_administrator_id' => 0,
                'stock_adjustment_member_id' => $memberId,
                'stock_adjustment_code' => $this->documentCodeService->next(
                    DocumentCodeService::ADJUSTMENT,
                ),
                'stock_adjustment_note' => $data['reason'],
                'stock_adjustment_datetime' => now(),
            ]);

            $stock->update(['member_stock_balance' => $newBalance]);

            MemberStockAdjustmentDetail::query()->create([
                'stock_adjustment_detail_stock_adjustment_id' => $adjustment->getKey(),
                'stock_adjustment_detail_stock_member_id' => $stock->getKey(),
                'stock_adjustment_detail_product_id' => $product->getKey(),
                'stock_adjustment_detail_type' => 'out',
                'stock_adjustment_detail_qty' => $quantity,
                'stock_adjustment_detail_current_price' => (int) $product->product_customer_price,
                'stock_adjustment_detail_note' => $data['reason'],
            ]);

            MemberStockLog::query()->create([
                'member_stock_log_member_id' => $memberId,
                'member_stock_log_product_id' => $product->getKey(),
                'member_stock_log_type' => 'out',
                'member_stock_log_quantity' => $quantity,
                'member_stock_log_unit_price' => (int) $product->product_customer_price,
                'member_stock_log_balance' => $newBalance,
                'member_stock_log_note' => "Penyesuaian {$adjustment->stock_adjustment_code}: {$data['reason']}",
                'member_stock_log_datetime' => now(),
            ]);

            return $this->stockAdjustment($adjustment);
        });
    }
}
