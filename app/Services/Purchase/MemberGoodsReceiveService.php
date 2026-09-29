<?php

namespace App\Services\Purchase;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\GoodsReceive;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\Warehouse;
use App\Services\Document\DocumentCodeService;
use App\Services\Shipping\PickupVerificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberGoodsReceiveService
{
    public function __construct(
        private readonly MemberPurchaseService $purchaseService,
        private readonly DocumentCodeService $documentCodeService,
        private readonly PickupVerificationService $pickupVerificationService,
        private readonly PreorderChainService $preorderChainService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination: array<string, mixed>}
     */
    public function goodsReceives(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $trxTable = (new Trx)->getTable();
        $detailTable = (new TrxDetail)->getTable();
        $receiveTable = (new GoodsReceive)->getTable();
        $memberTable = (new Member)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $pickupTable = (new ShippingPickup)->getTable();
        $pickupStatusTable = (new ShippingPickupStatus)->getTable();
        $detailSummary = DB::table($detailTable)
            ->select("{$detailTable}.trx_detail_trx_id")
            ->selectRaw('COUNT(*) AS product_count')
            ->selectRaw("SUM({$detailTable}.trx_detail_qty) AS total_quantity")
            ->groupBy("{$detailTable}.trx_detail_trx_id");
        $latestPickupStatus = DB::table($pickupStatusTable)
            ->select('shipping_pickup_status_shipping_pickup_id')
            ->selectRaw('MAX(shipping_pickup_status_id) AS latest_status_id')
            ->groupBy('shipping_pickup_status_shipping_pickup_id');
        $pickupSummary = DB::table("{$pickupTable} as list_pickup")
            ->leftJoinSub(
                $latestPickupStatus,
                'latest_pickup_status',
                'latest_pickup_status.shipping_pickup_status_shipping_pickup_id',
                '=',
                'list_pickup.shipping_pickup_id',
            )
            ->leftJoin(
                "{$pickupStatusTable} as current_pickup_status",
                'current_pickup_status.shipping_pickup_status_id',
                '=',
                'latest_pickup_status.latest_status_id',
            )
            ->where('list_pickup.shipping_pickup_ref_type', 'trx')
            ->select('list_pickup.shipping_pickup_ref_id as transaction_id')
            ->selectRaw('current_pickup_status.shipping_pickup_status_value AS pickup_status');

        $page = DataTable::select([
            "{$trxTable}.trx_id as id",
            "{$trxTable}.trx_code as code",
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            "{$trxTable}.trx_shipping_method as shipping_method",
            "{$trxTable}.trx_is_preorder as is_preorder",
            'seller_member.member_code as seller_code',
            'seller_member.member_name as seller_name',
            "{$warehouseTable}.warehouse_name as warehouse_name",
            "{$trxTable}.trx_status as order_status",
            "{$trxTable}.trx_status_datetime as ready_at",
            "{$receiveTable}.goods_receive_id as receive_id",
            "{$receiveTable}.goods_receive_number as receive_number",
            "{$receiveTable}.goods_receive_delivery_note_number as delivery_note_number",
            DB::raw("COALESCE(
                (SELECT shipping_courier_express_delivery_note_number FROM shipping_courier_express
                    WHERE shipping_courier_express_ref_type = 'trx'
                    AND shipping_courier_express_ref_id = {$trxTable}.trx_id
                    ORDER BY shipping_courier_express_id DESC LIMIT 1),
                (SELECT shipping_courier_instant_delivery_note_number FROM shipping_courier_instant
                    WHERE shipping_courier_instant_ref_type = 'trx'
                    AND shipping_courier_instant_ref_id = {$trxTable}.trx_id
                    ORDER BY shipping_courier_instant_id DESC LIMIT 1),
                (SELECT shipping_courier_manual_delivery_note_number FROM shipping_courier_manual
                    WHERE shipping_courier_manual_ref_type = 'trx'
                    AND shipping_courier_manual_ref_id = {$trxTable}.trx_id
                    ORDER BY shipping_courier_manual_id DESC LIMIT 1),
                (SELECT shipping_pickup_delivery_note_number FROM shipping_pickup
                    WHERE shipping_pickup_ref_type = 'trx'
                    AND shipping_pickup_ref_id = {$trxTable}.trx_id
                    ORDER BY shipping_pickup_id DESC LIMIT 1)
            ) AS shipping_delivery_note_number"),
            "{$receiveTable}.goods_receive_status as receive_status",
            'detail_summary.product_count as product_count',
            'detail_summary.total_quantity as total_quantity',
            'pickup_summary.pickup_status as pickup_status',
            DB::raw("(
                SELECT shipping_courier_express_status_value
                FROM shipping_courier_express_status
                INNER JOIN shipping_courier_express
                    ON shipping_courier_express_id = shipping_courier_express_status_shipping_courier_express_id
                WHERE shipping_courier_express_ref_type = 'trx'
                    AND shipping_courier_express_ref_id = {$trxTable}.trx_id
                ORDER BY shipping_courier_express_status_id DESC LIMIT 1
            ) AS express_status"),
            DB::raw("(
                SELECT shipping_courier_instant_status_value
                FROM shipping_courier_instant_status
                INNER JOIN shipping_courier_instant
                    ON shipping_courier_instant_id = shipping_courier_instant_status_shipping_courier_instant_id
                WHERE shipping_courier_instant_ref_type = 'trx'
                    AND shipping_courier_instant_ref_id = {$trxTable}.trx_id
                ORDER BY shipping_courier_instant_status_id DESC LIMIT 1
            ) AS instant_status"),
        ])
            ->selectRaw(
                "COALESCE({$receiveTable}.goods_receive_status_datetime, {$receiveTable}.goods_receive_created_datetime) AS received_at"
            )
            ->from($trxTable)
            ->leftJoin("{$memberTable} as seller_member", "seller_member.member_id = {$trxTable}.trx_seller_id")
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$trxTable}.trx_seller_id")
            ->leftJoin($receiveTable, "{$receiveTable}.goods_receive_trx_id = {$trxTable}.trx_id")
            ->leftJoinSub($detailSummary, 'detail_summary', "detail_summary.trx_detail_trx_id = {$trxTable}.trx_id")
            ->leftJoinSub($pickupSummary, 'pickup_summary', "pickup_summary.transaction_id = {$trxTable}.trx_id")
            ->where("{$trxTable}.trx_buyer_id", $member->getKey())
            ->whereIn("{$trxTable}.trx_buyer_type", ['distributor', 'agent', 'reseller'])
            ->where("{$trxTable}.trx_type", 'stock')
            ->where("{$trxTable}.trx_parent_trx_id", 0)
            ->whereRaw("(
                {$trxTable}.trx_status IN ('received', 'completed')
                OR (
                    {$trxTable}.trx_status = 'shipped'
                    AND {$trxTable}.trx_is_preorder = 0
                    AND (
                        {$trxTable}.trx_shipping_method = 'courier_manual'
                        OR (
                            {$trxTable}.trx_shipping_method = 'pickup'
                            AND pickup_summary.pickup_status = 'picked_up'
                        )
                        OR (
                            {$trxTable}.trx_shipping_method = 'courier_express'
                            AND EXISTS (
                                SELECT 1
                                FROM shipping_courier_express ready_express
                                INNER JOIN shipping_courier_express_status ready_express_status
                                    ON ready_express_status.shipping_courier_express_status_shipping_courier_express_id
                                        = ready_express.shipping_courier_express_id
                                WHERE ready_express.shipping_courier_express_ref_type = 'trx'
                                    AND ready_express.shipping_courier_express_ref_id = {$trxTable}.trx_id
                                    AND ready_express_status.shipping_courier_express_status_value
                                        IN ('finished_packages', 'completed')
                            )
                        )
                        OR (
                            {$trxTable}.trx_shipping_method = 'courier_instant'
                            AND EXISTS (
                                SELECT 1
                                FROM shipping_courier_instant ready_instant
                                INNER JOIN shipping_courier_instant_status ready_instant_status
                                    ON ready_instant_status.shipping_courier_instant_status_shipping_courier_instant_id
                                        = ready_instant.shipping_courier_instant_id
                                WHERE ready_instant.shipping_courier_instant_ref_type = 'trx'
                                    AND ready_instant.shipping_courier_instant_ref_id = {$trxTable}.trx_id
                                    AND ready_instant_status.shipping_courier_instant_status_value
                                        IN ('finished_packages', 'completed')
                            )
                        )
                    )
                )
            )")
            ->search(['code', 'seller_code', 'seller_name', 'warehouse_name', 'receive_number'])
            ->defaultSort('-id')
            ->get($params);

        foreach ($page['results'] as $row) {
            if (! (bool) $row->is_preorder) {
                continue;
            }

            $root = Trx::query()->findOrFail((int) $row->id);
            $chain = $this->preorderChainService
                ->loadVisiblePurchaseChain($root)
                ->getRelation('preorderChain');
            /** @var Trx|null $fulfillment */
            $fulfillment = $chain->last();
            if (! $fulfillment) {
                continue;
            }

            $seller = $fulfillment->trx_seller_type === 'warehouse'
                ? $fulfillment->sellerWarehouse
                : $fulfillment->seller;
            $row->seller_type = $fulfillment->trx_seller_type;
            $row->seller_id = $fulfillment->trx_seller_id;
            $row->seller_code = $seller?->member_code;
            $row->seller_name = $seller?->member_name;
            $row->warehouse_name = $seller?->warehouse_name;
            $row->shipping_method = $fulfillment->trx_shipping_method;
            $row->pickup_status = $fulfillment->shippingPickup?->latestStatus
                ?->shipping_pickup_status_value;
            $row->express_status = $fulfillment->shippingExpress?->latestStatus
                ?->shipping_courier_express_status_value;
            $row->instant_status = $fulfillment->shippingInstant?->latestStatus
                ?->shipping_courier_instant_status_value;
        }

        return $page;
    }

    public function goodsReceive(MemberAccount $account, Trx $trx): Trx
    {
        return $this->purchaseService->order($account, $trx);
    }

    /** @param array<string, mixed> $data */
    public function confirm(MemberAccount $account, Trx $trx, array $data): Trx
    {
        $member = $this->member($account);

        return DB::transaction(function () use ($account, $member, $trx, $data): Trx {
            $lockedTrx = Trx::query()
                ->with([
                    'details',
                    'shippingExpress.details',
                    'shippingExpress.latestStatus',
                    'shippingInstant.details',
                    'shippingInstant.latestStatus',
                    'shippingManual.details',
                    'shippingPickup.details',
                    'shippingPickup.latestStatus',
                ])
                ->whereKey($trx->getKey())
                ->where('trx_buyer_id', $member->getKey())
                ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
                ->where('trx_type', 'stock')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTrx->trx_status !== 'received') {
                if ($lockedTrx->trx_status === 'shipped') {
                    throw new ProcessException('Pengiriman belum selesai dan belum dapat diterima.');
                }

                throw new ProcessException('Pesanan belum siap diterima atau sudah pernah diterima.');
            }
            $lockedTrx = $this->preorderChainService->loadVisiblePurchaseChain($lockedTrx);
            /** @var Trx $shippingTrx */
            $shippingTrx = $lockedTrx->trx_is_preorder
                ? ($lockedTrx->preorderChain->last() ?? $lockedTrx)
                : $lockedTrx;
            $now = now();
            $shipment = match ($shippingTrx->trx_shipping_method) {
                'courier_express' => $shippingTrx->shippingExpress,
                'courier_instant' => $shippingTrx->shippingInstant,
                'courier_manual' => $shippingTrx->shippingManual,
                'pickup' => $shippingTrx->shippingPickup,
                default => null,
            };
            if (! $shipment) {
                throw new ProcessException('Data pengiriman pesanan tidak ditemukan.');
            }
            $this->ensureShipmentReady($shippingTrx);
            $shippingDeliveryNote = match ($shippingTrx->trx_shipping_method) {
                'courier_express' => $shippingTrx->shippingExpress?->shipping_courier_express_delivery_note_number,
                'courier_instant' => $shippingTrx->shippingInstant?->shipping_courier_instant_delivery_note_number,
                'courier_manual' => $shippingTrx->shippingManual?->shipping_courier_manual_delivery_note_number,
                'pickup' => $shippingTrx->shippingPickup?->shipping_pickup_delivery_note_number,
                default => null,
            };
            $submittedDeliveryNote = $data['delivery_note_number'] ?? null;
            $mustVerifyDeliveryNote = $shippingTrx->trx_seller_type === 'warehouse'
                || filled($submittedDeliveryNote);
            if ($mustVerifyDeliveryNote
                && (blank($shippingDeliveryNote) || $shippingDeliveryNote !== $submittedDeliveryNote)) {
                throw new ProcessException(
                    'Nomor surat jalan tidak sesuai dengan pengiriman yang harus diterima.'
                );
            }
            if (GoodsReceive::query()->where('goods_receive_trx_id', $lockedTrx->getKey())->exists()) {
                throw new ProcessException('Penerimaan barang untuk pesanan ini sudah tercatat.');
            }

            $orderedQuantities = $lockedTrx->details
                ->groupBy('trx_detail_product_id')
                ->map(fn (Collection $details): int => (int) $details->sum('trx_detail_qty'))
                ->sortKeys();
            $receivedItems = $shipment->details->map(fn ($item): array => [
                'product_id' => (int) $item->shipping_detail_product_id,
                'quantity' => (int) $item->shipping_detail_qty,
                'batch_number' => $item->shipping_detail_batch_number,
                'expiry_date' => $item->shipping_detail_expire_date,
            ]);
            if ($receivedItems->isEmpty()) {
                throw new ProcessException('Rincian batch pengiriman tidak ditemukan.');
            }
            $receivedQuantities = $receivedItems
                ->groupBy('product_id')
                ->map(fn (Collection $items): int => (int) $items->sum('quantity'))
                ->sortKeys();

            if ($orderedQuantities->all() !== $receivedQuantities->all()) {
                throw new ProcessException(
                    'Seluruh jumlah produk harus diterima sesuai pesanan. Produk boleh dipecah ke beberapa batch.'
                );
            }

            $goodsReceive = GoodsReceive::query()->create([
                'goods_receive_number' => $this->documentCodeService->next(
                    DocumentCodeService::GOODS_RECEIVE,
                ),
                'goods_receive_trx_id' => $lockedTrx->getKey(),
                'goods_receive_buyer_type' => $lockedTrx->trx_buyer_type,
                'goods_receive_buyer_id' => $member->getKey(),
                'goods_receive_seller_type' => $shippingTrx->trx_seller_type,
                'goods_receive_seller_id' => $shippingTrx->trx_seller_id,
                'goods_receive_delivery_note_number' => $shippingDeliveryNote,
                'goods_receive_faktur_number' => null,
                'goods_receive_status' => 'completed',
                'goods_receive_status_datetime' => $now,
                'goods_receive_created_datetime' => $now,
            ]);
            $goodsReceive->details()->createMany($receivedItems->map(
                fn (array $item): array => [
                    'goods_receive_detail_product_id' => $item['product_id'],
                    'goods_receive_detail_batch_number' => $item['batch_number'],
                    'goods_receive_detail_expire_date' => $item['expiry_date'],
                    'goods_receive_detail_qty' => $item['quantity'],
                    'goods_receive_detail_created_datetime' => $now,
                ]
            )->all());

            foreach ($receivedQuantities as $productId => $quantity) {
                $stock = MemberStock::query()
                    ->where('member_stock_member_id', $member->getKey())
                    ->where('member_stock_product_id', $productId)
                    ->lockForUpdate()
                    ->first();
                if (! $stock) {
                    $stock = MemberStock::query()->create([
                        'member_stock_member_id' => $member->getKey(),
                        'member_stock_product_id' => $productId,
                        'member_stock_balance' => 0,
                        'member_stock_transfer_in' => 0,
                        'member_stock_transfer_out' => 0,
                    ]);
                }

                $newBalance = (int) $stock->member_stock_balance + $quantity;
                $stock->update([
                    'member_stock_balance' => $newBalance,
                    'member_stock_transfer_in' => max(
                        0,
                        (int) $stock->member_stock_transfer_in - $quantity,
                    ),
                ]);
                $transactionDetail = $lockedTrx->details->firstWhere(
                    'trx_detail_product_id',
                    $productId
                );
                MemberStockLog::query()->create([
                    'member_stock_log_member_id' => $member->getKey(),
                    'member_stock_log_product_id' => $productId,
                    'member_stock_log_type' => 'in',
                    'member_stock_log_quantity' => $quantity,
                    'member_stock_log_unit_price' => $transactionDetail?->trx_detail_nett_price ?? 0,
                    'member_stock_log_balance' => $newBalance,
                    'member_stock_log_note' => "Penerimaan {$goodsReceive->goods_receive_number} dari pesanan {$lockedTrx->trx_code}",
                    'member_stock_log_datetime' => $now,
                ]);
            }

            $lockedTrx->update(['trx_status' => 'completed', 'trx_status_datetime' => $now]);
            $this->preorderChainService->markChainCompleted($lockedTrx);
            if ($lockedTrx->trx_shipping_method === 'pickup') {
                $this->pickupVerificationService->markCompleted($lockedTrx, $now);
            }

            return $this->purchaseService->order($account, $lockedTrx->fresh());
        });
    }

    private function ensureShipmentReady(Trx $trx): void
    {
        if ($trx->trx_is_preorder && $trx->trx_status === 'received') {
            return;
        }

        $ready = match ($trx->trx_shipping_method) {
            'courier_express' => in_array(
                $trx->shippingExpress?->latestStatus?->shipping_courier_express_status_value,
                ['finished_packages', 'completed'],
                true,
            ),
            'courier_instant' => in_array(
                $trx->shippingInstant?->latestStatus?->shipping_courier_instant_status_value,
                ['finished_packages', 'completed'],
                true,
            ),
            'courier_manual' => true,
            'pickup' => $trx->shippingPickup?->latestStatus?->shipping_pickup_status_value
                === 'picked_up',
            default => false,
        };

        if (! $ready) {
            throw new ProcessException('Pengiriman belum selesai dan belum dapat diterima.');
        }
    }

    private function member(MemberAccount $account): Member
    {
        $account->loadMissing('member');
        $member = $account->member;

        if (! $member || (int) $member->member_status !== 1) {
            throw new ProcessException('Data mitra aktif tidak ditemukan.', 403);
        }

        return $member;
    }
}
