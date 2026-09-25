<?php

namespace App\Services\Purchase;

use App\Exceptions\ProcessException;
use App\Models\BankCompany;
use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\RewardStockist;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierManual;
use App\Models\ShippingDetail;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLog;
use App\Services\Inventory\StockAllocationService;
use App\Services\Reward\MemberPointService;
use App\Services\Transaction\TransactionCodeService;
use App\Support\BusinessConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PreorderChainService
{
    public function __construct(
        private readonly TransactionCodeService $transactionCodeService,
        private readonly StockAllocationService $stockAllocationService,
        private readonly MemberPointService $memberPointService,
    ) {}

    /**
     * @param  Collection<int, array{product_id: int, quantity: int}>  $items
     */
    public function assertTerminalStockAvailable(Collection $items, ?array $directSeller = null): void
    {
        $this->terminalSeller($items, $directSeller);
    }

    public function loadVisibleSalesChain(Trx $trx): Trx
    {
        return $this->loadCompleteChain($trx);
    }

    public function loadVisiblePurchaseChain(Trx $trx): Trx
    {
        return $this->loadCompleteChain($trx);
    }

    private function loadCompleteChain(Trx $trx): Trx
    {
        if (! $trx->trx_is_preorder) {
            $trx->setRelation('preorderChain', collect());
            $trx->setRelation('preorderCurrentTransaction', $trx);
            $trx->setRelation('preorderTrackingTransaction', $trx);

            return $trx;
        }

        $rootId = (int) $trx->getKey();
        $parentId = (int) $trx->trx_parent_trx_id;
        $visitedIds = [$rootId => true];

        while ($parentId > 0 && ! isset($visitedIds[$parentId])) {
            $visitedIds[$parentId] = true;
            $parent = Trx::query()
                ->select(['trx_id', 'trx_parent_trx_id'])
                ->find($parentId);
            if (! $parent) {
                break;
            }

            $rootId = $parentId;
            $parentId = (int) $parent->trx_parent_trx_id;
        }

        $transactionDepths = [$rootId => 0];
        $parentIds = [$rootId];
        $depth = 1;

        while ($parentIds !== []) {
            $children = Trx::query()
                ->whereIn('trx_parent_trx_id', $parentIds)
                ->where('trx_is_preorder', true)
                ->orderBy('trx_id')
                ->pluck('trx_parent_trx_id', 'trx_id');
            $newIds = $children->keys()
                ->map(fn (mixed $id): int => (int) $id)
                ->reject(fn (int $id): bool => isset($transactionDepths[$id]))
                ->values();

            foreach ($newIds as $id) {
                $transactionDepths[$id] = $depth;
            }

            $parentIds = $newIds->all();
            $depth++;
        }

        $transactions = Trx::query()
            ->whereIn('trx_id', array_keys($transactionDepths))
            ->with([
                'buyer.level',
                'buyerCustomer',
                'details',
                'paymentTransfer',
                'seller.level',
                'sellerWarehouse',
                'shippingExpress.details',
                'shippingExpress.latestStatus',
                'shippingInstant.details',
                'shippingInstant.latestStatus',
                'shippingManual.details',
                'shippingPickup.details',
                'shippingPickup.latestStatus',
            ])
            ->get()
            ->sort(function (Trx $first, Trx $second) use ($transactionDepths): int {
                return [
                    $transactionDepths[$first->getKey()],
                    (int) $first->getKey(),
                ] <=> [
                    $transactionDepths[$second->getKey()],
                    (int) $second->getKey(),
                ];
            })
            ->values();

        $trx->setRelation('preorderChain', $transactions);
        $trx->setRelation('preorderExpectedChain', $this->expectedChain($transactions, $rootId));
        $trx->setRelation('preorderCurrentTransaction', $trx);
        $trx->setRelation('preorderTrackingTransaction', $this->trackingOrderFromLoadedChain($trx));

        return $trx;
    }

    public function trackingOrder(Trx $trx): Trx
    {
        if (! $trx->relationLoaded('preorderTrackingTransaction')) {
            $this->loadCompleteChain($trx);
        }

        $trackingOrder = $trx->getRelation('preorderTrackingTransaction');

        return $trackingOrder instanceof Trx ? $trackingOrder : $trx;
    }

    private function trackingOrderFromLoadedChain(Trx $trx): Trx
    {
        if (! $trx->trx_is_preorder || ! $trx->relationLoaded('preorderChain')) {
            return $trx;
        }

        $providerOrders = $trx->preorderChain
            ->reverse()
            ->filter(fn (Trx $order): bool => match ($order->trx_shipping_method) {
                'courier_express' => $order->shippingExpress !== null,
                'courier_instant' => $order->shippingInstant !== null,
                default => false,
            })
            ->values();
        /** @var Trx|null $terminalOrder */
        $terminalOrder = $providerOrders->first();
        if (! $terminalOrder) {
            return $trx;
        }
        if ($this->hasAvailableTracking($terminalOrder)) {
            return $terminalOrder;
        }

        $terminalOrderId = $this->providerOrderId($terminalOrder);
        /** @var Trx|null $legacyTrackingOrder */
        $legacyTrackingOrder = $providerOrders->first(
            fn (Trx $order): bool => $this->hasAvailableTracking($order)
                && (blank($terminalOrderId) || $this->providerOrderId($order) === $terminalOrderId),
        );

        return $legacyTrackingOrder ?? $terminalOrder;
    }

    private function hasAvailableTracking(Trx $trx): bool
    {
        return filled($this->providerOrderId($trx)) && filled(match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress?->shipping_courier_express_awb,
            'courier_instant' => $trx->shippingInstant?->shipping_courier_instant_awb,
            default => null,
        });
    }

    private function providerOrderId(Trx $trx): ?string
    {
        $orderId = match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress?->shipping_courier_express_order_id,
            'courier_instant' => $trx->shippingInstant?->shipping_courier_instant_order_id,
            default => null,
        };

        return filled($orderId) ? (string) $orderId : null;
    }

    /**
     * @param  Collection<int, Trx>  $transactions
     * @return Collection<int, array{
     *     sequence: int,
     *     transaction: ?Trx,
     *     buyer: array{type: string, id: int, model: Member|Warehouse|null},
     *     seller: array{type: string, id: int, model: Member|Warehouse|null},
     *     is_projected: bool
     * }>
     */
    private function expectedChain(Collection $transactions, int $rootId): Collection
    {
        if ($transactions->isEmpty()) {
            return collect();
        }

        /** @var Trx $root */
        $root = $transactions->firstWhere('trx_id', $rootId) ?? $transactions->first();
        $buyer = $this->partyFromTransaction($root, 'buyer');
        $seller = $this->partyFromTransaction($root, 'seller');
        $terminalSeller = $this->terminalReservationSeller($root);

        if (! $buyer || ! $seller || ! $terminalSeller) {
            return $transactions->values()->map(fn (Trx $transaction, int $index): array => [
                'sequence' => $index + 1,
                'transaction' => $transaction,
                'buyer' => $this->partyFromTransaction($transaction, 'buyer'),
                'seller' => $this->partyFromTransaction($transaction, 'seller'),
                'is_projected' => false,
            ]);
        }

        $actualTransactions = $transactions->values();
        $expected = collect();
        $sequence = 1;

        while ($buyer && $seller) {
            /** @var Trx|null $transaction */
            $transaction = $actualTransactions->get($sequence - 1);
            $expected->push([
                'sequence' => $sequence,
                'transaction' => $transaction,
                'buyer' => $buyer,
                'seller' => $seller,
                'is_projected' => ! $transaction,
            ]);

            if ($this->sameParty($seller, $terminalSeller) || $seller['model'] instanceof Warehouse) {
                break;
            }

            if (! ($seller['model'] instanceof Member)) {
                break;
            }

            $buyer = $seller;
            $seller = $this->nextProjectedSeller($seller['model']);
            $sequence++;

            if ($sequence > 10) {
                break;
            }
        }

        for ($index = $expected->count(); $index < $actualTransactions->count(); $index++) {
            /** @var Trx $transaction */
            $transaction = $actualTransactions->get($index);
            $expected->push([
                'sequence' => $index + 1,
                'transaction' => $transaction,
                'buyer' => $this->partyFromTransaction($transaction, 'buyer'),
                'seller' => $this->partyFromTransaction($transaction, 'seller'),
                'is_projected' => false,
            ]);
        }

        return $expected->values();
    }

    /**
     * @return array{type: string, id: int, model: Member|Warehouse}|null
     */
    private function terminalReservationSeller(Trx $root): ?array
    {
        $root->loadMissing('details');
        $quantities = $this->detailQuantities($root);
        if ($quantities->isEmpty()) {
            return null;
        }

        $memberId = $this->reservedMemberId($root, $quantities);
        if ($memberId > 0) {
            $member = Member::query()
                ->with('level')
                ->whereKey($memberId)
                ->first();

            return $member ? [
                'type' => $this->memberType($member),
                'id' => (int) $member->getKey(),
                'model' => $member,
            ] : null;
        }

        $warehouseId = $this->reservedWarehouseId($root, $quantities);
        if ($warehouseId <= 0) {
            return null;
        }

        $warehouse = Warehouse::query()
            ->whereKey($warehouseId)
            ->first();

        return $warehouse ? [
            'type' => 'warehouse',
            'id' => (int) $warehouse->getKey(),
            'model' => $warehouse,
        ] : null;
    }

    /** @return Collection<int, int> */
    private function detailQuantities(Trx $trx): Collection
    {
        return $trx->details
            ->groupBy(fn (TrxDetail $detail): int => (int) $detail->trx_detail_product_id)
            ->map(fn (Collection $details): int => $details->sum(
                fn (TrxDetail $detail): int => (int) $detail->trx_detail_qty,
            ));
    }

    /** @param Collection<int, int> $quantities */
    private function reservedMemberId(Trx $root, Collection $quantities): int
    {
        $rows = MemberStockLog::query()
            ->select([
                'member_stock_log_member_id',
                'member_stock_log_product_id',
            ])
            ->selectRaw('SUM(member_stock_log_quantity) as reserved_quantity')
            ->where('member_stock_log_type', 'out')
            ->where('member_stock_log_note', "Pemesanan {$root->trx_code}")
            ->whereIn('member_stock_log_product_id', $quantities->keys())
            ->groupBy('member_stock_log_member_id', 'member_stock_log_product_id')
            ->get()
            ->groupBy('member_stock_log_member_id');

        foreach ($rows as $memberId => $productRows) {
            $isComplete = $quantities->every(function (int $quantity, int $productId) use ($productRows): bool {
                $reserved = (int) ($productRows->firstWhere('member_stock_log_product_id', $productId)
                    ?->reserved_quantity ?? 0);

                return $reserved >= $quantity;
            });

            if ($isComplete) {
                return (int) $memberId;
            }
        }

        return 0;
    }

    /** @param Collection<int, int> $quantities */
    private function reservedWarehouseId(Trx $root, Collection $quantities): int
    {
        $rows = WarehouseStockLog::query()
            ->select([
                'warehouse_stock_log_warehouse_id',
                'warehouse_stock_log_product_id',
            ])
            ->selectRaw('SUM(warehouse_stock_log_quantity) as reserved_quantity')
            ->where('warehouse_stock_log_type', 'out')
            ->where('warehouse_stock_log_note', "Pemesanan {$root->trx_code}")
            ->whereIn('warehouse_stock_log_product_id', $quantities->keys())
            ->groupBy('warehouse_stock_log_warehouse_id', 'warehouse_stock_log_product_id')
            ->get()
            ->groupBy('warehouse_stock_log_warehouse_id');

        foreach ($rows as $warehouseId => $productRows) {
            $isComplete = $quantities->every(function (int $quantity, int $productId) use ($productRows): bool {
                $reserved = (int) ($productRows->firstWhere('warehouse_stock_log_product_id', $productId)
                    ?->reserved_quantity ?? 0);

                return $reserved >= $quantity;
            });

            if ($isComplete) {
                return (int) $warehouseId;
            }
        }

        return 0;
    }

    /**
     * @return array{type: string, id: int, model: Member|Warehouse|null}|null
     */
    private function partyFromTransaction(Trx $trx, string $side): ?array
    {
        $type = (string) $trx->{"trx_{$side}_type"};
        $id = (int) $trx->{"trx_{$side}_id"};

        return [
            'type' => $type,
            'id' => $id,
            'model' => match (true) {
                $side === 'seller' && $type === 'warehouse' => $trx->sellerWarehouse,
                $side === 'seller' => $trx->seller,
                default => $trx->buyer,
            },
        ];
    }

    /**
     * @return array{type: string, id: int, model: Member|Warehouse}|null
     */
    private function nextProjectedSeller(Member $buyer): ?array
    {
        if ($buyer->level?->member_level_code === 'DST') {
            $warehouse = Warehouse::query()
                ->whereKey(1)
                ->where('warehouse_is_active', 1)
                ->first();

            return $warehouse ? ['type' => 'warehouse', 'id' => (int) $warehouse->getKey(), 'model' => $warehouse] : null;
        }

        $seller = Member::query()
            ->with('level')
            ->whereKey($buyer->member_parent_member_id)
            ->where('member_status', 1)
            ->first();

        return $seller ? [
            'type' => $this->memberType($seller),
            'id' => (int) $seller->getKey(),
            'model' => $seller,
        ] : null;
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse|null}  $party
     * @param  array{type: string, id: int, model: Member|Warehouse}  $other
     */
    private function sameParty(array $party, array $other): bool
    {
        return $party['type'] === $other['type'] && (int) $party['id'] === (int) $other['id'];
    }

    /**
     * @param  Collection<int, array{product_id: int, quantity: int}>  $items
     * @param  null|array{type: string, id: int, model: Member|Warehouse}  $directSeller
     * @return array{type: string, id: int, model: Member|Warehouse}
     */
    public function terminalSeller(Collection $items, ?array $directSeller = null): array
    {
        $quantities = $items
            ->groupBy(fn (array $item): int => (int) $item['product_id'])
            ->map(fn (Collection $productItems): int => $productItems->sum(
                fn (array $item): int => (int) $item['quantity'],
            ));

        if ($directSeller && $directSeller['model'] instanceof Member) {
            $member = Member::query()
                ->with('level')
                ->whereKey($directSeller['id'])
                ->where('member_status', 1)
                ->lockForUpdate()
                ->first();

            while ($member) {
                if ($this->memberHasEnoughStock($member, $quantities)) {
                    return [
                        'type' => $this->memberType($member),
                        'id' => (int) $member->getKey(),
                        'model' => $member,
                    ];
                }

                if ((int) $member->member_parent_member_id === 0) {
                    break;
                }

                $member = Member::query()
                    ->with('level')
                    ->whereKey($member->member_parent_member_id)
                    ->where('member_status', 1)
                    ->lockForUpdate()
                    ->first();
            }
        }

        $warehouse = Warehouse::query()
            ->whereKey(1)
            ->where('warehouse_is_active', 1)
            ->lockForUpdate()
            ->first();
        if (! $warehouse) {
            throw new ProcessException('Gudang aktif untuk memenuhi pesanan inden tidak ditemukan.');
        }
        if (! $this->warehouseHasEnoughStock($warehouse, $quantities)) {
            throw new ProcessException(
                'Pesanan inden tidak dapat dibuat karena stok di seluruh jaringan hingga gudang tidak mencukupi.',
            );
        }

        return [
            'type' => 'warehouse',
            'id' => (int) $warehouse->getKey(),
            'model' => $warehouse,
        ];
    }

    /**
     * @param  Collection<int, array{product_id: int, quantity: int}>  $items
     * @param  null|array{type: string, id: int, model: Member|Warehouse}  $terminalSeller
     */
    public function reserveTerminalStock(Collection $items, Trx $source, ?array $terminalSeller = null): void
    {
        $terminalSeller ??= $this->terminalSeller($items);
        $details = $items->map(fn (array $item): array => [
            'trx_detail_product_id' => (int) $item['product_id'],
            'trx_detail_qty' => (int) $item['quantity'],
            'trx_detail_nett_price' => 0,
            'trx_detail_product_price' => 0,
        ]);
        $trx = new Trx([
            'trx_seller_type' => $terminalSeller['type'],
            'trx_seller_id' => $terminalSeller['id'],
            'trx_is_preorder' => false,
        ]);
        $trx->setRelation('details', $details->map(fn (array $detail) => new TrxDetail($detail)));
        $trx->trx_code = $source->trx_code;
        $this->stockAllocationService->reserve($trx);
    }

    public function createNextOrder(Trx $approvedOrder): ?Trx
    {
        return DB::transaction(function () use ($approvedOrder): ?Trx {
            $source = Trx::query()
                ->with(['details', 'shippingExpress', 'shippingManual', 'shippingPickup'])
                ->whereKey($approvedOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $source->trx_is_preorder || $source->trx_seller_type === 'warehouse') {
                return null;
            }

            if ($this->sellerHasTerminalReservation($source)) {
                return null;
            }

            if (! $this->rootHasTerminalReservation($source)) {
                $this->assertTerminalStockAvailable($source->details->map(fn ($detail): array => [
                    'product_id' => (int) $detail->trx_detail_product_id,
                    'quantity' => (int) $detail->trx_detail_qty,
                ]));
            }

            $existing = Trx::query()
                ->where('trx_parent_trx_id', $source->getKey())
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $buyer = Member::query()
                ->with(['level', 'parent.level'])
                ->whereKey($source->trx_seller_id)
                ->where('member_status', 1)
                ->lockForUpdate()
                ->firstOrFail();
            $seller = $this->resolveSeller($buyer);
            $details = $this->detailAttributes($source, $buyer);
            $productTotal = collect($details)->sum(
                fn (array $detail): int => $detail['trx_detail_nett_price'] * $detail['trx_detail_qty'],
            );
            $shippingMethod = $seller['type'] === 'warehouse'
                && $source->trx_shipping_method !== 'pickup'
                    ? 'courier_express'
                    : $source->trx_shipping_method;
            $shippingCost = (int) $source->trx_shipping_cost;
            $billAmount = $productTotal + $shippingCost;
            $bank = $this->paymentDestination($seller);
            $now = now();
            $buyerType = $this->memberType($buyer);
            $initialStatus = $seller['type'] === 'warehouse' && $buyerType === 'distributor'
                ? 'waiting_stock_screening'
                : 'waiting_payment';

            $next = Trx::query()->create([
                'trx_code' => $this->transactionCodeService->nextInChain(
                    $source->trx_code,
                    $seller['type'],
                    $buyerType,
                ),
                'trx_parent_trx_id' => $source->getKey(),
                'trx_is_preorder' => 1,
                'trx_seller_type' => $seller['type'],
                'trx_seller_id' => $seller['id'],
                'trx_buyer_type' => $buyerType,
                'trx_buyer_id' => $buyer->getKey(),
                'trx_type' => 'stock',
                'trx_reference_id' => 0,
                'trx_total_price' => $productTotal,
                'trx_discount' => 0,
                'trx_discount_value' => 0,
                'trx_voucher_id' => 0,
                'trx_voucher_value' => 0,
                'trx_grand_total_price' => $productTotal,
                'trx_shipping_cost' => $shippingCost,
                'trx_payment_charge' => 0,
                'trx_grand_total_nett_price' => $billAmount,
                'trx_bill_remaining' => $billAmount,
                'trx_bill_augment' => 0,
                'trx_bill_amount' => $billAmount,
                'trx_payment_method' => 'transfer',
                'trx_shipping_method' => $shippingMethod,
                'trx_status' => $initialStatus,
                'trx_status_datetime' => $now,
                'trx_datetime' => $now,
            ]);

            $next->details()->createMany($details);
            $next->load('details');
            // Terminal source stock is reserved when the root PO is created.
            if ($next->trx_seller_type !== 'warehouse') {
                $this->stockAllocationService->reserve($next);
            }
            TrxPaymentTransfer::query()->create([
                'payment_transfer_trx_id' => $next->getKey(),
                'payment_transfer_bill_remaining' => $billAmount,
                'payment_transfer_bill_augment' => 0,
                'payment_transfer_bill_amount' => $billAmount,
                'payment_transfer_bank_id' => $bank['bank_id'],
                'payment_transfer_account_name' => $bank['account_name'],
                'payment_transfer_account_number' => $bank['account_number'],
                'payment_transfer_amount' => 0,
                'payment_transfer_datetime' => $now,
                'payment_transfer_receipt_file' => null,
                'payment_transfer_approval_status' => 'pending',
                'payment_transfer_approval_admin_id' => 0,
                'payment_transfer_approval_datetime' => null,
                'payment_transfer_note' => '',
            ]);

            $spreadUpline = $this->spreadPaymentUpline($buyer);
            if ($spreadUpline) {
                $this->createSpreadPayment($next, $spreadUpline->getKey(), $buyer->getKey(), $billAmount, $now);
            }

            $this->copyShipping($source, $next, $seller['model'], $shippingMethod);

            return $next->load('details', 'paymentTransfer');
        });
    }

    /** @return Collection<int, Trx> */
    public function createRemainingOrders(Trx $rootOrder): Collection
    {
        $orders = collect();
        $current = $rootOrder;

        while ($next = $this->createNextOrder($current)) {
            if ($orders->contains(fn (Trx $order): bool => $order->getKey() === $next->getKey())) {
                break;
            }

            $orders->push($next);
            $current = $next;

            if ($orders->count() >= 10) {
                break;
            }
        }

        return $orders;
    }

    /** @return Collection<int, Trx> */
    public function cancelFromOrder(Trx $order): Collection
    {
        return DB::transaction(function () use ($order): Collection {
            $current = Trx::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! $current->trx_is_preorder) {
                throw new ProcessException('Pesanan ini bukan bagian dari rantai PO.');
            }

            $visited = [];
            while (true) {
                if (isset($visited[$current->getKey()])) {
                    throw new ProcessException('Rantai PO tidak valid dan tidak dapat dibatalkan.');
                }
                $visited[$current->getKey()] = true;

                $children = Trx::query()
                    ->where('trx_parent_trx_id', $current->getKey())
                    ->where('trx_is_preorder', true)
                    ->orderBy('trx_id')
                    ->lockForUpdate()
                    ->get();
                if ($children->count() > 1) {
                    throw new ProcessException('Rantai PO bercabang dan perlu diperiksa sebelum dibatalkan.');
                }
                if ($children->isEmpty()) {
                    break;
                }

                $current = $children->first();
            }

            return $this->cancelFromTerminalSeller($current);
        });
    }

    /** @return Collection<int, Trx> */
    public function cancelFromTerminalSeller(Trx $terminalOrder): Collection
    {
        return DB::transaction(function () use ($terminalOrder): Collection {
            $terminalOrder = Trx::query()
                ->with('details')
                ->whereKey($terminalOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $terminalOrder->trx_is_preorder) {
                throw new ProcessException('Pesanan ini bukan bagian dari rantai PO.');
            }

            $hasNextOrder = Trx::query()
                ->where('trx_parent_trx_id', $terminalOrder->getKey())
                ->lockForUpdate()
                ->exists();
            if ($hasNextOrder) {
                throw new ProcessException('Rantai PO hanya dapat dibatalkan oleh penjual PO terakhir.');
            }

            $orders = $this->ordersToRoot($terminalOrder);
            $cancellableStatuses = [
                'waiting_stock_screening',
                'waiting_payment',
                'waiting_payment_approval',
                'processing',
            ];
            if ($orders->contains(
                fn (Trx $order): bool => ! in_array($order->trx_status, $cancellableStatuses, true),
            )) {
                throw new ProcessException('Rantai PO pada status saat ini tidak dapat dibatalkan.');
            }

            /** @var Trx $rootOrder */
            $rootOrder = $orders->last();
            $this->stockAllocationService->release($rootOrder);

            foreach ($orders as $order) {
                $this->memberPointService->reverseForCancelledTransaction($order);
            }

            $orderIds = $orders->pluck('trx_id')->map(fn (mixed $id): int => (int) $id)->all();
            RewardStockist::query()
                ->whereIn('reward_stockist_used_trx_id', $orderIds)
                ->update([
                    'reward_stockist_used_value' => 0,
                    'reward_stockist_used_trx_id' => 0,
                ]);
            Trx::query()
                ->whereIn('trx_id', $orderIds)
                ->update([
                    'trx_status' => 'cancelled',
                    'trx_status_datetime' => now(),
                ]);

            return Trx::query()->whereIn('trx_id', $orderIds)->orderBy('trx_id')->get();
        });
    }

    public function cancelAfterTerminalPaymentRejection(Trx $order): bool
    {
        if (! $order->trx_is_preorder || Trx::query()->where('trx_parent_trx_id', $order->getKey())->exists()) {
            return false;
        }

        $this->cancelFromTerminalSeller($order);

        return true;
    }

    public function synchronizeFinalShipment(Trx $finalOrder): void
    {
        if ((int) $finalOrder->trx_parent_trx_id === 0) {
            return;
        }

        $orders = $this->ordersToRoot($finalOrder);
        $root = $orders->last();
        if (! $root || $root->getKey() === $finalOrder->getKey()) {
            return;
        }

        if ($finalOrder->trx_shipping_method === 'courier_express') {
            $finalShipping = $finalOrder->loadMissing('shippingExpress.details')->shippingExpress;
            if (! $finalShipping) {
                throw new ProcessException('Data pengiriman express untuk pesanan inden berantai belum lengkap.');
            }
            if ($root->trx_shipping_method === 'pickup') {
                // Kompatibilitas untuk rantai lama: sebelum perbaikan, pickup
                // otomatis diubah menjadi express ketika mencapai gudang.
                $rootShipping = $root->loadMissing('shippingPickup.latestStatus')->shippingPickup;
                if (! $rootShipping) {
                    throw new ProcessException('Data pengambilan pada pesanan awal tidak ditemukan.');
                }
                $rootShipping->update([
                    'shipping_pickup_schedule_datetime' => $finalShipping
                        ->shipping_courier_express_schedule_datetime,
                    'shipping_pickup_delivery_note_number' => $finalShipping
                        ->shipping_courier_express_delivery_note_number,
                ]);
                $this->copyShippingDetails(
                    'courier_express',
                    $finalShipping->getKey(),
                    $rootShipping->getKey(),
                    'pickup',
                );

                if (in_array($finalOrder->trx_status, ['received', 'completed'], true)
                    && ! in_array(
                        $rootShipping->latestStatus?->shipping_pickup_status_value,
                        ['picked_up', 'completed'],
                        true,
                    )) {
                    ShippingPickupStatus::query()->create([
                        'shipping_pickup_status_shipping_pickup_id' => $rootShipping->getKey(),
                        'shipping_pickup_status_ref_type' => 'trx',
                        'shipping_pickup_status_ref_id' => $root->getKey(),
                        'shipping_pickup_status_value' => 'picked_up',
                        'shipping_pickup_status_datetime' => now(),
                    ]);
                }
            } elseif ($root->trx_shipping_method === 'courier_manual') {
                $rootShipping = $root->loadMissing('shippingManual')->shippingManual;
                if (! $rootShipping) {
                    throw new ProcessException('Data pengiriman manual pada pesanan awal tidak ditemukan.');
                }
                $rootShipping->update([
                    'shipping_courier_manual_awb' => $finalShipping->shipping_courier_express_awb,
                    'shipping_courier_manual_delivery_note_number' => $finalShipping
                        ->shipping_courier_express_delivery_note_number,
                ]);
                $this->copyShippingDetails(
                    'courier_express',
                    $finalShipping->getKey(),
                    $rootShipping->getKey(),
                    'courier_manual',
                );
            } elseif ($root->trx_shipping_method === 'courier_express') {
                $rootShipping = $root->loadMissing('shippingExpress')->shippingExpress;
                if (! $rootShipping) {
                    throw new ProcessException('Data pengiriman express pada pesanan awal tidak ditemukan.');
                }
                $rootShipping->update([
                    // The provider order belongs only to the physical shipment made by
                    // the terminal seller. Keeping it on the root duplicates callback
                    // targets and can leave child PO transactions unsynchronised.
                    'shipping_courier_express_order_id' => '',
                    'shipping_courier_express_pickup_method' => $finalShipping->shipping_courier_express_pickup_method,
                    'shipping_courier_express_pickup_number' => $finalShipping->shipping_courier_express_pickup_number,
                    'shipping_courier_express_schedule_datetime' => $finalShipping
                        ->shipping_courier_express_schedule_datetime,
                    'shipping_courier_express_awb' => $finalShipping->shipping_courier_express_awb,
                    'shipping_courier_express_delivery_note_number' => $finalShipping
                        ->shipping_courier_express_delivery_note_number,
                ]);
                $this->copyShippingDetails(
                    'courier_express',
                    $finalShipping->getKey(),
                    $rootShipping->getKey(),
                );
            } else {
                throw new ProcessException('Metode pengiriman awal belum mendukung sinkronisasi dari express.');
            }
        } elseif ($finalOrder->trx_shipping_method === 'courier_manual') {
            $finalShipping = $finalOrder->loadMissing('shippingManual.details')->shippingManual;
            $rootShipping = $root->loadMissing('shippingManual')->shippingManual;
            if (! $finalShipping || ! $rootShipping) {
                throw new ProcessException('Data pengiriman manual untuk pesanan inden berantai belum lengkap.');
            }
            $rootShipping->update([
                'shipping_courier_manual_awb' => $finalShipping->shipping_courier_manual_awb,
                'shipping_courier_manual_delivery_note_number' => $finalShipping->shipping_courier_manual_delivery_note_number,
            ]);
            $this->copyShippingDetails(
                'courier_manual',
                $finalShipping->getKey(),
                $rootShipping->getKey(),
            );
        } elseif ($finalOrder->trx_shipping_method === 'pickup') {
            $finalShipping = $finalOrder->loadMissing('shippingPickup.details', 'shippingPickup.latestStatus')->shippingPickup;
            $rootShipping = $root->loadMissing('shippingPickup')->shippingPickup;
            if (! $finalShipping || ! $rootShipping) {
                throw new ProcessException('Data pengambilan pesanan inden berantai belum lengkap.');
            }
            $rootShipping->update([
                'shipping_pickup_schedule_datetime' => $finalShipping->shipping_pickup_schedule_datetime,
                'shipping_pickup_delivery_note_number' => $finalShipping->shipping_pickup_delivery_note_number,
            ]);
            $this->copyShippingDetails('pickup', $finalShipping->getKey(), $rootShipping->getKey());
            ShippingPickupStatus::query()->create([
                'shipping_pickup_status_shipping_pickup_id' => $rootShipping->getKey(),
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $root->getKey(),
                'shipping_pickup_status_value' => 'picked_up',
                'shipping_pickup_status_datetime' => now(),
            ]);
        }

        Trx::query()
            ->whereIn('trx_id', $orders->pluck('trx_id'))
            ->update([
                'trx_status' => $finalOrder->trx_status,
                'trx_status_datetime' => now(),
            ]);
    }

    public function markChainCompleted(Trx $rootOrder): void
    {
        $ids = collect([$rootOrder->getKey()]);
        $current = $rootOrder;

        while ($child = Trx::query()
            ->where('trx_parent_trx_id', $current->getKey())
            ->lockForUpdate()
            ->first()) {
            if ($ids->contains($child->getKey())) {
                break;
            }
            $ids->push($child->getKey());
            $current = $child;
        }

        Trx::query()
            ->whereIn('trx_id', $ids)
            ->update(['trx_status' => 'completed', 'trx_status_datetime' => now()]);
    }

    /** @param Collection<int, int> $quantities */
    private function memberHasEnoughStock(Member $member, Collection $quantities): bool
    {
        $stocks = MemberStock::query()
            ->where('member_stock_member_id', $member->getKey())
            ->whereIn('member_stock_product_id', $quantities->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('member_stock_product_id');

        return ! $quantities->contains(function (int $quantity, int $productId) use ($stocks): bool {
            $stock = $stocks->get($productId);
            $available = $stock
                ? max(0, (int) $stock->member_stock_balance)
                : 0;

            return $available < $quantity;
        });
    }

    /** @param Collection<int, int> $quantities */
    private function warehouseHasEnoughStock(Warehouse $warehouse, Collection $quantities): bool
    {
        $stocks = WarehouseStock::query()
            ->where('warehouse_stock_warehouse_id', $warehouse->getKey())
            ->whereIn('warehouse_stock_product_id', $quantities->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('warehouse_stock_product_id');

        return ! $quantities->contains(function (int $quantity, int $productId) use ($stocks): bool {
            $stock = $stocks->get($productId);
            $available = $stock
                ? max(0, (int) $stock->warehouse_stock_balance)
                : 0;

            return $available < $quantity;
        });
    }

    private function sellerHasTerminalReservation(Trx $source): bool
    {
        if ($source->trx_seller_type === 'warehouse') {
            return false;
        }

        $root = $this->ordersToRoot($source)->last();
        if (! $root) {
            return false;
        }

        return $source->details->every(function ($detail) use ($root, $source): bool {
            $quantity = (int) $detail->trx_detail_qty;
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $source->trx_seller_id)
                ->where('member_stock_product_id', $detail->trx_detail_product_id)
                ->lockForUpdate()
                ->first();

            if (! $stock || (int) $stock->member_stock_transfer_out < $quantity) {
                return false;
            }

            return DB::table('member_stock_log')
                ->where('member_stock_log_member_id', $source->trx_seller_id)
                ->where('member_stock_log_product_id', $detail->trx_detail_product_id)
                ->where('member_stock_log_type', 'out')
                ->where('member_stock_log_note', "Pemesanan {$root->trx_code}")
                ->exists();
        });
    }

    private function rootHasTerminalReservation(Trx $source): bool
    {
        $root = $this->ordersToRoot($source)->last();

        return $root instanceof Trx && $this->terminalReservationSeller($root) !== null;
    }

    /** @return array{type: string, id: int, model: Member|Warehouse} */
    private function resolveSeller(Member $buyer): array
    {
        if ($buyer->level?->member_level_code === 'DST') {
            $warehouse = Warehouse::query()
                ->whereKey(1)
                ->where('warehouse_is_active', 1)
                ->lockForUpdate()
                ->firstOrFail();

            return ['type' => 'warehouse', 'id' => (int) $warehouse->getKey(), 'model' => $warehouse];
        }

        $seller = Member::query()
            ->with('level')
            ->whereKey($buyer->member_parent_member_id)
            ->where('member_status', 1)
            ->lockForUpdate()
            ->first();
        if (! $seller) {
            throw new ProcessException('Mitra penjual di atas jaringan tidak tersedia untuk meneruskan pesanan inden.');
        }

        return [
            'type' => $this->memberType($seller),
            'id' => (int) $seller->getKey(),
            'model' => $seller,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function detailAttributes(Trx $source, Member $buyer): array
    {
        $productIds = $source->details->pluck('trx_detail_product_id')->map(
            fn (mixed $productId): int => (int) $productId,
        );
        $products = Product::query()->whereIn('product_id', $productIds)->get()->keyBy('product_id');
        $prices = ProductPrice::query()
            ->where('product_price_member_level_id', $buyer->member_member_level_id)
            ->whereIn('product_price_product_id', $productIds)
            ->get()
            ->keyBy('product_price_product_id');

        return $source->details->map(function ($sourceDetail) use ($prices, $products): array {
            $product = $products->get((int) $sourceDetail->trx_detail_product_id);
            $price = (int) ($prices->get((int) $sourceDetail->trx_detail_product_id)?->product_price_value
                ?? $product?->product_customer_price
                ?? $sourceDetail->trx_detail_nett_price);

            return [
                'trx_detail_product_id' => $sourceDetail->trx_detail_product_id,
                'trx_detail_product_plan_id' => $sourceDetail->trx_detail_product_plan_id,
                'trx_detail_product_type' => $sourceDetail->trx_detail_product_type,
                'trx_detail_product_code' => $sourceDetail->trx_detail_product_code,
                'trx_detail_product_name' => $sourceDetail->trx_detail_product_name,
                'trx_detail_product_bpom_number' => $sourceDetail->trx_detail_product_bpom_number
                    ?: $product?->product_bpom_number,
                'trx_detail_product_price' => $price,
                'trx_detail_product_weight' => $sourceDetail->trx_detail_product_weight,
                'trx_detail_product_length' => $sourceDetail->trx_detail_product_length,
                'trx_detail_product_height' => $sourceDetail->trx_detail_product_height,
                'trx_detail_product_width' => $sourceDetail->trx_detail_product_width,
                'trx_detail_discount_percent' => 0,
                'trx_detail_discount_value' => 0,
                'trx_detail_nett_price' => $price,
                'trx_detail_qty' => $sourceDetail->trx_detail_qty,
            ];
        })->all();
    }

    /** @param array{type: string, id: int, model: Member|Warehouse} $seller
     * @return array{bank_id: int, account_name: string, account_number: string}
     */
    private function paymentDestination(array $seller): array
    {
        if ($seller['model'] instanceof Member) {
            $bank = MemberBankAccount::query()
                ->where('member_bank_account_member_id', $seller['id'])
                ->where('member_bank_account_is_active', 1)
                ->orderByDesc('member_bank_account_is_default')
                ->orderBy('member_bank_account_id')
                ->lockForUpdate()
                ->first();
            if ($bank) {
                return [
                    'bank_id' => (int) $bank->member_bank_account_bank_id,
                    'account_name' => $bank->member_bank_account_name,
                    'account_number' => $bank->member_bank_account_number,
                ];
            }

            throw new ProcessException(sprintf(
                'Rekening Penjual (%s) belum ditentukan.',
                Str::headline($seller['type']),
            ));
        }

        $bank = BankCompany::query()
            ->where('bank_company_type', 'company')
            ->where('bank_company_bank_is_active', 1)
            ->orderBy('bank_company_id')
            ->lockForUpdate()
            ->first();
        if (! $bank) {
            throw new ProcessException('Rekening tujuan pembayaran pesanan inden tidak ditemukan.');
        }

        return [
            'bank_id' => (int) $bank->bank_company_bank_id,
            'account_name' => (string) $bank->bank_company_bank_acc_name,
            'account_number' => (string) $bank->bank_company_bank_acc_number,
        ];
    }

    private function createSpreadPayment(
        Trx $trx,
        int $uplineId,
        int $memberId,
        int $billAmount,
        \DateTimeInterface $now,
    ): void {
        $bank = BankCompany::query()
            ->where('bank_company_type', 'spread_payment')
            ->where('bank_company_bank_is_active', 1)
            ->orderBy('bank_company_id')
            ->lockForUpdate()
            ->first();
        if (! $bank) {
            throw new ProcessException('Rekening pembagian pembayaran tidak ditemukan.');
        }
        $percentage = max(1.0, (float) BusinessConfig::get('partnership.spread_payment_percentage', 1));

        TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => $trx->getKey(),
            'trx_spread_payment_upline_id' => $uplineId,
            'trx_spread_payment_member_id' => $memberId,
            'trx_spread_payment_bank_id' => $bank->bank_company_bank_id,
            'trx_spread_payment_account_name' => $bank->bank_company_bank_acc_name,
            'trx_spread_payment_account_number' => $bank->bank_company_bank_acc_number,
            'trx_spread_payment_percentage' => $percentage,
            'trx_spread_payment_amount' => (int) ceil($billAmount * $percentage / 100),
            'trx_spread_payment_status' => 'pending',
            'trx_spread_payment_approved_by' => 0,
            'trx_spread_payment_paid_by' => 0,
            'trx_spread_payment_note' => 'Pembayaran spread wajib dilampirkan bersama bukti pembayaran utama.',
            'trx_spread_payment_created_datetime' => $now,
        ]);
    }

    private function spreadPaymentUpline(Member $buyer): ?Member
    {
        if ($buyer->level?->member_level_code !== 'DST') {
            return null;
        }

        $parent = $buyer->parent;

        if (! $parent || (int) $parent->member_status !== 1) {
            return null;
        }

        return $parent->level?->member_level_code === 'DST' ? $parent : null;
    }

    private function copyShipping(
        Trx $source,
        Trx $next,
        Member|Warehouse $seller,
        string $shippingMethod,
    ): void {
        if ($shippingMethod === 'courier_express') {
            $this->copyAsExpressShipping($source, $next, $seller);

            return;
        }

        if ($shippingMethod !== $source->trx_shipping_method) {
            throw new ProcessException('Metode pengiriman pesanan inden tidak sesuai dengan tujuan pesanan.');
        }

        if ($source->trx_shipping_method === 'courier_manual') {
            $shipping = $source->shippingManual;
            if (! $shipping) {
                throw new ProcessException('Data pengiriman manual pada pesanan awal tidak ditemukan.');
            }
            $copy = $shipping->replicate();
            $copy->shipping_courier_manual_ref_id = $next->getKey();
            $copy->shipping_courier_manual_awb = null;
            $copy->shipping_courier_manual_insurance = $shipping->shipping_courier_manual_insurance;
            $copy->fill($this->manualOrigin($seller));
            $copy->save();

            return;
        }

        if ($source->trx_shipping_method !== 'pickup') {
            throw new ProcessException('Pesanan inden berantai hanya mendukung kurir manual atau ambil di tempat.');
        }

        $sourcePickup = $source->loadMissing('shippingPickup')->shippingPickup;
        if (! $sourcePickup || blank($sourcePickup->shipping_pickup_pin)) {
            throw new ProcessException('Kode pengambilan pada pesanan awal tidak ditemukan.');
        }

        $origin = $this->location($seller);
        $pickup = ShippingPickup::query()->create([
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $next->getKey(),
            'shipping_pickup_seller_address' => $origin['address'],
            'shipping_pickup_seller_name' => Str::substr($origin['name'], 0, 50),
            'shipping_pickup_seller_mobilephone' => Str::substr($origin['phone'], 0, 16),
            'shipping_pickup_schedule_datetime' => null,
            // Satu rangkaian PO mewakili satu penyerahan fisik. Pembeli cukup
            // menunjukkan kode yang tampil pada pesanan awal kepada seller terakhir.
            'shipping_pickup_pin' => $sourcePickup->shipping_pickup_pin,
        ]);
        ShippingPickupStatus::query()->create([
            'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
            'shipping_pickup_status_ref_type' => 'trx',
            'shipping_pickup_status_ref_id' => $next->getKey(),
            'shipping_pickup_status_value' => 'pending',
            'shipping_pickup_status_datetime' => now(),
        ]);
    }

    private function copyAsExpressShipping(Trx $source, Trx $next, Member|Warehouse $seller): void
    {
        if ($source->trx_shipping_method === 'courier_express') {
            $shipping = $source->shippingExpress;
            if (! $shipping) {
                throw new ProcessException('Data pengiriman ekspres pada pesanan awal tidak ditemukan.');
            }
            $copy = $shipping->replicate();
            $copy->shipping_courier_express_ref_id = $next->getKey();
            $copy->shipping_courier_express_order_id = '';
            $copy->shipping_courier_express_pickup_number = '';
            $copy->shipping_courier_express_schedule_datetime = null;
            $copy->shipping_courier_express_awb = null;
            $copy->fill($this->expressOrigin($seller));
            $copy->save();

            return;
        }

        if ($source->trx_shipping_method === 'courier_manual') {
            $shipping = $source->shippingManual;
            if (! $shipping) {
                throw new ProcessException('Data pengiriman manual pada pesanan awal tidak ditemukan.');
            }
            [$length, $width, $height] = $this->manualPackageDimensions(
                $shipping->shipping_courier_manual_package_dimension,
            );

            ShippingCourierExpress::query()->create([
                'shipping_courier_express_ref_type' => 'trx',
                'shipping_courier_express_ref_id' => $next->getKey(),
                'shipping_courier_express_type' => $shipping->shipping_courier_manual_type,
                'shipping_courier_express_expedition_name' => $shipping->shipping_courier_manual_name,
                'shipping_courier_express_expedition_service' => $shipping->shipping_courier_manual_service,
                'shipping_courier_express_etd' => '',
                'shipping_courier_express_order_id' => '',
                'shipping_courier_express_pickup_method' => 'DROP-OFF',
                'shipping_courier_express_pickup_number' => '',
                'shipping_courier_express_schedule_datetime' => null,
                'shipping_courier_express_awb' => null,
                'shipping_courier_express_cost' => (int) $shipping->shipping_courier_manual_price,
                'shipping_courier_express_insurance_is_force' => 0,
                'shipping_courier_express_insurance' => (int) $shipping->shipping_courier_manual_insurance,
                'shipping_courier_express_package_weight' => (int) $shipping->shipping_courier_manual_package_weight,
                'shipping_courier_express_package_length' => $length,
                'shipping_courier_express_package_width' => $width,
                'shipping_courier_express_package_height' => $height,
                ...$this->expressOrigin($seller),
                ...$this->expressDestinationFromManual($shipping),
            ]);

            return;
        }

        if ($source->trx_shipping_method === 'pickup') {
            ShippingCourierExpress::query()->create([
                'shipping_courier_express_ref_type' => 'trx',
                'shipping_courier_express_ref_id' => $next->getKey(),
                'shipping_courier_express_type' => '',
                'shipping_courier_express_expedition_name' => '',
                'shipping_courier_express_expedition_service' => '',
                'shipping_courier_express_etd' => '',
                'shipping_courier_express_order_id' => '',
                'shipping_courier_express_pickup_method' => 'DROP-OFF',
                'shipping_courier_express_pickup_number' => '',
                'shipping_courier_express_schedule_datetime' => null,
                'shipping_courier_express_awb' => null,
                'shipping_courier_express_cost' => (int) $source->trx_shipping_cost,
                'shipping_courier_express_insurance_is_force' => 0,
                'shipping_courier_express_insurance' => 0,
                'shipping_courier_express_package_weight' => 0,
                'shipping_courier_express_package_length' => 0,
                'shipping_courier_express_package_width' => 0,
                'shipping_courier_express_package_height' => 0,
                ...$this->expressOrigin($seller),
                ...$this->expressDestinationFromRootBuyer($source),
            ]);

            return;
        }

        throw new ProcessException('Pesanan inden ke perusahaan harus menggunakan kurir ekspres.');
    }

    private function copyShippingDetails(
        string $type,
        int $sourceId,
        int $destinationId,
        ?string $destinationType = null,
    ): void {
        $targetType = $destinationType ?? $type;

        ShippingDetail::query()
            ->where('shipping_detail_shipping_type', $targetType)
            ->where('shipping_detail_shipping_id', $destinationId)
            ->delete();
        $details = ShippingDetail::query()
            ->where('shipping_detail_shipping_type', $type)
            ->where('shipping_detail_shipping_id', $sourceId)
            ->get();
        ShippingDetail::query()->insert($details->map(fn (ShippingDetail $detail): array => [
            'shipping_detail_shipping_type' => $targetType,
            'shipping_detail_shipping_id' => $destinationId,
            'shipping_detail_product_id' => $detail->shipping_detail_product_id,
            'shipping_detail_batch_number' => $detail->shipping_detail_batch_number,
            'shipping_detail_qty' => $detail->shipping_detail_qty,
            'shipping_detail_expire_date' => $detail->shipping_detail_expire_date,
        ])->all());
    }

    /** @return Collection<int, Trx> */
    private function ordersToRoot(Trx $order): Collection
    {
        $orders = collect();
        $current = Trx::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

        while (true) {
            if ($orders->contains(fn (Trx $item): bool => $item->getKey() === $current->getKey())) {
                break;
            }
            $orders->push($current);
            if ((int) $current->trx_parent_trx_id === 0) {
                break;
            }
            $current = Trx::query()
                ->whereKey($current->trx_parent_trx_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $orders;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function manualPackageDimensions(?string $dimension): array
    {
        $parts = collect(explode('x', (string) $dimension))
            ->map(fn (string $part): int => max(0, (int) trim($part)))
            ->values();

        return [
            (int) ($parts->get(0) ?? 0),
            (int) ($parts->get(1) ?? 0),
            (int) ($parts->get(2) ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function expressOrigin(Member|Warehouse $seller): array
    {
        $origin = $this->location($seller);

        return [
            'shipping_courier_express_origin_name' => Str::substr($origin['name'], 0, 50),
            'shipping_courier_express_origin_phone' => Str::substr($origin['phone'], 0, 16),
            'shipping_courier_express_origin_address' => $origin['address'],
            'shipping_courier_express_origin_subdistrict_id' => $origin['subdistrict_id'],
            'shipping_courier_express_origin_subdistrict_name' => Str::substr($origin['subdistrict_name'], 0, 50),
            'shipping_courier_express_origin_district_name' => Str::substr($origin['district_name'], 0, 50),
            'shipping_courier_express_origin_city_name' => Str::substr($origin['city_name'], 0, 50),
            'shipping_courier_express_origin_province_name' => Str::substr($origin['province_name'], 0, 50),
            'shipping_courier_express_origin_zipcode' => $origin['zipcode'],
        ];
    }

    /** @return array<string, mixed> */
    private function expressDestinationFromManual(ShippingCourierManual $shipping): array
    {
        return [
            'shipping_courier_express_destination_name' => $shipping->shipping_courier_manual_destination_name,
            'shipping_courier_express_destination_phone' => $shipping->shipping_courier_manual_destination_phone,
            'shipping_courier_express_destination_address' => $shipping->shipping_courier_manual_destination_address,
            'shipping_courier_express_destination_subdistrict_id' => $shipping->shipping_courier_manual_destination_subdistrict_id,
            'shipping_courier_express_destination_subdistrict_name' => $shipping->shipping_courier_manual_destination_subdistrict_name,
            'shipping_courier_express_destination_district_name' => $shipping->shipping_courier_manual_destination_district_name,
            'shipping_courier_express_destination_city_name' => $shipping->shipping_courier_manual_destination_city_name,
            'shipping_courier_express_destination_province_name' => $shipping->shipping_courier_manual_destination_province_name,
            'shipping_courier_express_destination_zipcode' => $shipping->shipping_courier_manual_destination_zipcode,
        ];
    }

    /** @return array<string, mixed> */
    private function expressDestinationFromRootBuyer(Trx $source): array
    {
        $root = $this->ordersToRoot($source)->last();
        if (! $root || ! in_array($root->trx_buyer_type, ['distributor', 'agent', 'reseller'], true)) {
            throw new ProcessException('Alamat tujuan pesanan inden ke perusahaan tidak ditemukan.');
        }

        $buyer = Member::query()->whereKey($root->trx_buyer_id)->first();
        $address = MemberAddress::query()
            ->where('member_address_member_id', $root->trx_buyer_id)
            ->orderByDesc('member_address_is_default')
            ->orderBy('member_address_id')
            ->first();
        if (! $buyer || ! $address) {
            throw new ProcessException('Alamat tujuan pesanan inden ke perusahaan tidak ditemukan.');
        }

        $subdistrict = RefSubdistrict::query()
            ->where('subdistrict_id', $address->member_address_subdistrict_id)
            ->first();

        return [
            'shipping_courier_express_destination_name' => Str::substr(
                (string) ($address->member_address_recipient ?: $buyer->member_name),
                0,
                50,
            ),
            'shipping_courier_express_destination_phone' => Str::substr(
                (string) ($address->member_address_phone ?: $buyer->member_mobilephone),
                0,
                16,
            ),
            'shipping_courier_express_destination_address' => $address->member_address_full,
            'shipping_courier_express_destination_subdistrict_id' => $address->member_address_subdistrict_id,
            'shipping_courier_express_destination_subdistrict_name' => Str::substr(
                (string) ($subdistrict?->subdistrict_name ?? ''),
                0,
                50,
            ),
            'shipping_courier_express_destination_district_name' => Str::substr(
                (string) (RefDistrict::query()
                    ->where('district_id', $address->member_address_district_id)
                    ->value('district_name') ?? ''),
                0,
                50,
            ),
            'shipping_courier_express_destination_city_name' => Str::substr(
                (string) (RefCity::query()
                    ->where('city_id', $address->member_address_city_id)
                    ->value('city_name') ?? ''),
                0,
                50,
            ),
            'shipping_courier_express_destination_province_name' => Str::substr(
                (string) (RefProvince::query()
                    ->where('province_id', $address->member_address_province_id)
                    ->value('province_name') ?? ''),
                0,
                50,
            ),
            'shipping_courier_express_destination_zipcode' => $subdistrict?->subdistrict_zip_code === null
                ? null
                : Str::substr((string) $subdistrict->subdistrict_zip_code, 0, 5),
        ];
    }

    /** @return array<string, mixed> */
    private function manualOrigin(Member|Warehouse $seller): array
    {
        $origin = $this->location($seller);

        return [
            'shipping_courier_manual_origin_name' => Str::substr($origin['name'], 0, 50),
            'shipping_courier_manual_origin_phone' => Str::substr($origin['phone'], 0, 16),
            'shipping_courier_manual_origin_address' => $origin['address'],
            'shipping_courier_manual_origin_subdistrict_id' => $origin['subdistrict_id'],
            'shipping_courier_manual_origin_subdistrict_name' => Str::substr($origin['subdistrict_name'], 0, 50),
            'shipping_courier_manual_origin_district_name' => Str::substr($origin['district_name'], 0, 50),
            'shipping_courier_manual_origin_city_name' => Str::substr($origin['city_name'], 0, 50),
            'shipping_courier_manual_origin_province_name' => Str::substr($origin['province_name'], 0, 50),
            'shipping_courier_manual_origin_zipcode' => $origin['zipcode'],
        ];
    }

    /** @return array{name: string, phone: string, address: string, subdistrict_id: int, subdistrict_name: string, district_name: string, city_name: string, province_name: string, zipcode: ?string} */
    private function location(Member|Warehouse $seller): array
    {
        $isWarehouse = $seller instanceof Warehouse;
        $subdistrictId = (int) ($isWarehouse
            ? $seller->warehouse_subdistrict_id
            : $seller->member_subdistrict_id);
        $districtId = (int) ($isWarehouse ? $seller->warehouse_district_id : $seller->member_district_id);
        $cityId = (int) ($isWarehouse ? $seller->warehouse_city_id : $seller->member_city_id);
        $provinceId = (int) ($isWarehouse ? $seller->warehouse_province_id : $seller->member_province_id);
        $subdistrict = RefSubdistrict::query()
            ->where('subdistrict_id', $subdistrictId)
            ->first();

        return [
            'name' => (string) ($isWarehouse ? $seller->warehouse_name : $seller->member_name),
            'phone' => (string) ($isWarehouse ? $seller->warehouse_phone : $seller->member_mobilephone),
            'address' => (string) ($isWarehouse ? $seller->warehouse_address : $seller->member_address),
            'subdistrict_id' => $subdistrictId,
            'subdistrict_name' => (string) ($subdistrict?->subdistrict_name ?? ''),
            'district_name' => (string) (RefDistrict::query()
                ->where('district_id', $districtId)
                ->value('district_name') ?? ''),
            'city_name' => (string) (RefCity::query()
                ->where('city_id', $cityId)
                ->value('city_name') ?? ''),
            'province_name' => (string) (RefProvince::query()
                ->where('province_id', $provinceId)
                ->value('province_name') ?? ''),
            'zipcode' => $subdistrict?->subdistrict_zip_code === null
                ? null
                : Str::substr((string) $subdistrict->subdistrict_zip_code, 0, 5),
        ];
    }

    private function memberType(Member $member): string
    {
        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => throw new ProcessException('Tingkat mitra tidak dapat menggunakan pesanan inden.'),
        };
    }
}
