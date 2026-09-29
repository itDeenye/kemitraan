<?php

namespace App\Services\Sales;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberBankAccount;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RefBank;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierExpressStatus;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierInstantStatus;
use App\Models\ShippingCourierManual;
use App\Models\ShippingDetail;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use App\Services\Inventory\StockAllocationService;
use App\Services\Notification\PartnershipEmailService;
use App\Services\Purchase\PreorderChainService;
use App\Services\Purchase\PreorderPaymentSequenceGuard;
use App\Services\Reward\MemberPointService;
use App\Services\Shipping\MemberShippingService;
use App\Services\Shipping\PickupVerificationService;
use App\Services\Transaction\TransactionCodeService;
use App\Support\MediaUrl;
use App\Support\ShippingInsurance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberSalesService
{
    public function __construct(
        private readonly MemberShippingService $shippingService,
        private readonly PickupVerificationService $pickupVerificationService,
        private readonly TransactionCodeService $transactionCodeService,
        private readonly StockAllocationService $stockAllocationService,
        private readonly PreorderChainService $preorderChainService,
        private readonly PreorderPaymentSequenceGuard $preorderPaymentSequenceGuard,
        private readonly MemberPointService $memberPointService,
        private readonly PartnershipEmailService $partnershipEmailService,
    ) {}

    /** @param array<string, mixed> $params */
    public function orders(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $trxTable = (new Trx)->getTable();
        $customerTable = (new Customer)->getTable();
        $memberTable = (new Member)->getTable();
        $detailTable = (new TrxDetail)->getTable();
        $productTable = (new Product)->getTable();
        $paymentTable = (new TrxPaymentTransfer)->getTable();
        $bankTable = (new RefBank)->getTable();
        $expressTable = (new ShippingCourierExpress)->getTable();
        $instantTable = (new ShippingCourierInstant)->getTable();
        $manualTable = (new ShippingCourierManual)->getTable();
        $pickupTable = (new ShippingPickup)->getTable();
        $pickupStatusTable = (new ShippingPickupStatus)->getTable();
        $defaultAddresses = DB::table('member_address')
            ->leftJoin('ref_subdistrict', 'ref_subdistrict.subdistrict_id', '=', 'member_address.member_address_subdistrict_id')
            ->leftJoin('ref_district', 'ref_district.district_id', '=', 'member_address.member_address_district_id')
            ->leftJoin('ref_city', 'ref_city.city_id', '=', 'member_address.member_address_city_id')
            ->leftJoin('ref_province', 'ref_province.province_id', '=', 'member_address.member_address_province_id')
            ->where('member_address_is_default', 1)
            ->select([
                'member_address.*',
                'ref_subdistrict.subdistrict_name',
                'ref_subdistrict.subdistrict_zip_code as postal_code',
                'ref_district.district_name',
                'ref_city.city_name',
                'ref_province.province_name',
            ]);
        $detailSummary = DB::table($detailTable)
            ->select("{$detailTable}.trx_detail_trx_id")
            ->selectRaw('COUNT(*) AS product_count')
            ->selectRaw("SUM({$detailTable}.trx_detail_qty) AS total_quantity")
            ->groupBy("{$detailTable}.trx_detail_trx_id");
        $firstDetail = DB::table($detailTable)
            ->select("{$detailTable}.trx_detail_trx_id")
            ->selectRaw("MIN({$detailTable}.trx_detail_id) AS first_detail_id")
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
        $childSummary = DB::table("{$trxTable} as child_trx")
            ->select('child_trx.trx_parent_trx_id')
            ->selectRaw('COUNT(*) AS child_count')
            ->where('child_trx.trx_parent_trx_id', '>', 0)
            ->groupBy('child_trx.trx_parent_trx_id');

        $table = DataTable::select([
            "{$trxTable}.trx_id as id",
            "{$trxTable}.trx_code as code",
            "{$trxTable}.trx_buyer_id as customer_id",
            "{$trxTable}.trx_buyer_type as buyer_type",
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            "{$trxTable}.trx_type as order_type",
            "{$trxTable}.trx_parent_trx_id as parent_transaction_id",
            "{$trxTable}.trx_is_preorder as is_preorder",
            "{$customerTable}.customer_name as customer_name",
            "{$customerTable}.customer_whatsapp as customer_whatsapp",
            "{$customerTable}.customer_phone as customer_phone",
            "{$customerTable}.customer_address as customer_address",
            "{$customerTable}.customer_subdistrict_id as customer_subdistrict_id",
            'customer_subdistrict.subdistrict_name as customer_subdistrict_name',
            'customer_subdistrict.subdistrict_zip_code as customer_postal_code',
            "{$customerTable}.customer_district_id as customer_district_id",
            'customer_district.district_name as customer_district_name',
            "{$customerTable}.customer_city_id as customer_city_id",
            'customer_city.city_name as customer_city_name',
            "{$customerTable}.customer_province_id as customer_province_id",
            'customer_province.province_name as customer_province_name',
            "{$memberTable}.member_name as buyer_member_name",
            "{$memberTable}.member_mobilephone as buyer_member_whatsapp",
            "{$memberTable}.member_code as buyer_member_code",
            'buyer_default_address.member_address_full as buyer_member_address',
            'buyer_default_address.member_address_subdistrict_id as buyer_member_subdistrict_id',
            'buyer_default_address.subdistrict_name as buyer_member_subdistrict_name',
            'buyer_default_address.postal_code as buyer_member_postal_code',
            'buyer_default_address.member_address_district_id as buyer_member_district_id',
            'buyer_default_address.district_name as buyer_member_district_name',
            'buyer_default_address.member_address_city_id as buyer_member_city_id',
            'buyer_default_address.city_name as buyer_member_city_name',
            'buyer_default_address.member_address_province_id as buyer_member_province_id',
            'buyer_default_address.province_name as buyer_member_province_name',
            'seller_member.member_code as seller_code',
            'seller_member.member_name as seller_name',
            'seller_member.member_mobilephone as seller_phone',
            'seller_default_address.member_address_full as seller_address',
            'seller_default_address.member_address_subdistrict_id as seller_subdistrict_id',
            'seller_default_address.subdistrict_name as seller_subdistrict_name',
            'seller_default_address.postal_code as seller_postal_code',
            'seller_default_address.member_address_district_id as seller_district_id',
            'seller_default_address.district_name as seller_district_name',
            'seller_default_address.member_address_city_id as seller_city_id',
            'seller_default_address.city_name as seller_city_name',
            'seller_default_address.member_address_province_id as seller_province_id',
            'seller_default_address.province_name as seller_province_name',
            "{$trxTable}.trx_total_price as product_total",
            "{$trxTable}.trx_discount as discount_percent",
            "{$trxTable}.trx_discount_value as total_discount",
            "{$trxTable}.trx_voucher_id as voucher_id",
            "{$trxTable}.trx_voucher_value as voucher_value",
            "{$trxTable}.trx_grand_total_price as after_discount",
            DB::raw("COALESCE(list_express.shipping_courier_express_cost, list_instant.shipping_courier_instant_cost, list_manual.shipping_courier_manual_price, {$trxTable}.trx_shipping_cost, 0) as shipping_cost"),
            DB::raw('COALESCE(list_express.shipping_courier_express_insurance, list_instant.shipping_courier_instant_insurance, list_manual.shipping_courier_manual_insurance, 0) as shipping_cost_insurance'),
            "{$trxTable}.trx_shipping_cost as shipping_cost_total",
            "{$trxTable}.trx_payment_charge as payment_charge",
            "{$trxTable}.trx_grand_total_nett_price as grand_total",
            "{$trxTable}.trx_bill_amount as bill_amount",
            "{$trxTable}.trx_payment_method as payment_method",
            "{$trxTable}.trx_shipping_method as shipping_method",
            "{$trxTable}.trx_status as status",
            "{$trxTable}.trx_status_datetime as status_at",
            "{$trxTable}.trx_datetime as ordered_at",
            'detail_summary.product_count as product_count',
            'detail_summary.total_quantity as total_quantity',
            'preview_detail.trx_detail_product_id as preview_product_id',
            'preview_detail.trx_detail_product_code as preview_product_code',
            'preview_detail.trx_detail_product_name as preview_product_name',
            DB::raw("COALESCE(NULLIF(preview_detail.trx_detail_product_bpom_number, ''), preview_product.product_bpom_number) as preview_product_bpom_number"),
            'preview_detail.trx_detail_nett_price as preview_product_price',
            'preview_detail.trx_detail_qty as preview_product_quantity',
            'preview_product.product_image as preview_product_image',
            "{$paymentTable}.payment_transfer_approval_status as payment_status",
            "{$paymentTable}.payment_transfer_id as payment_id",
            "{$paymentTable}.payment_transfer_bank_id as payment_bank_id",
            "{$bankTable}.bank_code as payment_bank_code",
            "{$bankTable}.bank_name as payment_bank_name",
            "{$paymentTable}.payment_transfer_account_name as payment_account_name",
            "{$paymentTable}.payment_transfer_account_number as payment_account_number",
            "{$paymentTable}.payment_transfer_bill_amount as payment_bill_amount",
            "{$paymentTable}.payment_transfer_amount as payment_amount",
            "{$paymentTable}.payment_transfer_receipt_file as payment_receipt_url",
            "{$paymentTable}.payment_transfer_note as payment_note",
            "{$paymentTable}.payment_transfer_datetime as payment_transferred_at",
            "{$paymentTable}.payment_transfer_approval_datetime as payment_verified_at",
            'pickup_summary.pickup_status as pickup_status',
            'child_summary.child_count as has_child_transaction',
        ])
            ->from($trxTable)
            ->leftJoin($customerTable, "{$customerTable}.customer_id = {$trxTable}.trx_buyer_id")
            ->leftJoin('ref_subdistrict as customer_subdistrict', "customer_subdistrict.subdistrict_id = {$customerTable}.customer_subdistrict_id")
            ->leftJoin('ref_district as customer_district', "customer_district.district_id = {$customerTable}.customer_district_id")
            ->leftJoin('ref_city as customer_city', "customer_city.city_id = {$customerTable}.customer_city_id")
            ->leftJoin('ref_province as customer_province', "customer_province.province_id = {$customerTable}.customer_province_id")
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$trxTable}.trx_buyer_id")
            ->leftJoinSub($defaultAddresses, 'buyer_default_address', "buyer_default_address.member_address_member_id = {$memberTable}.member_id")
            ->leftJoin("{$memberTable} as seller_member", "seller_member.member_id = {$trxTable}.trx_seller_id")
            ->leftJoinSub($defaultAddresses, 'seller_default_address', 'seller_default_address.member_address_member_id = seller_member.member_id')
            ->leftJoinSub($detailSummary, 'detail_summary', "detail_summary.trx_detail_trx_id = {$trxTable}.trx_id")
            ->leftJoinSub($firstDetail, 'first_detail', "first_detail.trx_detail_trx_id = {$trxTable}.trx_id")
            ->leftJoin("{$detailTable} as preview_detail", 'preview_detail.trx_detail_id = first_detail.first_detail_id')
            ->leftJoin("{$productTable} as preview_product", 'preview_product.product_id = preview_detail.trx_detail_product_id')
            ->leftJoin("{$expressTable} as list_express", "list_express.shipping_courier_express_ref_id = {$trxTable}.trx_id")
            ->leftJoin("{$instantTable} as list_instant", "list_instant.shipping_courier_instant_ref_id = {$trxTable}.trx_id")
            ->leftJoin("{$manualTable} as list_manual", "list_manual.shipping_courier_manual_ref_id = {$trxTable}.trx_id")
            ->leftJoin($paymentTable, "{$paymentTable}.payment_transfer_trx_id = {$trxTable}.trx_id")
            ->leftJoin($bankTable, "{$bankTable}.bank_id = {$paymentTable}.payment_transfer_bank_id")
            ->leftJoinSub($pickupSummary, 'pickup_summary', "pickup_summary.transaction_id = {$trxTable}.trx_id")
            ->leftJoinSub($childSummary, 'child_summary', "child_summary.trx_parent_trx_id = {$trxTable}.trx_id")
            ->where("{$trxTable}.trx_seller_id", $member->getKey())
            ->where("{$trxTable}.trx_seller_type", $this->memberType($member))
            ->where(function ($query) use ($trxTable): void {
                $query->where(function ($retail) use ($trxTable): void {
                    $retail->where("{$trxTable}.trx_buyer_type", 'customer')
                        ->where("{$trxTable}.trx_type", 'retail');
                })->orWhere(function ($stock) use ($trxTable): void {
                    $stock->whereIn("{$trxTable}.trx_buyer_type", ['distributor', 'agent', 'reseller'])
                        ->where("{$trxTable}.trx_type", 'stock');
                });
            })
            ->search(['code', 'customer_name', 'customer_whatsapp', 'buyer_member_name', 'buyer_member_whatsapp'])
            ->defaultSort('-id');

        if (($params['filter']['action'] ?? null) === 'ship') {
            $table->where("{$trxTable}.trx_status", 'processing')
                ->whereNull('child_summary.child_count');
        }

        return $table->get($params);
    }

    /** @return array<string, int> */
    public function orderSummary(MemberAccount $account): array
    {
        $member = $this->member($account);
        $trxTable = (new Trx)->getTable();
        $summary = Trx::query()
            ->where('trx_seller_id', $member->getKey())
            ->where('trx_seller_type', $this->memberType($member))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $retail): void {
                    $retail->where('trx_buyer_type', 'customer')
                        ->where('trx_type', 'retail');
                })->orWhere(function (Builder $stock): void {
                    $stock->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
                        ->where('trx_type', 'stock');
                });
            })
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment_approval' OR (trx_status = 'processing' AND NOT EXISTS (SELECT 1 FROM {$trxTable} AS child_trx WHERE child_trx.trx_parent_trx_id = {$trxTable}.trx_id)) THEN 1 END) as action_required")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment' THEN 1 END) as waiting_payment")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment_approval' THEN 1 END) as waiting_payment_approval")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'processing' THEN 1 END) as processing")
            ->selectRaw("COUNT(CASE WHEN trx_status IN ('shipped', 'reship_required', 'ready_to_pickup', 'received') THEN 1 END) as delivery")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'completed' THEN 1 END) as completed")
            ->selectRaw("COUNT(CASE WHEN trx_status IN ('cancelled', 'rejected') THEN 1 END) as cancelled")
            ->first();

        return collect((array) $summary)->map(fn (mixed $value): int => (int) $value)->all();
    }

    /** @param array<string, mixed> $params */
    public function products(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $stockTable = (new MemberStock)->getTable();
        $productTable = (new Product)->getTable();
        $categoryTable = (new ProductCategory)->getTable();

        return DataTable::select([
            "{$productTable}.product_id as id",
            "{$productTable}.product_product_category_id as category_id",
            "{$categoryTable}.product_category_name as category_name",
            "{$productTable}.product_code as code",
            "{$productTable}.product_name as name",
            "{$productTable}.product_image as image",
            "{$productTable}.product_customer_price as price",
            "{$productTable}.product_weight as weight",
            "{$productTable}.product_unit as unit",
        ])
            ->selectRaw(
                "{$stockTable}.member_stock_balance as available_stock"
            )
            ->from($stockTable)
            ->join('inner', $productTable, "{$productTable}.product_id = {$stockTable}.member_stock_product_id")
            ->join('inner', $categoryTable, "{$categoryTable}.product_category_id = {$productTable}.product_product_category_id")
            ->where("{$stockTable}.member_stock_member_id", $member->getKey())
            ->where("{$productTable}.product_is_publish", 1)
            ->where("{$productTable}.product_is_active", 1)
            ->where("{$productTable}.product_is_deleted", 0)
            ->where("{$categoryTable}.product_category_is_active", 1)
            ->where("{$stockTable}.member_stock_balance", '>', 0)
            ->search(['code', 'name', 'category_name'])
            ->defaultSort('name')
            ->get($params);
    }

    /** @return array<string, mixed> */
    public function options(MemberAccount $account): array
    {
        $member = $this->member($account)->loadMissing('level');
        $origin = $this->memberLocation($member);

        return [
            'seller' => [
                'id' => (int) $member->getKey(),
                'code' => $member->member_code,
                'name' => $member->member_name,
                'level' => $member->level?->member_level_name,
                'address' => $member->member_address,
                'origin' => [
                    ...$origin,
                    'latitude' => null,
                    'longitude' => null,
                ],
            ],
            'bank_accounts' => [],
            'payment_methods' => [
                ['code' => 'cash', 'name' => 'Tunai'],
            ],
            'shipping_methods' => $this->shippingMethods(),
        ];
    }

    /**
     * @param  array{search?: string|null, limit?: int|null}  $params
     * @return list<array<string, mixed>>
     */
    public function customerOptions(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $search = trim((string) ($params['search'] ?? ''));
        $limit = (int) ($params['limit'] ?? 10);

        return Customer::query()
            ->where('customer_member_id', $member->getKey())
            ->where('customer_is_deleted', 0)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $filter) use ($search): void {
                    $filter->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_whatsapp', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->latest('customer_id')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => $this->customerData($customer))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCustomer(MemberAccount $account, array $data): array
    {
        $member = $this->member($account);
        $customer = Customer::query()->create([
            'customer_member_id' => $member->getKey(),
            'customer_name' => $data['name'],
            'customer_whatsapp' => $data['whatsapp'],
            'customer_phone' => $data['phone'] ?? '',
            'customer_gender' => $data['gender'],
            'customer_birth_date' => $data['birth_date'] ?? null,
            'customer_address' => $data['address'],
            'customer_subdistrict_id' => $data['subdistrict_id'],
            'customer_district_id' => $data['district_id'],
            'customer_city_id' => $data['city_id'],
            'customer_province_id' => $data['province_id'],
            'customer_is_deleted' => 0,
            'customer_created_datetime' => now(),
        ]);

        return $this->customerData($customer);
    }

    public function order(MemberAccount $account, Trx $trx): Trx
    {
        $order = $this->orderQuery($this->member($account))->findOrFail($trx->getKey());
        $order = $this->preorderChainService->loadVisibleSalesChain($order);
        $order->setAttribute(
            'member_tracking_histories',
            $this->shippingService->trackingHistories($order),
        );

        return $order;
    }

    /** @param array<string, mixed> $data */
    public function checkout(MemberAccount $account, array $data): Trx
    {
        $member = $this->member($account)->loadMissing('level');

        return DB::transaction(function () use ($account, $member, $data): Trx {
            $this->ensurePosOrderContract($data['payment_method'], $data['shipping_method']);
            $customer = $this->customer($member, (int) $data['customer_id']);
            $items = collect($data['items']);
            $productIds = $items->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all();
            $products = Product::query()
                ->availableInCatalog()
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
            if ($products->count() !== count($productIds)) {
                throw new ProcessException('Salah satu produk tidak aktif atau tidak tersedia di katalog.');
            }

            $stocks = MemberStock::query()
                ->where('member_stock_member_id', $member->getKey())
                ->whereIn('member_stock_product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('member_stock_product_id');
            $details = [];
            $grossTotal = 0;
            $discountTotal = 0;

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                $stock = $stocks->get($productId);
                $available = $stock
                    ? (int) $stock->member_stock_balance
                    : 0;
                if ($available < $quantity) {
                    throw new ProcessException("Stok produk {$products->get($productId)->product_name} tidak mencukupi.");
                }

                $product = $products->get($productId);
                $price = (int) $product->product_customer_price;
                $discountPercent = 0.0;
                $discountValue = 0;
                $netPrice = max(0, $price - $discountValue);
                $grossTotal += $price * $quantity;
                $discountTotal += $discountValue * $quantity;
                $details[] = [
                    'trx_detail_product_id' => $productId,
                    'trx_detail_product_plan_id' => 0,
                    'trx_detail_product_type' => 'stock',
                    'trx_detail_product_code' => $product->product_code,
                    'trx_detail_product_name' => $product->product_name,
                    'trx_detail_product_bpom_number' => $product->product_bpom_number,
                    'trx_detail_product_price' => $price,
                    'trx_detail_product_weight' => $product->product_weight,
                    'trx_detail_product_length' => $product->product_length,
                    'trx_detail_product_height' => $product->product_height,
                    'trx_detail_product_width' => $product->product_width,
                    'trx_detail_discount_percent' => $discountPercent,
                    'trx_detail_discount_value' => $discountValue,
                    'trx_detail_nett_price' => $netPrice,
                    'trx_detail_qty' => $quantity,
                ];
            }

            if ($data['shipping_method'] === 'courier_express') {
                $origin = $this->memberLocation($member);
                $requestedDropOff = (bool) ($data['courier']['drop'] ?? false);
                $rate = collect($this->shippingService->expressRates($account, [
                    'origin' => [
                        'district_id' => (int) $origin['district_id'],
                        'subdistrict_id' => (int) $origin['subdistrict_id'],
                    ],
                    'destination' => [
                        'district_id' => (int) $customer->customer_district_id,
                        'subdistrict_id' => (int) $customer->customer_subdistrict_id,
                    ],
                    'couriers' => [$data['courier']['name']],
                    'items' => $items->all(),
                ], 'sale')['results'])->first(fn (array $candidate): bool => (string) $candidate['courier_code'] === (string) $data['courier']['name']
                    && in_array((string) $data['courier']['service'], [
                        (string) $candidate['courier_name'],
                        (string) $candidate['service_type'],
                    ], true)
                );
                if (! $rate) {
                    throw new ProcessException('Layanan kurir yang dipilih tidak tersedia lagi. Silakan cek ongkir kembali.');
                }
                if ($requestedDropOff && ! $rate['drop_off_available']) {
                    throw new ProcessException('Layanan kurir yang dipilih tidak mendukung pengantaran paket ke gerai kurir.');
                }
                $data['courier'] = [
                    'name' => $rate['courier_code'],
                    'service' => $rate['courier_name'],
                    'type' => $rate['service_type'],
                    'cost' => (int) $rate['cost'],
                    'etd' => $rate['etd'],
                    'drop' => $requestedDropOff,
                    'force_insurance' => ShippingInsurance::isForced($rate),
                    'insurance' => ShippingInsurance::amount($rate),
                ];
            } elseif ($data['shipping_method'] === 'courier_instant') {
                $origin = $this->memberLocation($member);
                $destination = $this->customerLocation($customer);
                $courier = $data['courier'];
                $rate = collect($this->shippingService->instantRates($account, [
                    'services' => [$courier['name']],
                    'vehicle' => $courier['vehicle'],
                    'origin' => [
                        'latitude' => $courier['origin_latitude'],
                        'longitude' => $courier['origin_longitude'],
                        'address' => $origin['address'],
                    ],
                    'destination' => [
                        'latitude' => $courier['destination_latitude'],
                        'longitude' => $courier['destination_longitude'],
                        'address' => $destination['address'],
                    ],
                    'items' => $items->all(),
                ], 'sale')['results'])->first(fn (array $candidate): bool => (string) $candidate['courier_code'] === (string) $courier['name']
                    && (string) $candidate['service_type'] === (string) $courier['service']
                );
                if (! $rate) {
                    throw new ProcessException(
                        'Layanan kurir instan yang dipilih tidak tersedia lagi. Silakan cek ongkir kembali.'
                    );
                }

                $data['courier'] = [
                    'name' => $rate['courier_code'],
                    'service' => $rate['service_type'],
                    'type' => 'instant',
                    'cost' => (int) $rate['cost'],
                    'vehicle' => $rate['vehicle'],
                    'etd' => $rate['estimation'],
                    'admin_fee' => $rate['admin_fee'],
                    'origin_latitude' => $courier['origin_latitude'],
                    'origin_longitude' => $courier['origin_longitude'],
                    'destination_latitude' => $courier['destination_latitude'],
                    'destination_longitude' => $courier['destination_longitude'],
                ];
            }

            $shippingCost = $data['shipping_method'] === 'pickup' ? 0 : (int) $data['courier']['cost'];
            $shippingExtra = match ($data['shipping_method']) {
                'courier_express', 'courier_manual' => ShippingInsurance::amount($data['courier']),
                'courier_instant' => (int) ($data['courier']['admin_fee'] ?? 0),
                default => 0,
            };
            $shippingCharge = $shippingCost + $shippingExtra;
            $afterDiscount = max(0, $grossTotal - $discountTotal);
            $billAmount = $afterDiscount + $shippingCharge;
            $this->ensureUnsignedIntegerTotals($grossTotal, $discountTotal, $billAmount);
            $paymentMethod = $data['payment_method'];
            $shippingMethod = $data['shipping_method'];
            $now = now();
            $sellerType = $this->memberType($member);
            $trx = Trx::query()->create([
                'trx_code' => $this->transactionCodeService->next($sellerType, 'customer'),
                'trx_parent_trx_id' => 0,
                'trx_is_preorder' => 0,
                'trx_seller_type' => $sellerType,
                'trx_seller_id' => $member->getKey(),
                'trx_buyer_type' => 'customer',
                'trx_buyer_id' => $customer->getKey(),
                'trx_type' => 'retail',
                'trx_reference_id' => 0,
                'trx_total_price' => $grossTotal,
                'trx_discount' => $grossTotal === 0 ? 0 : (int) round($discountTotal * 100 / $grossTotal),
                'trx_discount_value' => $discountTotal,
                'trx_grand_total_price' => $afterDiscount,
                'trx_shipping_cost' => $shippingCharge,
                'trx_payment_charge' => 0,
                'trx_grand_total_nett_price' => $billAmount,
                'trx_bill_remaining' => $paymentMethod === 'cash' ? 0 : $billAmount,
                'trx_bill_augment' => 0,
                'trx_bill_amount' => $billAmount,
                'trx_payment_method' => $paymentMethod,
                'trx_shipping_method' => $shippingMethod,
                'trx_status' => $paymentMethod === 'cash' ? 'processing' : 'waiting_payment',
                'trx_status_datetime' => $now,
                'trx_datetime' => $now,
            ]);

            $trx->details()->createMany($details);
            $trx->load('details');
            $this->stockAllocationService->reserve($trx);

            if ($paymentMethod === 'transfer') {
                $bank = MemberBankAccount::query()
                    ->whereKey($data['bank_account_id'])
                    ->where('member_bank_account_member_id', $member->getKey())
                    ->where('member_bank_account_is_active', 1)
                    ->lockForUpdate()
                    ->firstOrFail();
                TrxPaymentTransfer::query()->create([
                    'payment_transfer_trx_id' => $trx->getKey(),
                    'payment_transfer_bill_remaining' => $billAmount,
                    'payment_transfer_bill_augment' => 0,
                    'payment_transfer_bill_amount' => $billAmount,
                    'payment_transfer_bank_id' => $bank->member_bank_account_bank_id,
                    'payment_transfer_account_name' => $bank->member_bank_account_name,
                    'payment_transfer_account_number' => $bank->member_bank_account_number,
                    'payment_transfer_amount' => 0,
                    'payment_transfer_datetime' => $now,
                    'payment_transfer_receipt_file' => null,
                    'payment_transfer_approval_status' => 'pending',
                    'payment_transfer_approval_admin_id' => 0,
                    'payment_transfer_approval_datetime' => null,
                    'payment_transfer_note' => '',
                ]);
            }

            $this->createShipping($trx, $member, $customer, $products, $items, $data);

            $batches = $items->flatMap(fn (array $item): array => array_map(
                fn (array $batch): array => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $batch['quantity'],
                    'batch_number' => $batch['batch_number'],
                    'expiry_date' => $batch['expiry_date'],
                ],
                $item['batches'],
            ))->all();
            $this->completeCustomerPickup($trx, $batches);

            return $this->order($account, $trx);
        });
    }

    /** @param array<string, mixed> $data */
    public function approvePayment(MemberAccount $account, Trx $trx, array $data): Trx
    {
        return $this->updatePaymentApproval($account, $trx, 'approved', $data);
    }

    public function rejectPayment(MemberAccount $account, Trx $trx, string $note): Trx
    {
        return $this->updatePaymentApproval($account, $trx, 'rejected', ['note' => $note]);
    }

    /** @param array<string, mixed> $data */
    private function updatePaymentApproval(MemberAccount $account, Trx $trx, string $status, array $data): Trx
    {
        $member = $this->member($account);

        /** @var array{order: Trx, payment: TrxPaymentTransfer} $result */
        $result = DB::transaction(function () use ($account, $member, $trx, $status, $data): array {
            $lockedTrx = $this->orderQuery($member)->whereKey($trx->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedTrx->trx_payment_method !== 'transfer') {
                throw new ProcessException('Pesanan ini tidak dapat diverifikasi pembayarannya.');
            }
            if ($lockedTrx->trx_status === 'waiting_payment') {
                throw new ProcessException('Bukti pembayaran belum dikirim oleh pembeli.');
            }
            if ($lockedTrx->trx_status !== 'waiting_payment_approval') {
                throw new ProcessException('Pesanan ini tidak dapat diverifikasi pembayarannya.');
            }

            $payment = TrxPaymentTransfer::query()
                ->where('payment_transfer_trx_id', $lockedTrx->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($payment->payment_transfer_approval_status !== 'submitted') {
                throw new ProcessException('Pembayaran ini sudah pernah diverifikasi.');
            }
            $spread = $lockedTrx->trx_buyer_type === 'distributor'
                ? TrxSpreadPayment::query()
                    ->where('trx_spread_payment_trx_id', $lockedTrx->getKey())
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($status === 'approved') {
                $this->preorderPaymentSequenceGuard->ensurePreviousPaymentsApproved($lockedTrx);
                $amount = (int) $payment->payment_transfer_amount;
                if ($amount === 0) {
                    $amount = (int) $lockedTrx->trx_bill_amount;
                }
                if ($amount !== (int) $lockedTrx->trx_bill_amount) {
                    throw new ProcessException('Nominal transfer harus sama dengan total tagihan pesanan.');
                }
                $receiptUrl = MediaUrl::canonicalPrivateUrl(
                    $data['receipt_url'] ?? $payment->payment_transfer_receipt_file,
                    'member',
                );
                if (blank($receiptUrl)) {
                    throw new ProcessException('Bukti pembayaran belum tersedia.');
                }
                $payment->update([
                    'payment_transfer_amount' => $amount,
                    'payment_transfer_datetime' => $payment->payment_transfer_receipt_file
                        ? $payment->payment_transfer_datetime
                        : now(),
                    'payment_transfer_receipt_file' => $receiptUrl,
                    'payment_transfer_approval_status' => 'approved',
                    'payment_transfer_approval_datetime' => now(),
                    'payment_transfer_note' => $data['note'] ?? '',
                ]);
                if ($spread) {
                    $spreadReceiptUrl = MediaUrl::canonicalPrivateUrl(
                        $data['spread_receipt_url'] ?? $spread->trx_spread_payment_receipt_file,
                        'member',
                    );
                    if (blank($spreadReceiptUrl)) {
                        throw new ProcessException('Bukti spread payment belum tersedia.');
                    }
                    $spread->update([
                        'trx_spread_payment_receipt_file' => $spreadReceiptUrl,
                        'trx_spread_payment_transfer_datetime' => $spread->trx_spread_payment_transfer_datetime
                            ?? now(),
                        'trx_spread_payment_status' => 'approved',
                        'trx_spread_payment_approved_datetime' => now(),
                    ]);
                }
                $lockedTrx->update([
                    'trx_bill_remaining' => 0,
                    'trx_status' => 'processing',
                    'trx_status_datetime' => now(),
                ]);
                $this->preorderChainService->createNextOrder($lockedTrx);
                $this->memberPointService->recordForApprovedPayment($lockedTrx);
            } else {
                $payment->update([
                    'payment_transfer_approval_status' => 'rejected',
                    'payment_transfer_approval_datetime' => now(),
                    'payment_transfer_note' => $data['note'],
                ]);
                if ($spread) {
                    $spread->update(['trx_spread_payment_status' => 'rejected']);
                }
                $chainCancelled = $this->preorderChainService->cancelAfterTerminalPaymentRejection($lockedTrx);
                if (! $chainCancelled) {
                    if (! $lockedTrx->trx_is_preorder) {
                        $this->stockAllocationService->release($lockedTrx);
                    }
                    $lockedTrx->update([
                        'trx_status' => $lockedTrx->trx_is_preorder ? 'waiting_payment' : 'cancelled',
                        'trx_status_datetime' => now(),
                    ]);
                }
            }

            return [
                'order' => $this->order($account, $lockedTrx->refresh()),
                'payment' => $payment->refresh(),
            ];
        });
        $this->partnershipEmailService->sendPaymentReviewed($result['payment']);

        return $result['order'];
    }

    /** @param array<string, mixed> $data */
    public function ship(MemberAccount $account, Trx $trx, array $data): Trx
    {
        $member = $this->member($account);

        $order = $this->orderQuery($member)->whereKey($trx->getKey())->firstOrFail();
        $this->ensureOrderCanBeShipped($order);
        if ($order->trx_shipping_method === 'courier_express') {
            $lock = Cache::lock("member-sales-express-shipping:{$order->getKey()}", 30);
            if (! $lock->get()) {
                throw new ProcessException('Pengiriman pesanan sedang diproses. Silakan coba kembali.', 409);
            }

            try {
                $currentOrder = $this->orderQuery($member)
                    ->whereKey($order->getKey())
                    ->firstOrFail();
                $this->ensureOrderCanBeShipped($currentOrder);

                $shippedOrder = $this->shipExpress($account, $member, $currentOrder, $data);
            } finally {
                $lock->release();
            }

            $this->partnershipEmailService->sendOrderShipped($shippedOrder);

            return $shippedOrder;
        }
        if ($order->trx_shipping_method === 'courier_instant') {
            $lock = Cache::lock("member-sales-instant-shipping:{$order->getKey()}", 30);
            if (! $lock->get()) {
                throw new ProcessException('Pengiriman pesanan sedang diproses. Silakan coba kembali.', 409);
            }

            try {
                $currentOrder = $this->orderQuery($member)
                    ->whereKey($order->getKey())
                    ->firstOrFail();
                $this->ensureOrderCanBeShipped($currentOrder);

                $shippedOrder = $this->shipInstant(
                    $account,
                    $member,
                    $currentOrder,
                    $data['delivery_note_number'] ?? null,
                    $data['items'],
                );
            } finally {
                $lock->release();
            }

            $this->partnershipEmailService->sendOrderShipped($shippedOrder);

            return $shippedOrder;
        }

        $shippedOrder = DB::transaction(function () use ($account, $member, $trx, $data): Trx {
            $lockedTrx = $this->orderQuery($member)->whereKey($trx->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureOrderCanBeShipped($lockedTrx);

            if ($lockedTrx->trx_shipping_method === 'pickup' && $lockedTrx->trx_buyer_type === 'customer') {
                $this->completeCustomerPickup($lockedTrx, $data['items']);

                return $this->order($account, $lockedTrx->refresh());
            }

            $status = $lockedTrx->trx_buyer_type === 'customer'
                ? 'completed'
                : 'received';
            if ($lockedTrx->trx_shipping_method === 'pickup') {
                $pickup = $lockedTrx->shippingPickup;
                if (! $pickup) {
                    throw new ProcessException('Data pengambilan pesanan tidak ditemukan.');
                }
                $pickupAttributes = [
                    'shipping_pickup_schedule_datetime' => $data['pickup_schedule'] ?? null,
                ];
                if ($lockedTrx->trx_buyer_type !== 'customer') {
                    $pickupAttributes['shipping_pickup_delivery_note_number'] = $data['delivery_note_number'] ?? null;
                }
                $pickup->update($pickupAttributes);
                $this->syncShipmentDetails($pickup, $lockedTrx, $data['items']);
                ShippingPickupStatus::query()->create([
                    'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                    'shipping_pickup_status_ref_type' => 'trx',
                    'shipping_pickup_status_ref_id' => $lockedTrx->getKey(),
                    'shipping_pickup_status_value' => 'ready_to_pickup',
                    'shipping_pickup_status_datetime' => now(),
                ]);
                if ($lockedTrx->trx_buyer_type === 'customer') {
                    ShippingPickupStatus::query()->create([
                        'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                        'shipping_pickup_status_ref_type' => 'trx',
                        'shipping_pickup_status_ref_id' => $lockedTrx->getKey(),
                        'shipping_pickup_status_value' => 'picked_up',
                        'shipping_pickup_status_datetime' => now(),
                    ]);
                } else {
                    $this->pickupVerificationService->validateAndMarkPickedUp(
                        $lockedTrx,
                        $data['pickup_pin'] ?? null,
                        now(),
                    );
                }
            } elseif ($lockedTrx->trx_shipping_method === 'courier_manual') {
                if (! $lockedTrx->shippingManual) {
                    throw new ProcessException('Data pengiriman manual tidak ditemukan.');
                }
                $lockedTrx->shippingManual->update([
                    'shipping_courier_manual_awb' => $data['tracking_number'],
                    'shipping_courier_manual_delivery_note_number' => $data['delivery_note_number'] ?? null,
                ]);
                $this->syncShipmentDetails($lockedTrx->shippingManual, $lockedTrx, $data['items']);
            }

            $this->consumeStockForShipment($lockedTrx);
            if ($lockedTrx->trx_type === 'retail') {
                if ($lockedTrx->trx_shipping_method === 'pickup') {
                    $this->pickupVerificationService->markCompleted($lockedTrx, now());
                }
            }
            $lockedTrx->update(['trx_status' => $status, 'trx_status_datetime' => now()]);
            $this->preorderChainService->synchronizeFinalShipment($lockedTrx->refresh());

            return $this->order($account, $lockedTrx->refresh());
        });
        $this->partnershipEmailService->sendOrderShipped($shippedOrder);

        return $shippedOrder;
    }

    /** @param array<string, mixed> $data */
    private function shipExpress(
        MemberAccount $account,
        Member $member,
        Trx $trx,
        array $data,
    ): Trx {
        $this->validateShipmentItems($trx, $data['items']);
        $pickup = $this->shippingService->createExpressPickup(
            $trx,
            (string) $data['pickup_schedule'],
            (string) $data['pickup_method'],
        );

        return DB::transaction(function () use ($account, $member, $trx, $data, $pickup): Trx {
            $lockedTrx = $this->orderQuery($member)
                ->whereKey($trx->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedTrx->trx_status !== 'processing') {
                throw new ProcessException('Pesanan harus berstatus diproses sebelum dikirim.');
            }

            $shipping = ShippingCourierExpress::query()
                ->where('shipping_courier_express_ref_type', 'trx')
                ->where('shipping_courier_express_ref_id', $lockedTrx->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $shipping->update([
                'shipping_courier_express_order_id' => $pickup['order_id'],
                'shipping_courier_express_pickup_method' => $data['pickup_method'],
                'shipping_courier_express_pickup_number' => $pickup['pickup_number'],
                'shipping_courier_express_schedule_datetime' => $data['pickup_schedule'],
                'shipping_courier_express_awb' => $pickup['tracking_number'],
                'shipping_courier_express_delivery_note_number' => $data['delivery_note_number'] ?? null,
            ]);
            $this->syncShipmentDetails($shipping, $lockedTrx, $data['items']);
            ShippingCourierExpressStatus::query()->create([
                'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
                'shipping_courier_express_status_ref_type' => 'trx',
                'shipping_courier_express_status_ref_id' => $lockedTrx->getKey(),
                'shipping_courier_express_status_value' => 'processed_packages',
                'shipping_courier_express_status_note' => 'Pickup STC berhasil dibuat oleh member.',
                'shipping_courier_express_status_datetime' => now(),
                'shipping_courier_express_status_ref_code' => $lockedTrx->trx_code,
                'shipping_courier_express_status_external_ref_code' => $pickup['order_id']
                    ?: $pickup['pickup_number'],
            ]);
            $this->consumeStockForShipment($lockedTrx);
            $lockedTrx->update(['trx_status' => 'shipped', 'trx_status_datetime' => now()]);
            $this->preorderChainService->synchronizeFinalShipment($lockedTrx->refresh());

            return $this->order($account, $lockedTrx->refresh());
        });
    }

    private function shipInstant(
        MemberAccount $account,
        Member $member,
        Trx $trx,
        ?string $deliveryNoteNumber,
        array $items,
    ): Trx {
        $this->validateShipmentItems($trx, $items);
        $pickup = $this->shippingService->createInstantPickup($trx);

        return DB::transaction(function () use (
            $account,
            $member,
            $trx,
            $deliveryNoteNumber,
            $items,
            $pickup,
        ): Trx {
            $lockedTrx = $this->orderQuery($member)
                ->whereKey($trx->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedTrx->trx_status !== 'processing') {
                throw new ProcessException('Pesanan harus berstatus diproses sebelum dikirim.');
            }

            $shipping = ShippingCourierInstant::query()
                ->where('shipping_courier_instant_ref_type', 'trx')
                ->where('shipping_courier_instant_ref_id', $lockedTrx->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (filled($shipping->shipping_courier_instant_order_id)) {
                throw new ProcessException('Permintaan kurir instan untuk pesanan ini sudah pernah dibuat.');
            }
            $shipping->update([
                'shipping_courier_instant_order_id' => $pickup['order_id'],
                'shipping_courier_instant_awb' => $pickup['tracking_number'],
                'shipping_courier_instant_delivery_note_number' => $deliveryNoteNumber,
            ]);
            $this->syncShipmentDetails($shipping, $lockedTrx, $items);
            ShippingCourierInstantStatus::query()->create([
                'shipping_courier_instant_status_shipping_courier_instant_id' => $shipping->getKey(),
                'shipping_courier_instant_status_ref_type' => 'trx',
                'shipping_courier_instant_status_ref_id' => $lockedTrx->getKey(),
                'shipping_courier_instant_status_value' => 'processed_packages',
                'shipping_courier_instant_status_note' => 'Pickup instan STC berhasil dibuat oleh member.',
                'shipping_courier_instant_status_datetime' => now(),
                'shipping_courier_instant_status_ref_code' => $lockedTrx->trx_code,
                'shipping_courier_instant_status_external_ref_code' => $pickup['order_id'],
            ]);
            $this->consumeStockForShipment($lockedTrx);
            $lockedTrx->update(['trx_status' => 'shipped', 'trx_status_datetime' => now()]);
            $this->preorderChainService->synchronizeFinalShipment($lockedTrx->refresh());

            return $this->order($account, $lockedTrx->refresh());
        });
    }

    public function cancel(MemberAccount $account, Trx $trx): Trx
    {
        $member = $this->member($account);

        return DB::transaction(function () use ($account, $member, $trx): Trx {
            $lockedTrx = $this->orderQuery($member)->whereKey($trx->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedTrx->trx_is_preorder) {
                $this->preorderChainService->cancelFromTerminalSeller($lockedTrx);

                return $this->order($account, $lockedTrx->refresh());
            }
            if ($lockedTrx->trx_status !== 'waiting_payment') {
                throw new ProcessException('Hanya pesanan yang menunggu pembayaran yang dapat dibatalkan.');
            }

            $this->stockAllocationService->release($lockedTrx);
            $lockedTrx->update(['trx_status' => 'cancelled', 'trx_status_datetime' => now()]);

            return $this->order($account, $lockedTrx->refresh());
        });
    }

    private function member(MemberAccount $account): Member
    {
        $account->loadMissing('member.level');
        $member = $account->member;
        if (! $member || (int) $member->member_status !== 1) {
            throw new ProcessException('Data mitra aktif tidak ditemukan.', 403);
        }

        return $member;
    }

    private function customer(Member $member, int $customerId): Customer
    {
        return Customer::query()
            ->whereKey($customerId)
            ->where('customer_member_id', $member->getKey())
            ->where('customer_is_deleted', 0)
            ->firstOrFail();
    }

    private function consumeStockForShipment(Trx $trx): void
    {
        $this->stockAllocationService->consume($trx);
    }

    /** @param list<array<string, mixed>> $items */
    private function completeCustomerPickup(Trx $trx, array $items): void
    {
        $pickup = $trx->shippingPickup;
        if (! $pickup) {
            throw new ProcessException('Data pengambilan pesanan tidak ditemukan.');
        }

        $this->syncShipmentDetails($pickup, $trx, $items);
        foreach (['ready_to_pickup', 'picked_up'] as $status) {
            ShippingPickupStatus::query()->create([
                'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trx->getKey(),
                'shipping_pickup_status_value' => $status,
                'shipping_pickup_status_datetime' => now(),
            ]);
        }

        $this->consumeStockForShipment($trx);
        $this->pickupVerificationService->markCompleted($trx, now());
        $trx->update(['trx_status' => 'completed', 'trx_status_datetime' => now()]);
    }

    /**
     * @param  EloquentCollection<int, Product>  $products
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     */
    private function createShipping(
        Trx $trx,
        Member $seller,
        Customer $customer,
        EloquentCollection $products,
        Collection $items,
        array $data,
    ): void {
        $origin = $this->memberLocation($seller);
        $shippingMethod = $data['shipping_method'];

        if ($shippingMethod === 'pickup') {
            $pickup = ShippingPickup::query()->create([
                'shipping_pickup_ref_type' => 'trx',
                'shipping_pickup_ref_id' => $trx->getKey(),
                'shipping_pickup_seller_address' => $origin['address'],
                'shipping_pickup_seller_name' => Str::substr((string) $origin['name'], 0, 50),
                'shipping_pickup_seller_mobilephone' => Str::substr((string) $origin['phone'], 0, 16),
                'shipping_pickup_schedule_datetime' => null,
                'shipping_pickup_pin' => '',
            ]);
            ShippingPickupStatus::query()->create([
                'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trx->getKey(),
                'shipping_pickup_status_value' => 'pending',
                'shipping_pickup_status_datetime' => now(),
            ]);

            return;
        }

        $destination = $this->customerLocation($customer);
        $package = $this->package($products, $items);
        $courier = $data['courier'];

        if ($shippingMethod === 'courier_express') {
            ShippingCourierExpress::query()->create([
                'shipping_courier_express_ref_type' => 'trx',
                'shipping_courier_express_ref_id' => $trx->getKey(),
                'shipping_courier_express_type' => $courier['type'] ?? '',
                'shipping_courier_express_expedition_name' => $courier['name'],
                'shipping_courier_express_expedition_service' => $courier['service'],
                'shipping_courier_express_etd' => $courier['etd'] ?? '',
                'shipping_courier_express_order_id' => '',
                'shipping_courier_express_pickup_method' => ($courier['drop'] ?? false) ? 'DROP-OFF' : 'PICKUP',
                'shipping_courier_express_pickup_number' => '',
                'shipping_courier_express_schedule_datetime' => null,
                'shipping_courier_express_awb' => null,
                'shipping_courier_express_cost' => $courier['cost'],
                'shipping_courier_express_insurance_is_force' => ShippingInsurance::isForced($courier),
                'shipping_courier_express_insurance' => ShippingInsurance::amount($courier),
                'shipping_courier_express_package_weight' => $package['weight'],
                'shipping_courier_express_package_length' => $package['length'],
                'shipping_courier_express_package_width' => $package['width'],
                'shipping_courier_express_package_height' => $package['height'],
                ...$this->regionalFields('shipping_courier_express', $origin, $destination),
            ]);

            return;
        }

        if ($shippingMethod === 'courier_instant') {
            ShippingCourierInstant::query()->create([
                'shipping_courier_instant_ref_type' => 'trx',
                'shipping_courier_instant_ref_id' => $trx->getKey(),
                'shipping_courier_instant_type' => $courier['type'] ?? 'instant',
                'shipping_courier_instant_expedition_name' => $courier['name'],
                'shipping_courier_instant_expedition_service' => $courier['service'],
                'shipping_courier_instant_expedition_vehicle' => $courier['vehicle'],
                'shipping_courier_instant_estimation_hours' => $courier['etd'] ?? '',
                'shipping_courier_instant_order_id' => '',
                'shipping_courier_instant_awb' => null,
                'shipping_courier_instant_admin_fee' => $courier['admin_fee'] ?? 0,
                'shipping_courier_instant_cost' => $courier['cost'],
                'shipping_courier_instant_insurance' => 0,
                'shipping_courier_instant_package_weight' => $package['weight'],
                'shipping_courier_instant_origin_name' => Str::substr((string) $origin['name'], 0, 50),
                'shipping_courier_instant_origin_phone' => Str::substr((string) $origin['phone'], 0, 16),
                'shipping_courier_instant_origin_address' => $origin['address'],
                'shipping_courier_instant_origin_address_note' => '',
                'shipping_courier_instant_origin_latitude' => $courier['origin_latitude'],
                'shipping_courier_instant_origin_longitude' => $courier['origin_longitude'],
                'shipping_courier_instant_destination_name' => Str::substr((string) $destination['name'], 0, 50),
                'shipping_courier_instant_destination_phone' => Str::substr((string) $destination['phone'], 0, 16),
                'shipping_courier_instant_destination_address' => $destination['address'],
                'shipping_courier_instant_destination_address_note' => '',
                'shipping_courier_instant_destination_latitude' => $courier['destination_latitude'],
                'shipping_courier_instant_destination_longitude' => $courier['destination_longitude'],
            ]);

            return;
        }

        ShippingCourierManual::query()->create([
            'shipping_courier_manual_ref_type' => 'trx',
            'shipping_courier_manual_ref_id' => $trx->getKey(),
            'shipping_courier_manual_name' => $courier['name'],
            'shipping_courier_manual_service' => $courier['service'],
            'shipping_courier_manual_type' => $courier['type'] ?? '',
            'shipping_courier_manual_awb' => null,
            'shipping_courier_manual_price' => (int) $courier['cost'],
            'shipping_courier_manual_insurance' => ShippingInsurance::amount($courier),
            'shipping_courier_manual_package_weight' => $package['weight'],
            'shipping_courier_manual_package_dimension' => "{$package['length']}x{$package['width']}x{$package['height']}",
            ...$this->regionalFields('shipping_courier_manual', $origin, $destination),
        ]);
    }

    /** @return array<string, mixed> */
    private function memberLocation(Member $member): array
    {
        return $this->location([
            'name' => $member->member_name,
            'phone' => $member->member_mobilephone,
            'address' => $member->member_address ?? '',
            'province_id' => $member->member_province_id,
            'city_id' => $member->member_city_id,
            'district_id' => $member->member_district_id,
            'subdistrict_id' => $member->member_subdistrict_id,
        ]);
    }

    /** @return array<string, mixed> */
    private function customerLocation(Customer $customer): array
    {
        return $this->location([
            'name' => $customer->customer_name,
            'phone' => $customer->customer_whatsapp ?: $customer->customer_phone,
            'address' => $customer->customer_address,
            'province_id' => $customer->customer_province_id,
            'city_id' => $customer->customer_city_id,
            'district_id' => $customer->customer_district_id,
            'subdistrict_id' => $customer->customer_subdistrict_id,
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function location(array $data): array
    {
        $subdistrict = RefSubdistrict::query()->where('subdistrict_id', $data['subdistrict_id'])->first();

        return [
            ...$data,
            'province_name' => RefProvince::query()->where('province_id', $data['province_id'])->value('province_name') ?? '',
            'city_name' => RefCity::query()->where('city_id', $data['city_id'])->value('city_name') ?? '',
            'district_name' => RefDistrict::query()->where('district_id', $data['district_id'])->value('district_name') ?? '',
            'subdistrict_name' => $subdistrict?->subdistrict_name ?? '',
            'zipcode' => $subdistrict?->subdistrict_zip_code,
        ];
    }

    /** @param EloquentCollection<int, Product> $products
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{weight: int, length: int, width: int, height: int}
     */
    private function package(EloquentCollection $products, Collection $items): array
    {
        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;
        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            $quantity = (int) $item['quantity'];
            $weight += (int) $product->product_weight * $quantity;
            $length = max($length, (int) $product->product_length);
            $width = max($width, (int) $product->product_width);
            $height += (int) $product->product_height * $quantity;
        }

        return compact('weight', 'length', 'width', 'height');
    }

    /** @param array<string, mixed> $origin
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>
     */
    private function regionalFields(string $prefix, array $origin, array $destination): array
    {
        return [
            "{$prefix}_origin_name" => Str::substr((string) $origin['name'], 0, 50),
            "{$prefix}_origin_phone" => Str::substr((string) $origin['phone'], 0, 16),
            "{$prefix}_origin_address" => $origin['address'],
            "{$prefix}_origin_subdistrict_id" => (int) $origin['subdistrict_id'],
            "{$prefix}_origin_subdistrict_name" => Str::substr((string) $origin['subdistrict_name'], 0, 50),
            "{$prefix}_origin_district_name" => Str::substr((string) $origin['district_name'], 0, 50),
            "{$prefix}_origin_city_name" => Str::substr((string) $origin['city_name'], 0, 50),
            "{$prefix}_origin_province_name" => Str::substr((string) $origin['province_name'], 0, 50),
            "{$prefix}_origin_zipcode" => $origin['zipcode'] ? Str::substr((string) $origin['zipcode'], 0, 5) : null,
            "{$prefix}_destination_name" => Str::substr((string) $destination['name'], 0, 50),
            "{$prefix}_destination_phone" => Str::substr((string) $destination['phone'], 0, 16),
            "{$prefix}_destination_address" => $destination['address'],
            "{$prefix}_destination_subdistrict_id" => (int) $destination['subdistrict_id'],
            "{$prefix}_destination_subdistrict_name" => Str::substr((string) $destination['subdistrict_name'], 0, 50),
            "{$prefix}_destination_district_name" => Str::substr((string) $destination['district_name'], 0, 50),
            "{$prefix}_destination_city_name" => Str::substr((string) $destination['city_name'], 0, 50),
            "{$prefix}_destination_province_name" => Str::substr((string) $destination['province_name'], 0, 50),
            "{$prefix}_destination_zipcode" => $destination['zipcode'] ? Str::substr((string) $destination['zipcode'], 0, 5) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function customerData(Customer $customer): array
    {
        return [
            'id' => (int) $customer->getKey(),
            'name' => $customer->customer_name,
            'whatsapp' => $customer->customer_whatsapp,
            'phone' => $customer->customer_phone,
            'gender' => $customer->customer_gender,
            'birth_date' => $customer->customer_birth_date?->toDateString(),
            'address' => $customer->customer_address,
            'province_id' => (int) $customer->customer_province_id,
            'city_id' => (int) $customer->customer_city_id,
            'district_id' => (int) $customer->customer_district_id,
            'subdistrict_id' => (int) $customer->customer_subdistrict_id,
        ];
    }

    private function transition(MemberAccount $account, Trx $trx, array $allowed, string $status): Trx
    {
        $member = $this->member($account);

        return DB::transaction(function () use ($account, $member, $trx, $allowed, $status): Trx {
            $lockedTrx = $this->orderQuery($member)->whereKey($trx->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($lockedTrx->trx_status, $allowed, true)) {
                throw new ProcessException('Status pesanan saat ini tidak dapat diproses ke tahap yang diminta.');
            }
            $lockedTrx->update(['trx_status' => $status, 'trx_status_datetime' => now()]);

            return $this->order($account, $lockedTrx->refresh());
        });
    }

    /** @param list<array<string, mixed>> $items */
    private function validateShipmentItems(Trx $trx, array $items): void
    {
        $expected = $trx->details()
            ->select(['trx_detail_product_id'])
            ->selectRaw('SUM(trx_detail_qty) AS quantity')
            ->groupBy('trx_detail_product_id')
            ->pluck('quantity', 'trx_detail_product_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->sortKeys();
        $actual = collect($items)
            ->groupBy('product_id')
            ->map(fn ($rows): int => (int) collect($rows)->sum('quantity'))
            ->sortKeys();
        if ($expected->all() !== $actual->all()) {
            throw new ProcessException('Jumlah produk dan batch pengiriman harus sesuai pesanan.');
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function syncShipmentDetails(Model $shipping, Trx $trx, array $items): void
    {
        $this->validateShipmentItems($trx, $items);
        $type = match ($shipping::class) {
            ShippingCourierExpress::class => 'courier_express',
            ShippingCourierInstant::class => 'courier_instant',
            ShippingCourierManual::class => 'courier_manual',
            default => 'pickup',
        };
        ShippingDetail::query()
            ->where('shipping_detail_shipping_type', $type)
            ->where('shipping_detail_shipping_id', $shipping->getKey())
            ->delete();
        ShippingDetail::query()->insert(collect($items)->map(fn (array $item): array => [
            'shipping_detail_shipping_type' => $type,
            'shipping_detail_shipping_id' => $shipping->getKey(),
            'shipping_detail_product_id' => $item['product_id'],
            'shipping_detail_batch_number' => $item['batch_number'],
            'shipping_detail_qty' => $item['quantity'],
            'shipping_detail_expire_date' => $item['expiry_date'] ?? null,
        ])->all());
    }

    private function orderQuery(Member $member): Builder
    {
        return Trx::query()
            ->withExists('children')
            ->with([
                'buyerCustomer.province',
                'buyerCustomer.city',
                'buyerCustomer.district',
                'buyerCustomer.subdistrict',
                'buyer.level',
                'buyer.defaultAddress.province',
                'buyer.defaultAddress.city',
                'buyer.defaultAddress.district',
                'buyer.defaultAddress.subdistrict',
                'seller.level',
                'seller.defaultAddress.province',
                'seller.defaultAddress.city',
                'seller.defaultAddress.district',
                'seller.defaultAddress.subdistrict',
                'details.product',
                'paymentTransfer.bank',
                'shippingExpress',
                'shippingExpress.latestStatus',
                'shippingInstant',
                'shippingInstant.latestStatus',
                'shippingManual',
                'shippingManual.latestStatus',
                'shippingPickup',
                'shippingPickup.latestStatus',
            ])
            ->where('trx_seller_id', $member->getKey())
            ->where('trx_seller_type', $this->memberType($member))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $retail): void {
                    $retail->where('trx_buyer_type', 'customer')
                        ->where('trx_type', 'retail');
                })->orWhere(function (Builder $stock): void {
                    $stock->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
                        ->where('trx_type', 'stock');
                });
            });
    }

    private function ensureOrderCanBeShipped(Trx $order): void
    {
        if ($order->trx_status !== 'processing') {
            throw new ProcessException('Pesanan harus berstatus diproses sebelum dikirim.');
        }

        if ($order->children()->exists()) {
            throw new ProcessException(
                'Pesanan PO ini sedang dipenuhi melalui pesanan pembelian Anda dan tidak perlu dikirim dari stok Anda.'
            );
        }
    }

    private function memberType(Member $member): string
    {
        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => throw new ProcessException('Tingkat kemitraan Anda tidak dikenali.'),
        };
    }

    private function ensureUnsignedIntegerTotals(int ...$values): void
    {
        if (collect($values)->contains(fn (int $value): bool => $value > 4294967295)) {
            throw new ProcessException('Nilai transaksi melebihi batas nominal yang dapat diproses.');
        }
    }

    /** @return list<array{code: string, name: string}> */
    private function shippingMethods(): array
    {
        return [
            ['code' => 'pickup', 'name' => 'Ambil di Tempat'],
        ];
    }

    private function ensurePosOrderContract(string $paymentMethod, string $shippingMethod): void
    {
        if ($paymentMethod !== 'cash') {
            throw new ProcessException('Metode pembayaran POS hanya tersedia secara tunai.');
        }
        if ($shippingMethod !== 'pickup') {
            throw new ProcessException('Metode penyerahan POS hanya tersedia ambil di tempat.');
        }
    }
}
