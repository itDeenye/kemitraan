<?php

namespace Database\Seeders;

use App\Contracts\Integrations\StcGateway;
use App\Models\Customer;
use App\Models\GoodsReceive;
use App\Models\GoodsReceiveDetail;
use App\Models\Member;
use App\Models\MemberBankAccount;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\Product;
use App\Models\ReturnDetail;
use App\Models\ReturnModel;
use App\Models\ReturnStatusLog;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierExpressStatus;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierInstantStatus;
use App\Models\ShippingCourierManual;
use App\Models\ShippingCourierManualStatus;
use App\Models\ShippingDetail;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxPaymentTransfer;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLog;
use App\Services\Document\DocumentCodeService;
use App\Services\Reward\MemberPointService;
use App\Services\Transaction\TransactionCodeService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class DevelopmentCommerceDataSeeder extends Seeder
{
    private TransactionCodeService $transactionCodeService;

    private DocumentCodeService $documentCodeService;

    /** @var array{cost: int, name: string, service: string, type: string, etd: string, force_insurance: bool, insurance: int} */
    private array $expressRate = [
        'cost' => 0,
        'name' => 'jne',
        'service' => 'JNE Express Reguler',
        'type' => 'REG23',
        'etd' => '2-3',
        'force_insurance' => false,
        'insurance' => 0,
    ];

    public function run(
        TransactionCodeService $transactionCodeService,
        DocumentCodeService $documentCodeService,
        StcGateway $stcGateway,
        MemberPointService $memberPointService,
    ): void {
        $this->transactionCodeService = $transactionCodeService;
        $this->documentCodeService = $documentCodeService;
        $warehouse = Warehouse::query()->findOrFail(1);
        $member = Member::query()->findOrFail(1);
        $agent = Member::query()->where('member_mobilephone', '+6281200000101')->firstOrFail();
        $reseller = Member::query()->where('member_mobilephone', '+6281200000201')->firstOrFail();
        $customer = Customer::query()->where('customer_member_id', 1)->firstOrFail();
        $products = Product::query()
            ->where('product_is_deleted', 0)
            ->orderBy('product_id')
            ->limit(3)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $this->expressRate = $this->resolveCurrentExpressRate(
            $stcGateway,
            $warehouse,
            $member,
            $products,
        );

        DB::transaction(function () use (
            $agent,
            $customer,
            $member,
            $memberPointService,
            $products,
            $reseller,
            $warehouse,
        ): void {
            $now = now();
            $this->removeLegacyDevelopmentReceipts();
            $purchaseWaitingPayment = $this->transaction(
                'DEV-PUR-WAIT-PAY',
                $warehouse,
                $member,
                'waiting_payment',
                'pickup',
                $products,
                $this->today($now, 120),
            );
            $purchasePayment = $this->transaction(
                'DEV-PUR-PAYMENT',
                $warehouse,
                $member,
                'waiting_payment_approval',
                'courier_express',
                $products,
                $this->today($now, 110),
            );
            $purchaseProcessing = $this->transaction(
                'DEV-PUR-PROCESS',
                $warehouse,
                $member,
                'processing',
                'courier_express',
                $products,
                $this->today($now, 100),
            );
            $purchaseShipped = $this->transaction(
                'DEV-PUR-SHIPPED',
                $warehouse,
                $member,
                'shipped',
                'courier_express',
                $products,
                $this->today($now, 90),
                preorder: true,
            );
            $purchaseCompleted = $this->transaction(
                'DEV-PUR-COMPLETE',
                $warehouse,
                $member,
                'completed',
                'courier_express',
                $products,
                $this->today($now, 80),
            );
            $purchaseForReturn = $this->transaction(
                'DEV-PUR-RETURN',
                $warehouse,
                $member,
                'completed',
                'courier_express',
                $products,
                $this->today($now, 130),
            );

            $this->payment($purchasePayment, 'submitted', $this->today($now, 100));
            $this->payment($purchaseProcessing, 'approved', $this->today($now, 90));
            $this->draftExpressShipping($purchaseProcessing, $warehouse, $member);
            $this->payment($purchaseShipped, 'approved', $this->today($now, 80));
            $this->payment($purchaseCompleted, 'approved', $this->today($now, 70));
            $this->payment($purchaseForReturn, 'approved', $this->today($now, 120));
            $this->expressShipping(
                $purchaseShipped,
                $warehouse,
                $member,
                'shipped_packages',
                $this->today($now, 25),
            );
            $this->expressShipping(
                $purchaseCompleted,
                $warehouse,
                $member,
                'completed',
                $this->today($now, 15),
            );
            $this->expressShipping(
                $purchaseForReturn,
                $warehouse,
                $member,
                'completed',
                $this->today($now, 65),
            );
            $this->pickupShipping($purchaseWaitingPayment, $warehouse, 'pending', $now);

            $saleProcessing = $this->sale(
                'DEV-SALE-PROCESS',
                $member,
                $customer,
                'processing',
                'pickup',
                $products,
                $this->today($now, 80),
            );
            $saleCompleted = $this->sale(
                'DEV-SALE-COMPLETED',
                $member,
                $customer,
                'completed',
                'pickup',
                $products,
                $this->today($now, 70),
            );
            $this->pickupShipping($saleProcessing, $member, 'pending', $now);
            $this->pickupShipping($saleCompleted, $member, 'completed', $now);

            $this->preorderChain(
                $warehouse,
                $member,
                $agent,
                $reseller,
                $products,
                $memberPointService,
                $now,
            );
            $this->upgradeRetailSales($member, $agent, $reseller, $products, $now);

            $completedReceive = $this->memberGoodsReceive(
                $purchaseCompleted,
                'COMPLETE',
                $this->today($now, 10),
            );
            $returnReceive = $this->memberGoodsReceive(
                $purchaseForReturn,
                'RETURN',
                $this->today($now, 60),
            );
            $this->rewardPurchases($warehouse, $member, $agent, $reseller, $products, $now);
            $this->returns(
                $returnReceive,
                $completedReceive,
                $member,
                $warehouse,
                $products->first(),
            );

            foreach (['DEV-SPREAD-PENDING', 'DEV-SPREAD-APPROVED', 'DEV-SPREAD-PAID'] as $key) {
                $spreadTrx = $this->developmentTransaction($key);
                if ($spreadTrx) {
                    $this->ensureDetails($spreadTrx, $products, false);
                }
            }

        });
    }

    private function removeLegacyDevelopmentReceipts(): void
    {
        $receives = GoodsReceive::query()
            ->whereIn('goods_receive_number', ['DEV-GR-MEMBER-001'])
            ->get();

        foreach ($receives as $receive) {
            $receive->details()->delete();
            $receive->delete();
        }
    }

    /** @param Collection<int, Product> $products */
    private function transaction(
        string $code,
        Warehouse|Member $seller,
        Member $buyer,
        string $status,
        string $shippingMethod,
        $products,
        CarbonInterface $orderedAt,
        bool $preorder = false,
        ?array $quantities = null,
        ?string $chainSourceCode = null,
    ): Trx {
        $total = $this->itemsTotal($products, $quantities);
        $shippingCost = $this->shippingCost($shippingMethod, 20_000);
        $sellerType = $seller instanceof Warehouse ? 'warehouse' : $this->memberType($seller);
        $buyerType = $this->memberType($buyer);
        $trx = $this->developmentTransaction($code) ?? new Trx;

        if (! $trx->exists) {
            $suffix = TransactionCodeService::deterministicUniqueSuffix($code);
            $trx->trx_code = $chainSourceCode
                ? $this->transactionCodeService->nextInChain(
                    $chainSourceCode,
                    $sellerType,
                    $buyerType,
                    $suffix,
                )
                : $this->transactionCodeService->next($sellerType, $buyerType, $suffix);
        }

        $trx->fill([
            'trx_parent_trx_id' => 0,
            'trx_is_preorder' => $preorder,
            'trx_seller_type' => $sellerType,
            'trx_seller_id' => $seller->getKey(),
            'trx_buyer_type' => $buyerType,
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'stock',
            'trx_reference_id' => 0,
            'trx_total_price' => $total,
            'trx_discount' => 0,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => $total,
            'trx_shipping_cost' => $shippingCost,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => $total + $shippingCost,
            'trx_bill_remaining' => 0,
            'trx_bill_augment' => 0,
            'trx_bill_amount' => $total + $shippingCost,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => $shippingMethod,
            'trx_status' => $status,
            'trx_status_datetime' => $orderedAt->copy()->addHour(),
            'trx_datetime' => $orderedAt,
        ]);
        $trx->save();
        $this->ensureDetails($trx, $products, $preorder, $quantities);

        return $trx;
    }

    /** @param Collection<int, Product> $products */
    private function sale(
        string $code,
        Member $seller,
        Customer $buyer,
        string $status,
        string $shippingMethod,
        $products,
        CarbonInterface $orderedAt,
        ?array $quantities = null,
    ): Trx {
        $total = $this->itemsTotal($products, $quantities);
        $shippingCost = $this->shippingCost($shippingMethod, 18_000);
        $trx = $this->developmentTransaction($code) ?? new Trx;
        $sellerType = $this->memberType($seller);

        if (! $trx->exists) {
            $trx->trx_code = $this->transactionCodeService->next(
                $sellerType,
                'customer',
                TransactionCodeService::deterministicUniqueSuffix($code),
            );
        }

        $trx->fill([
            'trx_parent_trx_id' => 0,
            'trx_is_preorder' => 0,
            'trx_seller_type' => $sellerType,
            'trx_seller_id' => $seller->getKey(),
            'trx_buyer_type' => 'customer',
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'retail',
            'trx_reference_id' => 0,
            'trx_total_price' => $total,
            'trx_discount' => 0,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => $total,
            'trx_shipping_cost' => $shippingCost,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => $total + $shippingCost,
            'trx_bill_remaining' => 0,
            'trx_bill_augment' => 0,
            'trx_bill_amount' => $total + $shippingCost,
            'trx_payment_method' => 'cash',
            'trx_shipping_method' => $shippingMethod,
            'trx_status' => $status,
            'trx_status_datetime' => $orderedAt->copy()->addHour(),
            'trx_datetime' => $orderedAt,
        ]);
        $trx->save();
        $this->ensureDetails($trx, $products, false, $quantities);

        return $trx;
    }

    /** @param Collection<int, Product> $products */
    private function ensureDetails(
        Trx $trx,
        $products,
        bool $preorder,
        ?array $quantities = null,
    ): void {
        foreach ($products->take(2)->values() as $index => $product) {
            $quantity = $quantities[$index] ?? ($index === 0 ? 3 : 1);
            $unitPrice = (int) $product->product_customer_price;
            TrxDetail::query()->updateOrCreate(
                [
                    'trx_detail_trx_id' => $trx->getKey(),
                    'trx_detail_product_id' => $product->getKey(),
                ],
                [
                    'trx_detail_product_plan_id' => 0,
                    'trx_detail_product_type' => 'stock',
                    'trx_detail_product_code' => $product->product_code,
                    'trx_detail_product_name' => $product->product_name,
                    'trx_detail_product_bpom_number' => $product->product_bpom_number,
                    'trx_detail_product_price' => $unitPrice,
                    'trx_detail_product_weight' => (int) $product->product_weight,
                    'trx_detail_product_length' => (int) $product->product_length,
                    'trx_detail_product_height' => (int) $product->product_height,
                    'trx_detail_product_width' => (int) $product->product_width,
                    'trx_detail_discount_percent' => 0,
                    'trx_detail_discount_value' => 0,
                    'trx_detail_nett_price' => $unitPrice,
                    'trx_detail_qty' => $quantity,
                ],
            );
        }
    }

    private function payment(
        Trx $trx,
        string $status,
        CarbonInterface $transferredAt,
        ?Member $recipient = null,
    ): void {
        $bankAccount = $recipient
            ? MemberBankAccount::query()
                ->where('member_bank_account_member_id', $recipient->getKey())
                ->where('member_bank_account_is_active', 1)
                ->orderByDesc('member_bank_account_is_default')
                ->first()
            : null;

        TrxPaymentTransfer::query()->updateOrCreate(
            ['payment_transfer_trx_id' => $trx->getKey()],
            [
                'payment_transfer_bill_remaining' => 0,
                'payment_transfer_bill_augment' => 0,
                'payment_transfer_bill_amount' => $trx->trx_bill_amount,
                'payment_transfer_bank_id' => $bankAccount?->member_bank_account_bank_id ?? 1,
                'payment_transfer_account_name' => $bankAccount?->member_bank_account_name
                    ?? 'PT Deenye Berkah Abadi',
                'payment_transfer_account_number' => $bankAccount?->member_bank_account_number
                    ?? '880000000001',
                'payment_transfer_amount' => $trx->trx_bill_amount,
                'payment_transfer_datetime' => $transferredAt,
                'payment_transfer_receipt_file' => '/storage/media/development/dny-development.png',
                'payment_transfer_approval_status' => $status,
                'payment_transfer_approval_admin_id' => $status === 'approved' ? 1 : 0,
                'payment_transfer_approval_datetime' => $status === 'approved'
                    ? $transferredAt->copy()->addHour()
                    : null,
                'payment_transfer_note' => 'Pembayaran data development.',
            ],
        );
    }

    /** @param Collection<int, Product> $products */
    private function itemsTotal($products, ?array $quantities = null): int
    {
        return (int) $products->take(2)->values()->sum(
            fn (Product $product, int $index): int => (int) $product->product_customer_price
                * ($quantities[$index] ?? ($index === 0 ? 3 : 1)),
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{cost: int, name: string, service: string, type: string, etd: string, force_insurance: bool, insurance: int}
     */
    private function resolveCurrentExpressRate(
        StcGateway $stcGateway,
        Warehouse $warehouse,
        Member $member,
        Collection $products,
    ): array {
        if (DB::getDriverName() === 'sqlite'
            || blank(config('services.stc.token'))
            || blank(config('services.stc.client_id'))) {
            return $this->expressRate;
        }

        $destination = DB::table('member_address')
            ->where('member_address_member_id', $member->getKey())
            ->orderByDesc('member_address_is_default')
            ->orderBy('member_address_id')
            ->first();
        $selectedProducts = $products->take(2)->values();

        try {
            $payload = $stcGateway->expressShippingRates([
                'client_id' => (int) config('services.stc.client_id'),
                'item_value' => $this->itemsTotal($selectedProducts),
                'insurance' => 0,
                'origin' => (int) $warehouse->warehouse_district_id,
                'subdistrict_origin' => (int) $warehouse->warehouse_subdistrict_id,
                'destination' => (int) ($destination?->member_address_district_id ?? 0),
                'subdistrict_destination' => (int) ($destination?->member_address_subdistrict_id ?? 0),
                'length' => (int) $selectedProducts->max('product_length'),
                'height' => (int) $selectedProducts->max('product_height'),
                'width' => (int) $selectedProducts->max('product_width'),
                'weight' => (int) $selectedProducts->sum(
                    fn (Product $product, int $index): int => (int) $product->product_weight
                        * ($index === 0 ? 3 : 1),
                ),
                'courier' => ['jne', 'jnt'],
            ]);
            $rates = collect($payload['results'])
                ->filter(fn (array $item): bool => (int) ($item['cost'] ?? 0) > 0);
            $rate = $rates->firstWhere('group', 'regular') ?? $rates->sortBy('cost')->first();

            if (! is_array($rate)) {
                throw new LogicException('STC tidak mengembalikan pilihan tarif yang dapat digunakan.');
            }

            $this->command?->info('Ongkir development menggunakan pricing STC terbaru.');

            return [
                'cost' => (int) $rate['cost'],
                'name' => (string) ($rate['service'] ?? 'jne'),
                'service' => (string) ($rate['service_name'] ?? $rate['service'] ?? 'JNE Express Reguler'),
                'type' => (string) ($rate['service_type'] ?? 'REG'),
                'etd' => (string) ($rate['etd'] ?? '-'),
                'force_insurance' => (bool) ($rate['force_insurance'] ?? false),
                'insurance' => (int) ($rate['insurance'] ?? 0),
            ];
        } catch (Throwable $exception) {
            $this->command?->warn(
                "Pricing STC tidak tersedia ({$exception->getMessage()}); tarif fallback digunakan.",
            );

            return $this->expressRate;
        }
    }

    private function shippingCost(string $shippingMethod, int $fallback): int
    {
        if ($shippingMethod === 'pickup') {
            return 0;
        }

        if ($this->expressRate['cost'] <= 0) {
            return $fallback;
        }

        return $this->expressRate['cost']
            + ($this->expressRate['force_insurance'] ? $this->expressRate['insurance'] : 0);
    }

    private function today(CarbonInterface $now, int $minutesAgo): CarbonInterface
    {
        $startOfDay = $now->copy()->startOfDay();
        $datetime = $now->copy()->subMinutes($minutesAgo);

        return $datetime->lt($startOfDay) ? $startOfDay : $datetime;
    }

    /** @param Collection<int, Product> $products */
    private function rewardPurchases(
        Warehouse $warehouse,
        Member $distributor,
        Member $agent,
        Member $reseller,
        $products,
        CarbonInterface $now,
    ): void {
        foreach ([
            3 => ['distributor' => 350, 'agent' => 270, 'reseller' => 60],
            2 => ['distributor' => 400, 'agent' => 180, 'reseller' => 70],
            1 => ['distributor' => 450, 'agent' => 210, 'reseller' => 85],
        ] as $monthsAgo => $quantities) {
            $period = $now->copy()->subMonths($monthsAgo)->startOfMonth();
            $orderedAt = $period->copy()->addDays(9)->setTime(9, 0);
            $periodCode = $period->format('Ym');
            $purchases = [
                [
                    'code' => "DEV-RWD-DST-{$periodCode}",
                    'receive_code' => "RWD-DST-{$periodCode}",
                    'seller' => $warehouse,
                    'buyer' => $distributor,
                    'quantity' => $quantities['distributor'],
                    'ordered_at' => $orderedAt,
                ],
                [
                    'code' => "DEV-RWD-AGT-{$periodCode}",
                    'receive_code' => "RWD-AGT-{$periodCode}",
                    'seller' => $distributor,
                    'buyer' => $agent,
                    'quantity' => $quantities['agent'],
                    'ordered_at' => $orderedAt->copy()->addDays(2),
                ],
                [
                    'code' => "DEV-RWD-RSL-{$periodCode}",
                    'receive_code' => "RWD-RSL-{$periodCode}",
                    'seller' => $agent,
                    'buyer' => $reseller,
                    'quantity' => $quantities['reseller'],
                    'ordered_at' => $orderedAt->copy()->addDays(4),
                ],
            ];

            foreach ($purchases as $purchase) {
                $seller = $purchase['seller'];
                $buyer = $purchase['buyer'];
                $purchaseOrderedAt = $purchase['ordered_at'];
                $receivedAt = $purchaseOrderedAt->copy()->addDays(3);
                $shippingMethod = $seller instanceof Warehouse
                    ? 'courier_express'
                    : 'courier_manual';
                $trx = $this->transaction(
                    $purchase['code'],
                    $seller,
                    $buyer,
                    'completed',
                    $shippingMethod,
                    $products->take(1),
                    $purchaseOrderedAt,
                    quantities: [$purchase['quantity']],
                );

                $this->payment(
                    $trx,
                    'approved',
                    $purchaseOrderedAt->copy()->addDay(),
                    $seller instanceof Member ? $seller : null,
                );
                if ($shippingMethod === 'courier_express') {
                    $this->expressShipping($trx, $seller, $buyer, 'completed', $receivedAt);
                } else {
                    $this->manualShipping($trx, $seller, $buyer, 'completed', $receivedAt);
                }
                $this->memberGoodsReceive($trx, $purchase['receive_code'], $receivedAt);
            }
        }
    }

    /** @param Collection<int, Product> $products */
    private function preorderChain(
        Warehouse $warehouse,
        Member $distributor,
        Member $agent,
        Member $reseller,
        Collection $products,
        MemberPointService $memberPointService,
        CarbonInterface $now,
    ): void {
        $selectedProducts = $products->take(2)->values();
        $quantities = [6, 2];
        $orderedAt = $this->today($now, 35);

        $resellerOrder = $this->transaction(
            'DEV-PO-RSL-AGT',
            $agent,
            $reseller,
            'processing',
            'courier_manual',
            $selectedProducts,
            $orderedAt,
            preorder: true,
            quantities: $quantities,
        );
        $this->payment($resellerOrder, 'approved', $orderedAt->copy()->addMinutes(5), $agent);
        $memberPointService->recordForApprovedPayment($resellerOrder);

        $agentOrder = $this->transaction(
            'DEV-PO-AGT-DST',
            $distributor,
            $agent,
            'processing',
            'courier_manual',
            $selectedProducts,
            $orderedAt->copy()->addMinutes(10),
            preorder: true,
            quantities: $quantities,
            chainSourceCode: $resellerOrder->trx_code,
        );
        $agentOrder->update(['trx_parent_trx_id' => $resellerOrder->getKey()]);
        $this->payment($agentOrder, 'approved', $orderedAt->copy()->addMinutes(15), $distributor);
        $memberPointService->recordForApprovedPayment($agentOrder);

        $distributorOrder = $this->transaction(
            'DEV-PO-DST-CMP',
            $warehouse,
            $distributor,
            'processing',
            'courier_manual',
            $selectedProducts,
            $orderedAt->copy()->addMinutes(20),
            preorder: true,
            quantities: $quantities,
            chainSourceCode: $agentOrder->trx_code,
        );
        $distributorOrder->update(['trx_parent_trx_id' => $agentOrder->getKey()]);
        $this->payment($distributorOrder, 'approved', $orderedAt->copy()->addMinutes(25));
        $memberPointService->recordForApprovedPayment($distributorOrder);

        $this->manualShipping($resellerOrder, $agent, $reseller, 'pending', $orderedAt);
        $this->manualShipping($agentOrder, $distributor, $agent, 'pending', $orderedAt);
        $this->manualShipping($distributorOrder, $warehouse, $distributor, 'pending', $orderedAt);

        foreach ($selectedProducts as $index => $product) {
            $quantity = $quantities[$index];
            $warehouseStock = WarehouseStock::query()
                ->where('warehouse_stock_warehouse_id', $warehouse->getKey())
                ->where('warehouse_stock_product_id', $product->getKey())
                ->firstOrFail();
            $warehouseStock->update([
                'warehouse_stock_balance' => (int) $warehouseStock->warehouse_stock_balance - $quantity,
                'warehouse_stock_transfer_out' => (int) $warehouseStock->warehouse_stock_transfer_out + $quantity,
            ]);
            WarehouseStockLog::query()->create([
                'warehouse_stock_log_warehouse_id' => $warehouse->getKey(),
                'warehouse_stock_log_product_id' => $product->getKey(),
                'warehouse_stock_log_type' => 'out',
                'warehouse_stock_log_quantity' => $quantity,
                'warehouse_stock_log_unit_price' => (int) $product->product_customer_price,
                'warehouse_stock_log_balance' => (int) $warehouseStock->warehouse_stock_balance,
                'warehouse_stock_log_note' => "Alokasi checkout {$distributorOrder->trx_code}",
                'warehouse_stock_log_datetime' => $orderedAt,
            ]);

            $stock = MemberStock::query()->firstOrNew([
                'member_stock_member_id' => $reseller->getKey(),
                'member_stock_product_id' => $product->getKey(),
            ]);
            if (! $stock->exists) {
                $stock->fill([
                    'member_stock_balance' => 0,
                    'member_stock_transfer_in' => 0,
                    'member_stock_transfer_out' => 0,
                ])->save();
                MemberStockLog::query()->create([
                    'member_stock_log_member_id' => $reseller->getKey(),
                    'member_stock_log_product_id' => $product->getKey(),
                    'member_stock_log_type' => 'in',
                    'member_stock_log_quantity' => 0,
                    'member_stock_log_unit_price' => (int) $product->product_customer_price,
                    'member_stock_log_balance' => 0,
                    'member_stock_log_note' => 'Saldo awal stok PO development',
                    'member_stock_log_datetime' => $orderedAt,
                ]);
            }
            $stock->increment('member_stock_transfer_in', $quantity);
        }
    }

    /** @param Collection<int, Product> $products */
    private function upgradeRetailSales(
        Member $distributor,
        Member $agent,
        Member $reseller,
        Collection $products,
        CarbonInterface $now,
    ): void {
        foreach ([$distributor, $agent, $reseller] as $member) {
            $quantity = match ($this->memberType($member)) {
                'distributor' => 500,
                'agent' => 300,
                'reseller' => 60,
            };

            foreach (range(3, 0) as $monthsAgo) {
                $period = $now->copy()->subMonths($monthsAgo)->startOfMonth();
                $customer = $this->upgradeCustomer($member, $monthsAgo, $period);
                $trx = $this->sale(
                    "DEV-UPG-SALE-{$member->getKey()}-{$period->format('Ym')}",
                    $member,
                    $customer,
                    'received',
                    'pickup',
                    $products->take(1),
                    $period->copy()->addDays(14)->setTime(10, 0),
                    [$quantity],
                );
                $this->pickupShipping($trx, $member, 'completed', $trx->trx_datetime);
            }
        }
    }

    private function upgradeCustomer(
        Member $member,
        int $monthsAgo,
        CarbonInterface $period,
    ): Customer {
        $phone = '+62899'.str_pad((string) $member->getKey(), 6, '0', STR_PAD_LEFT).$monthsAgo;

        return Customer::query()->updateOrCreate(
            ['customer_whatsapp' => $phone],
            [
                'customer_member_id' => $member->getKey(),
                'customer_name' => "Pelanggan Upgrade {$member->member_code} {$period->format('Ym')}",
                'customer_phone' => $phone,
                'customer_gender' => 'P',
                'customer_birth_date' => '1995-01-01',
                'customer_address' => $member->member_address,
                'customer_subdistrict_id' => $member->member_subdistrict_id,
                'customer_district_id' => $member->member_district_id,
                'customer_city_id' => $member->member_city_id,
                'customer_province_id' => $member->member_province_id,
                'customer_is_deleted' => 0,
                'customer_created_datetime' => $period->copy()->addDay(),
            ],
        );
    }

    private function memberType(Member $member): string
    {
        $member->loadMissing('level');

        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => throw new LogicException('Level member transaksi development tidak didukung.'),
        };
    }

    private function developmentTransaction(string $key): ?Trx
    {
        $suffixes = collect([$key, substr($key, 0, 20)])
            ->map(fn (string $value): string => TransactionCodeService::deterministicUniqueSuffix($value))
            ->unique();

        return Trx::query()->where(function ($query) use ($suffixes): void {
            foreach ($suffixes as $suffix) {
                $query->orWhere('trx_code', 'like', '%/'.$suffix);
            }
        })->first();
    }

    private function expressShipping(
        Trx $trx,
        Warehouse|Member $origin,
        Member|Customer $destination,
        string $status,
        CarbonInterface $now,
    ): void {
        $shipping = $this->saveExpressShipping($trx, $origin, $destination, [
            'shipping_courier_express_order_id' => 'DEV-STC-'.$trx->getKey(),
            'shipping_courier_express_pickup_method' => 'PICKUP',
            'shipping_courier_express_pickup_number' => 'DEV-PICKUP-'.$trx->getKey(),
            'shipping_courier_express_schedule_datetime' => $now,
            'shipping_courier_express_awb' => 'DEV-EXP-'.$trx->getKey(),
        ]);

        ShippingCourierExpressStatus::query()->updateOrCreate(
            [
                'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
                'shipping_courier_express_status_value' => $status,
            ],
            [
                'shipping_courier_express_status_ref_type' => 'trx',
                'shipping_courier_express_status_ref_id' => $trx->getKey(),
                'shipping_courier_express_status_note' => 'Status STC express development.',
                'shipping_courier_express_status_datetime' => $now,
                'shipping_courier_express_status_ref_code' => 'DEV-EXP-'.$trx->getKey(),
                'shipping_courier_express_status_external_ref_code' => 'DEV-STC-'.$trx->getKey(),
            ],
        );
    }

    private function draftExpressShipping(
        Trx $trx,
        Warehouse|Member $origin,
        Member|Customer $destination,
    ): void {
        $this->saveExpressShipping($trx, $origin, $destination, [
            'shipping_courier_express_order_id' => '',
            'shipping_courier_express_pickup_method' => 'PICKUP',
            'shipping_courier_express_pickup_number' => '',
            'shipping_courier_express_schedule_datetime' => null,
            'shipping_courier_express_awb' => null,
        ]);
    }

    /** @param array<string, mixed> $state */
    private function saveExpressShipping(
        Trx $trx,
        Warehouse|Member $origin,
        Member|Customer $destination,
        array $state,
    ): ShippingCourierExpress {
        $originLocation = $this->locationData($this->partySubdistrict($origin));
        $destinationLocation = $this->locationData($this->partySubdistrict($destination));
        $package = $this->transactionPackage($trx);

        return ShippingCourierExpress::query()->updateOrCreate(
            [
                'shipping_courier_express_ref_type' => 'trx',
                'shipping_courier_express_ref_id' => $trx->getKey(),
            ],
            [
                'shipping_courier_express_type' => $this->expressRate['type'],
                'shipping_courier_express_expedition_name' => $this->expressRate['name'],
                'shipping_courier_express_expedition_service' => $this->expressRate['service'],
                'shipping_courier_express_etd' => $this->expressRate['etd'],
                'shipping_courier_express_cost' => $this->expressRate['cost'] > 0
                    ? $this->expressRate['cost']
                    : $trx->trx_shipping_cost,
                'shipping_courier_express_insurance_is_force' => $this->expressRate['force_insurance'],
                'shipping_courier_express_insurance' => $this->expressRate['insurance'],
                'shipping_courier_express_package_weight' => $package['weight'],
                'shipping_courier_express_package_length' => $package['length'],
                'shipping_courier_express_package_width' => $package['width'],
                'shipping_courier_express_package_height' => $package['height'],
                'shipping_courier_express_origin_name' => $this->partyName($origin),
                'shipping_courier_express_origin_phone' => $this->partyPhone($origin),
                'shipping_courier_express_origin_address' => $this->partyAddress($origin),
                'shipping_courier_express_origin_subdistrict_id' => $this->partySubdistrict($origin),
                'shipping_courier_express_origin_subdistrict_name' => $originLocation['subdistrict'],
                'shipping_courier_express_origin_district_name' => $originLocation['district'],
                'shipping_courier_express_origin_city_name' => $originLocation['city'],
                'shipping_courier_express_origin_province_name' => $originLocation['province'],
                'shipping_courier_express_origin_zipcode' => $originLocation['zip_code'],
                'shipping_courier_express_destination_name' => $this->partyName($destination),
                'shipping_courier_express_destination_phone' => $this->partyPhone($destination),
                'shipping_courier_express_destination_address' => $this->partyAddress($destination),
                'shipping_courier_express_destination_subdistrict_id' => $this->partySubdistrict($destination),
                'shipping_courier_express_destination_subdistrict_name' => $destinationLocation['subdistrict'],
                'shipping_courier_express_destination_district_name' => $destinationLocation['district'],
                'shipping_courier_express_destination_city_name' => $destinationLocation['city'],
                'shipping_courier_express_destination_province_name' => $destinationLocation['province'],
                'shipping_courier_express_destination_zipcode' => $destinationLocation['zip_code'],
                ...$state,
            ],
        );
    }

    /** @return array{weight: int, length: int, width: int, height: int} */
    private function transactionPackage(Trx $trx): array
    {
        $details = $trx->details()->get();

        return [
            'weight' => (int) $details->sum(
                fn (TrxDetail $detail): int => (int) $detail->trx_detail_product_weight
                    * (int) $detail->trx_detail_qty,
            ),
            'length' => (int) $details->max('trx_detail_product_length'),
            'width' => (int) $details->max('trx_detail_product_width'),
            'height' => (int) $details->sum(
                fn (TrxDetail $detail): int => (int) $detail->trx_detail_product_height
                    * (int) $detail->trx_detail_qty,
            ),
        ];
    }

    private function instantShipping(
        Trx $trx,
        Warehouse|Member $origin,
        Member|Customer $destination,
        string $status,
        CarbonInterface $now,
    ): void {
        $shipping = ShippingCourierInstant::query()->updateOrCreate(
            [
                'shipping_courier_instant_ref_type' => 'trx',
                'shipping_courier_instant_ref_id' => $trx->getKey(),
            ],
            [
                'shipping_courier_instant_type' => 'instant',
                'shipping_courier_instant_expedition_name' => 'Grab',
                'shipping_courier_instant_expedition_service' => 'Instant',
                'shipping_courier_instant_expedition_vehicle' => 'Motor',
                'shipping_courier_instant_estimation_hours' => '2 jam',
                'shipping_courier_instant_order_id' => 'DEV-INSTANT-'.$trx->getKey(),
                'shipping_courier_instant_awb' => 'DEV-INS-'.$trx->getKey(),
                'shipping_courier_instant_admin_fee' => 2_000,
                'shipping_courier_instant_cost' => $trx->trx_shipping_cost,
                'shipping_courier_instant_insurance' => 0,
                'shipping_courier_instant_package_weight' => 500,
                'shipping_courier_instant_origin_name' => $this->partyName($origin),
                'shipping_courier_instant_origin_phone' => $this->partyPhone($origin),
                'shipping_courier_instant_origin_address' => $this->partyAddress($origin),
                'shipping_courier_instant_origin_address_note' => 'Data development',
                'shipping_courier_instant_origin_latitude' => -7.2575,
                'shipping_courier_instant_origin_longitude' => 112.7521,
                'shipping_courier_instant_destination_name' => $this->partyName($destination),
                'shipping_courier_instant_destination_phone' => $this->partyPhone($destination),
                'shipping_courier_instant_destination_address' => $this->partyAddress($destination),
                'shipping_courier_instant_destination_address_note' => 'Data development',
                'shipping_courier_instant_destination_latitude' => -7.2600,
                'shipping_courier_instant_destination_longitude' => 112.7500,
            ],
        );

        ShippingCourierInstantStatus::query()->updateOrCreate(
            [
                'shipping_courier_instant_status_shipping_courier_instant_id' => $shipping->getKey(),
                'shipping_courier_instant_status_value' => $status,
            ],
            [
                'shipping_courier_instant_status_ref_type' => 'trx',
                'shipping_courier_instant_status_ref_id' => $trx->getKey(),
                'shipping_courier_instant_status_note' => 'Status kurir instan development.',
                'shipping_courier_instant_status_datetime' => $now,
                'shipping_courier_instant_status_ref_code' => 'DEV-INS-'.$trx->getKey(),
                'shipping_courier_instant_status_external_ref_code' => 'DEV-INSTANT-'.$trx->getKey(),
            ],
        );
    }

    private function manualShipping(
        Trx $trx,
        Warehouse|Member $origin,
        Member|Customer $destination,
        string $status,
        CarbonInterface $now,
    ): void {
        $originLocation = $this->locationData($this->partySubdistrict($origin));
        $destinationLocation = $this->locationData($this->partySubdistrict($destination));
        $shipping = ShippingCourierManual::query()->updateOrCreate(
            [
                'shipping_courier_manual_ref_type' => 'trx',
                'shipping_courier_manual_ref_id' => $trx->getKey(),
            ],
            [
                'shipping_courier_manual_name' => $this->expressRate['name'],
                'shipping_courier_manual_service' => $this->expressRate['service'],
                'shipping_courier_manual_type' => $this->expressRate['type'],
                'shipping_courier_manual_awb' => $status === 'pending'
                    ? null
                    : 'DEV-AWB-'.$trx->getKey(),
                'shipping_courier_manual_price' => $trx->trx_shipping_cost,
                'shipping_courier_manual_insurance' => 0,
                'shipping_courier_manual_package_weight' => 500,
                'shipping_courier_manual_package_dimension' => '20x15x10',
                'shipping_courier_manual_origin_name' => $this->partyName($origin),
                'shipping_courier_manual_origin_phone' => $this->partyPhone($origin),
                'shipping_courier_manual_origin_address' => $this->partyAddress($origin),
                'shipping_courier_manual_origin_subdistrict_id' => $this->partySubdistrict($origin),
                'shipping_courier_manual_origin_subdistrict_name' => $originLocation['subdistrict'],
                'shipping_courier_manual_origin_district_name' => $originLocation['district'],
                'shipping_courier_manual_origin_city_name' => $originLocation['city'],
                'shipping_courier_manual_origin_province_name' => $originLocation['province'],
                'shipping_courier_manual_origin_zipcode' => $originLocation['zip_code'],
                'shipping_courier_manual_destination_name' => $this->partyName($destination),
                'shipping_courier_manual_destination_phone' => $this->partyPhone($destination),
                'shipping_courier_manual_destination_address' => $this->partyAddress($destination),
                'shipping_courier_manual_destination_subdistrict_id' => $this->partySubdistrict($destination),
                'shipping_courier_manual_destination_subdistrict_name' => $destinationLocation['subdistrict'],
                'shipping_courier_manual_destination_district_name' => $destinationLocation['district'],
                'shipping_courier_manual_destination_city_name' => $destinationLocation['city'],
                'shipping_courier_manual_destination_province_name' => $destinationLocation['province'],
                'shipping_courier_manual_destination_zipcode' => $destinationLocation['zip_code'],
            ],
        );

        ShippingCourierManualStatus::query()->updateOrCreate(
            [
                'shipping_courier_manual_status_shipping_courier_manual_id' => $shipping->getKey(),
                'shipping_courier_manual_status_value' => $status,
            ],
            [
                'shipping_courier_manual_status_ref_type' => 'trx',
                'shipping_courier_manual_status_ref_id' => $trx->getKey(),
                'shipping_courier_manual_status_note' => 'Status pengiriman development.',
                'shipping_courier_manual_status_datetime' => $now,
            ],
        );
    }

    private function pickupShipping(
        Trx $trx,
        Warehouse|Member $seller,
        string $status,
        CarbonInterface $now,
    ): void {
        $pickup = ShippingPickup::query()->updateOrCreate(
            [
                'shipping_pickup_ref_type' => 'trx',
                'shipping_pickup_ref_id' => $trx->getKey(),
            ],
            [
                'shipping_pickup_seller_address' => $this->partyAddress($seller),
                'shipping_pickup_seller_name' => $this->partyName($seller),
                'shipping_pickup_seller_mobilephone' => $this->partyPhone($seller),
                'shipping_pickup_schedule_datetime' => $now->copy()->addDay(),
                'shipping_pickup_pin' => $trx->trx_buyer_type === 'customer' ? '' : '12345',
            ],
        );

        ShippingPickupStatus::query()->updateOrCreate(
            [
                'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                'shipping_pickup_status_value' => $status,
            ],
            [
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trx->getKey(),
                'shipping_pickup_status_datetime' => $now,
            ],
        );

        if ($status === 'completed') {
            $trx->loadMissing('details');
            foreach ($trx->details as $detail) {
                ShippingDetail::query()->updateOrCreate(
                    [
                        'shipping_detail_shipping_type' => 'pickup',
                        'shipping_detail_shipping_id' => $pickup->getKey(),
                        'shipping_detail_product_id' => $detail->trx_detail_product_id,
                    ],
                    [
                        'shipping_detail_batch_number' => 'DEV-BATCH-POS-'.$detail->trx_detail_product_id,
                        'shipping_detail_qty' => $detail->trx_detail_qty,
                        'shipping_detail_expire_date' => $trx->trx_buyer_type === 'customer'
                            ? null
                            : $now->copy()->addYear()->toDateString(),
                    ],
                );
            }
        }
    }

    private function memberGoodsReceive(
        Trx $trx,
        string $code,
        CarbonInterface $receivedAt,
    ): GoodsReceive {
        $trx->loadMissing('details.product');
        $key = "DEV-GR-{$code}";
        $receive = $this->developmentGoodsReceive($key) ?? new GoodsReceive;
        if (! $receive->exists) {
            $receive->goods_receive_number = $this->documentCodeService->next(
                DocumentCodeService::GOODS_RECEIVE,
                DocumentCodeService::deterministicUniqueSuffix($key),
            );
        }
        $receive->fill([
            'goods_receive_trx_id' => $trx->getKey(),
            'goods_receive_buyer_type' => $trx->trx_buyer_type,
            'goods_receive_buyer_id' => $trx->trx_buyer_id,
            'goods_receive_seller_type' => $trx->trx_seller_type,
            'goods_receive_seller_id' => $trx->trx_seller_id,
            'goods_receive_delivery_note_number' => "DEV-SJ-{$code}",
            'goods_receive_faktur_number' => "DEV-INV-{$code}",
            'goods_receive_status' => 'completed',
            'goods_receive_status_datetime' => $receivedAt,
            'goods_receive_created_datetime' => $receivedAt,
        ])->save();

        foreach ($trx->details as $index => $detail) {
            GoodsReceiveDetail::query()->updateOrCreate(
                [
                    'goods_receive_detail_receive_id' => $receive->getKey(),
                    'goods_receive_detail_product_id' => $detail->trx_detail_product_id,
                    'goods_receive_detail_batch_number' => "DEV-{$code}-BATCH-".($index + 1),
                ],
                [
                    'goods_receive_detail_expire_date' => $receivedAt->copy()->addYear()->toDateString(),
                    'goods_receive_detail_qty' => $detail->trx_detail_qty,
                    'goods_receive_detail_created_datetime' => $receivedAt,
                ],
            );
        }

        return $receive;
    }

    private function returns(
        GoodsReceive $submittedReceive,
        GoodsReceive $approvedReceive,
        Member $member,
        Warehouse $warehouse,
        Product $product,
    ): void {
        $submitted = $this->returnRecord(
            'DEV-RET-SUBMITTED',
            $submittedReceive,
            $member,
            $product,
            'submitted',
            $submittedReceive->goods_receive_status_datetime->copy()->addMinutes(5),
        );
        $waitingShipment = $this->returnRecord(
            'DEV-RET-APPROVED',
            $approvedReceive,
            $member,
            $product,
            'waiting_member_shipment',
            $approvedReceive->goods_receive_status_datetime->copy()->addMinutes(10),
        );
        $this->expressReturnShipping(
            $waitingShipment,
            $member,
            $warehouse,
            $waitingShipment->return_created_datetime,
        );
        $this->reserveWaitingReturnStock($waitingShipment, $member);
        unset($submitted);
    }

    private function reserveWaitingReturnStock(ReturnModel $return, Member $member): void
    {
        foreach ($return->details as $detail) {
            MemberStock::query()
                ->where('member_stock_member_id', $member->getKey())
                ->where('member_stock_product_id', $detail->return_detail_product_id)
                ->increment('member_stock_transfer_out', (int) $detail->return_detail_qty);
        }
    }

    private function returnRecord(
        string $code,
        GoodsReceive $receive,
        Member $member,
        Product $product,
        string $status,
        CarbonInterface $createdAt,
    ): ReturnModel {
        $receive->loadMissing('trx');
        $trx = $receive->trx;
        $receivedAt = $receive->goods_receive_status_datetime;

        if (! $trx || $receive->goods_receive_status !== 'completed' || ! $receivedAt) {
            throw new LogicException('Retur development wajib berasal dari transaksi yang sudah diterima.');
        }
        if ($createdAt->lt($receivedAt) || $createdAt->gt($receivedAt->copy()->addDays(3))) {
            throw new LogicException('Retur development wajib dibuat maksimal tiga hari setelah penerimaan.');
        }

        $return = $this->developmentReturn($code) ?? new ReturnModel;
        if (! $return->exists) {
            $return->return_code = $this->documentCodeService->next(
                DocumentCodeService::RETURN,
                DocumentCodeService::deterministicUniqueSuffix($code),
            );
        }
        $address = DB::table('member_address')
            ->where('member_address_member_id', $member->getKey())
            ->orderByDesc('member_address_is_default')
            ->orderBy('member_address_id')
            ->first();
        $approved = $status !== 'submitted';
        $return->fill([
            'return_goods_receive_id' => $receive->getKey(),
            'return_member_id' => $member->getKey(),
            'return_member_address_id' => (int) ($address?->member_address_id ?? 0),
            'return_pickup_name' => (string) ($address?->member_address_recipient ?? $member->member_name),
            'return_pickup_phone' => (string) ($address?->member_address_phone ?? $member->member_mobilephone),
            'return_pickup_address' => (string) ($address?->member_address_full ?? $member->member_address),
            'return_pickup_province_id' => (int) ($address?->member_address_province_id ?? $member->member_province_id),
            'return_pickup_city_id' => (int) ($address?->member_address_city_id ?? $member->member_city_id),
            'return_pickup_district_id' => (int) ($address?->member_address_district_id ?? $member->member_district_id),
            'return_pickup_subdistrict_id' => (int) ($address?->member_address_subdistrict_id ?? $member->member_subdistrict_id),
            'return_description' => "Produk pada {$receive->goods_receive_number} tidak sesuai pesanan.",
            'return_attachment_image_url_json' => ['/storage/media/development/dny-development.png'],
            'return_attachment_video_url' => null,
            'return_status' => $status,
            'return_shipping_cost_bearer' => 'warehouse',
            'return_shipping_method' => $approved ? 'courier_express' : null,
            'return_shipping_cost' => $approved
                ? $this->shippingCost('courier_express', 18_000)
                : 0,
            'return_approved_by' => $approved ? 1 : 0,
            'return_approved_datetime' => $approved ? $createdAt : null,
            'return_created_datetime' => $createdAt,
        ])->save();

        ReturnDetail::query()->updateOrCreate(
            [
                'return_detail_return_id' => $return->getKey(),
                'return_detail_product_id' => $product->getKey(),
            ],
            [
                'return_detail_goods_receive_detail_id' => GoodsReceiveDetail::query()
                    ->where('goods_receive_detail_receive_id', $receive->getKey())
                    ->where('goods_receive_detail_product_id', $product->getKey())
                    ->value('goods_receive_detail_id'),
                'return_detail_qty' => 1,
                'return_detail_received_qty' => in_array($status, [
                    'received_by_company',
                    'replacement_in_transit',
                    'replacement_shipping_failed',
                    'completed',
                ], true) ? 1 : 0,
                'return_detail_reason' => 'Produk tidak sesuai dengan pesanan yang diterima.',
            ],
        );
        ReturnStatusLog::query()->updateOrCreate(
            [
                'return_status_log_return_id' => $return->getKey(),
                'return_status_log_status' => $status,
            ],
            [
                'return_status_log_note' => "Retur berasal dari {$receive->goods_receive_number} untuk transaksi {$trx->trx_code}.",
                'return_status_log_created_by' => $approved ? 1 : $member->getKey(),
                'return_status_log_created_datetime' => $createdAt,
            ],
        );

        return $return;
    }

    private function expressReturnShipping(
        ReturnModel $return,
        Member $origin,
        Warehouse $destination,
        CarbonInterface $now,
    ): void {
        $originLocation = $this->locationData($this->partySubdistrict($origin));
        $destinationLocation = $this->locationData($this->partySubdistrict($destination));
        $shipping = ShippingCourierExpress::query()->updateOrCreate(
            [
                'shipping_courier_express_ref_type' => 'return_company',
                'shipping_courier_express_ref_id' => $return->getKey(),
            ],
            [
                'shipping_courier_express_type' => $this->expressRate['type'],
                'shipping_courier_express_expedition_name' => $this->expressRate['name'],
                'shipping_courier_express_expedition_service' => $this->expressRate['service'],
                'shipping_courier_express_etd' => $this->expressRate['etd'],
                'shipping_courier_express_order_id' => 'DEV-STC-RET-'.$return->getKey(),
                'shipping_courier_express_pickup_method' => 'PICKUP',
                'shipping_courier_express_pickup_number' => 'DEV-PICKUP-RET-'.$return->getKey(),
                'shipping_courier_express_schedule_datetime' => $now->copy()->addDay(),
                'shipping_courier_express_awb' => 'DEV-RET-AWB-'.$return->getKey(),
                'shipping_courier_express_cost' => $this->expressRate['cost'] > 0
                    ? $this->expressRate['cost']
                    : 18_000,
                'shipping_courier_express_insurance_is_force' => $this->expressRate['force_insurance'],
                'shipping_courier_express_insurance' => $this->expressRate['insurance'],
                'shipping_courier_express_package_weight' => 250,
                'shipping_courier_express_package_length' => 15,
                'shipping_courier_express_package_width' => 10,
                'shipping_courier_express_package_height' => 8,
                'shipping_courier_express_origin_name' => $this->partyName($origin),
                'shipping_courier_express_origin_phone' => $this->partyPhone($origin),
                'shipping_courier_express_origin_address' => $this->partyAddress($origin),
                'shipping_courier_express_origin_subdistrict_id' => $this->partySubdistrict($origin),
                'shipping_courier_express_origin_subdistrict_name' => $originLocation['subdistrict'],
                'shipping_courier_express_origin_district_name' => $originLocation['district'],
                'shipping_courier_express_origin_city_name' => $originLocation['city'],
                'shipping_courier_express_origin_province_name' => $originLocation['province'],
                'shipping_courier_express_origin_zipcode' => $originLocation['zip_code'],
                'shipping_courier_express_destination_name' => $this->partyName($destination),
                'shipping_courier_express_destination_phone' => $this->partyPhone($destination),
                'shipping_courier_express_destination_address' => $this->partyAddress($destination),
                'shipping_courier_express_destination_subdistrict_id' => $this->partySubdistrict($destination),
                'shipping_courier_express_destination_subdistrict_name' => $destinationLocation['subdistrict'],
                'shipping_courier_express_destination_district_name' => $destinationLocation['district'],
                'shipping_courier_express_destination_city_name' => $destinationLocation['city'],
                'shipping_courier_express_destination_province_name' => $destinationLocation['province'],
                'shipping_courier_express_destination_zipcode' => $destinationLocation['zip_code'],
            ],
        );

        ShippingCourierExpressStatus::query()->updateOrCreate(
            [
                'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
                'shipping_courier_express_status_value' => 'processed_packages',
            ],
            [
                'shipping_courier_express_status_ref_type' => 'return_company',
                'shipping_courier_express_status_ref_id' => $return->getKey(),
                'shipping_courier_express_status_note' => 'Kurir retur telah dipilih admin.',
                'shipping_courier_express_status_datetime' => $now,
                'shipping_courier_express_status_ref_code' => $return->return_code,
                'shipping_courier_express_status_external_ref_code' => 'DEV-STC-RET-'.$return->getKey(),
            ],
        );
    }

    private function partyName(Warehouse|Member|Customer $party): string
    {
        return $party instanceof Warehouse
            ? $party->warehouse_name
            : ($party instanceof Member ? $party->member_name : $party->customer_name);
    }

    private function partyPhone(Warehouse|Member|Customer $party): string
    {
        return $party instanceof Warehouse
            ? $party->warehouse_phone
            : ($party instanceof Member ? $party->member_mobilephone : $party->customer_phone);
    }

    private function partyAddress(Warehouse|Member|Customer $party): string
    {
        return $party instanceof Warehouse
            ? $party->warehouse_address
            : ($party instanceof Member ? ($party->member_address ?: 'Alamat member development') : $party->customer_address);
    }

    private function partySubdistrict(Warehouse|Member|Customer $party): int
    {
        if ($party instanceof Warehouse) {
            return (int) $party->warehouse_subdistrict_id;
        }

        if ($party instanceof Member) {
            return (int) ($party->member_subdistrict_id ?: DB::table('ref_subdistrict')->min('subdistrict_id'));
        }

        return (int) ($party->customer_subdistrict_id
            ?: DB::table('ref_subdistrict')->min('subdistrict_id'));
    }

    /** @return array{subdistrict: string, district: string, city: string, province: string, zip_code: string} */
    private function locationData(int $subdistrictId): array
    {
        $location = DB::table('ref_subdistrict as subdistrict')
            ->join(
                'ref_district as district',
                'district.district_id',
                '=',
                'subdistrict.subdistrict_district_id',
            )
            ->join('ref_city as city', 'city.city_id', '=', 'district.district_city_id')
            ->join('ref_province as province', 'province.province_id', '=', 'city.city_province_id')
            ->where('subdistrict.subdistrict_id', $subdistrictId)
            ->first([
                'subdistrict.subdistrict_name',
                'subdistrict.subdistrict_zip_code',
                'district.district_name',
                'city.city_name',
                'province.province_name',
            ]);

        if (! $location) {
            if (DB::getDriverName() === 'sqlite') {
                return [
                    'subdistrict' => 'Development',
                    'district' => 'Development',
                    'city' => 'Development',
                    'province' => 'Development',
                    'zip_code' => '00000',
                ];
            }

            throw new LogicException("Referensi kelurahan {$subdistrictId} tidak ditemukan.");
        }

        return [
            'subdistrict' => (string) $location->subdistrict_name,
            'district' => (string) $location->district_name,
            'city' => (string) $location->city_name,
            'province' => (string) $location->province_name,
            'zip_code' => (string) $location->subdistrict_zip_code,
        ];
    }

    private function developmentGoodsReceive(string $key): ?GoodsReceive
    {
        return GoodsReceive::query()
            ->where('goods_receive_number', $key)
            ->orWhere(
                'goods_receive_number',
                'like',
                '%/'.DocumentCodeService::deterministicUniqueSuffix($key),
            )
            ->first();
    }

    private function developmentReturn(string $key): ?ReturnModel
    {
        return ReturnModel::query()
            ->where('return_code', $key)
            ->orWhere(
                'return_code',
                'like',
                '%/'.DocumentCodeService::deterministicUniqueSuffix($key),
            )
            ->first();
    }
}
