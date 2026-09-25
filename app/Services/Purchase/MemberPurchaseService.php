<?php

namespace App\Services\Purchase;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\BankCompany;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RefBank;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\RewardStockist;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierManual;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Inventory\StockAllocationService;
use App\Services\Notification\MemberNotificationService;
use App\Services\Notification\PartnershipEmailService;
use App\Services\Shipping\MemberShippingService;
use App\Services\Transaction\TransactionCodeService;
use App\Support\BusinessConfig;
use App\Support\MediaUrl;
use App\Support\ShippingInsurance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberPurchaseService
{
    public function __construct(
        private readonly MemberShippingService $shippingService,
        private readonly TransactionCodeService $transactionCodeService,
        private readonly StockAllocationService $stockAllocationService,
        private readonly PreorderChainService $preorderChainService,
        private readonly PreorderPaymentSequenceGuard $preorderPaymentSequenceGuard,
        private readonly PartnershipEmailService $partnershipEmailService,
        private readonly MemberNotificationService $memberNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination: array<string, mixed>}
     */
    public function orders(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $detailTable = (new TrxDetail)->getTable();
        $productTable = (new Product)->getTable();
        $paymentTable = (new TrxPaymentTransfer)->getTable();
        $bankTable = (new RefBank)->getTable();
        $pickupTable = (new ShippingPickup)->getTable();
        $pickupStatusTable = (new ShippingPickupStatus)->getTable();
        $defaultAddresses = DB::table('member_address')
            ->where('member_address_is_default', 1);
        $detailSummary = DB::table($detailTable)
            ->select("{$detailTable}.trx_detail_trx_id")
            ->selectRaw('COUNT(*) AS product_count')
            ->selectRaw("SUM({$detailTable}.trx_detail_qty) AS total_quantity")
            // Preorder quantity is derived from the transaction flag in the
            // resource; the detail subquery must remain independent so it can
            // be reused by the list query without an outer-table reference.
            ->selectRaw('0 AS preorder_quantity')
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

        return DataTable::select([
            "{$trxTable}.trx_id as id",
            "{$trxTable}.trx_code as code",
            "{$trxTable}.trx_type as order_type",
            "{$trxTable}.trx_parent_trx_id as parent_transaction_id",
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            'seller_member.member_code as seller_code',
            'seller_member.member_name as seller_name',
            "{$warehouseTable}.warehouse_name as warehouse_name",
            DB::raw("CASE WHEN {$trxTable}.trx_seller_type = 'warehouse' THEN {$warehouseTable}.warehouse_phone ELSE seller_member.member_mobilephone END AS seller_phone"),
            DB::raw("CASE WHEN {$trxTable}.trx_seller_type = 'warehouse' THEN {$warehouseTable}.warehouse_address ELSE seller_default_address.member_address_full END AS seller_address"),
            DB::raw("CASE WHEN {$trxTable}.trx_seller_type = 'warehouse' THEN {$warehouseTable}.warehouse_subdistrict_id ELSE seller_default_address.member_address_subdistrict_id END AS seller_subdistrict_id"),
            DB::raw("CASE WHEN {$trxTable}.trx_seller_type = 'warehouse' THEN {$warehouseTable}.warehouse_district_id ELSE seller_default_address.member_address_district_id END AS seller_district_id"),
            DB::raw("CASE WHEN {$trxTable}.trx_seller_type = 'warehouse' THEN {$warehouseTable}.warehouse_city_id ELSE seller_default_address.member_address_city_id END AS seller_city_id"),
            DB::raw("CASE WHEN {$trxTable}.trx_seller_type = 'warehouse' THEN {$warehouseTable}.warehouse_province_id ELSE seller_default_address.member_address_province_id END AS seller_province_id"),
            "{$trxTable}.trx_buyer_id as buyer_id",
            "{$trxTable}.trx_buyer_type as buyer_type",
            'buyer_member.member_code as buyer_code',
            'buyer_member.member_name as buyer_name',
            'buyer_member.member_mobilephone as buyer_phone',
            'buyer_default_address.member_address_full as buyer_address',
            'buyer_default_address.member_address_subdistrict_id as buyer_subdistrict_id',
            'buyer_default_address.member_address_district_id as buyer_district_id',
            'buyer_default_address.member_address_city_id as buyer_city_id',
            'buyer_default_address.member_address_province_id as buyer_province_id',
            "{$trxTable}.trx_is_preorder as is_preorder",
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
            'detail_summary.preorder_quantity as preorder_quantity',
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
            'parent_payment.payment_transfer_approval_status as parent_payment_status',
            'list_express.shipping_courier_express_origin_name as shipment_origin_name_express',
            'list_express.shipping_courier_express_origin_phone as shipment_origin_phone_express',
            'list_express.shipping_courier_express_origin_address as shipment_origin_address_express',
            'list_express.shipping_courier_express_origin_subdistrict_id as shipment_origin_subdistrict_id_express',
            'list_express.shipping_courier_express_origin_subdistrict_name as shipment_origin_subdistrict_name_express',
            'list_express.shipping_courier_express_origin_district_name as shipment_origin_district_name_express',
            'list_express.shipping_courier_express_origin_city_name as shipment_origin_city_name_express',
            'list_express.shipping_courier_express_origin_province_name as shipment_origin_province_name_express',
            'list_express.shipping_courier_express_origin_zipcode as shipment_origin_zipcode_express',
            'list_express.shipping_courier_express_destination_name as shipment_destination_name_express',
            'list_express.shipping_courier_express_destination_phone as shipment_destination_phone_express',
            'list_express.shipping_courier_express_destination_address as shipment_destination_address_express',
            'list_express.shipping_courier_express_destination_subdistrict_id as shipment_destination_subdistrict_id_express',
            'list_express.shipping_courier_express_destination_subdistrict_name as shipment_destination_subdistrict_name_express',
            'list_express.shipping_courier_express_destination_district_name as shipment_destination_district_name_express',
            'list_express.shipping_courier_express_destination_city_name as shipment_destination_city_name_express',
            'list_express.shipping_courier_express_destination_province_name as shipment_destination_province_name_express',
            'list_express.shipping_courier_express_destination_zipcode as shipment_destination_zipcode_express',
            'list_instant.shipping_courier_instant_origin_name as shipment_origin_name_instant',
            'list_instant.shipping_courier_instant_origin_phone as shipment_origin_phone_instant',
            'list_instant.shipping_courier_instant_origin_address as shipment_origin_address_instant',
            'list_instant.shipping_courier_instant_origin_address_note as shipment_origin_note_instant',
            'list_instant.shipping_courier_instant_origin_latitude as shipment_origin_latitude_instant',
            'list_instant.shipping_courier_instant_origin_longitude as shipment_origin_longitude_instant',
            'list_instant.shipping_courier_instant_destination_name as shipment_destination_name_instant',
            'list_instant.shipping_courier_instant_destination_phone as shipment_destination_phone_instant',
            'list_instant.shipping_courier_instant_destination_address as shipment_destination_address_instant',
            'list_instant.shipping_courier_instant_destination_address_note as shipment_destination_note_instant',
            'list_instant.shipping_courier_instant_destination_latitude as shipment_destination_latitude_instant',
            'list_instant.shipping_courier_instant_destination_longitude as shipment_destination_longitude_instant',
            'list_manual.shipping_courier_manual_origin_name as shipment_origin_name_manual',
            'list_manual.shipping_courier_manual_origin_phone as shipment_origin_phone_manual',
            'list_manual.shipping_courier_manual_origin_address as shipment_origin_address_manual',
            'list_manual.shipping_courier_manual_origin_subdistrict_id as shipment_origin_subdistrict_id_manual',
            'list_manual.shipping_courier_manual_origin_subdistrict_name as shipment_origin_subdistrict_name_manual',
            'list_manual.shipping_courier_manual_origin_district_name as shipment_origin_district_name_manual',
            'list_manual.shipping_courier_manual_origin_city_name as shipment_origin_city_name_manual',
            'list_manual.shipping_courier_manual_origin_province_name as shipment_origin_province_name_manual',
            'list_manual.shipping_courier_manual_origin_zipcode as shipment_origin_zipcode_manual',
            'list_manual.shipping_courier_manual_destination_name as shipment_destination_name_manual',
            'list_manual.shipping_courier_manual_destination_phone as shipment_destination_phone_manual',
            'list_manual.shipping_courier_manual_destination_address as shipment_destination_address_manual',
            'list_manual.shipping_courier_manual_destination_subdistrict_id as shipment_destination_subdistrict_id_manual',
            'list_manual.shipping_courier_manual_destination_subdistrict_name as shipment_destination_subdistrict_name_manual',
            'list_manual.shipping_courier_manual_destination_district_name as shipment_destination_district_name_manual',
            'list_manual.shipping_courier_manual_destination_city_name as shipment_destination_city_name_manual',
            'list_manual.shipping_courier_manual_destination_province_name as shipment_destination_province_name_manual',
            'list_manual.shipping_courier_manual_destination_zipcode as shipment_destination_zipcode_manual',
            'list_pickup.shipping_pickup_seller_name as shipment_origin_name_pickup',
            'list_pickup.shipping_pickup_seller_mobilephone as shipment_origin_phone_pickup',
            'list_pickup.shipping_pickup_seller_address as shipment_origin_address_pickup',
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
            ->from($trxTable)
            ->leftJoin("{$memberTable} as seller_member", "seller_member.member_id = {$trxTable}.trx_seller_id")
            ->leftJoinSub($defaultAddresses, 'seller_default_address', 'seller_default_address.member_address_member_id = seller_member.member_id')
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$trxTable}.trx_seller_id")
            ->leftJoin("{$memberTable} as buyer_member", "buyer_member.member_id = {$trxTable}.trx_buyer_id")
            ->leftJoinSub($defaultAddresses, 'buyer_default_address', 'buyer_default_address.member_address_member_id = buyer_member.member_id')
            ->leftJoinSub($detailSummary, 'detail_summary', "detail_summary.trx_detail_trx_id = {$trxTable}.trx_id")
            ->leftJoinSub($firstDetail, 'first_detail', "first_detail.trx_detail_trx_id = {$trxTable}.trx_id")
            ->leftJoin("{$detailTable} as preview_detail", 'preview_detail.trx_detail_id = first_detail.first_detail_id')
            ->leftJoin("{$productTable} as preview_product", 'preview_product.product_id = preview_detail.trx_detail_product_id')
            ->leftJoin($paymentTable, "{$paymentTable}.payment_transfer_trx_id = {$trxTable}.trx_id")
            ->leftJoin($bankTable, "{$bankTable}.bank_id = {$paymentTable}.payment_transfer_bank_id")
            ->leftJoin("{$trxTable} as parent_trx", "parent_trx.trx_id = {$trxTable}.trx_parent_trx_id")
            ->leftJoin("{$paymentTable} as parent_payment", 'parent_payment.payment_transfer_trx_id = parent_trx.trx_id')
            ->leftJoin('shipping_courier_express as list_express', 'list_express.shipping_courier_express_ref_id = '.$trxTable.'.trx_id')
            ->leftJoin('shipping_courier_instant as list_instant', 'list_instant.shipping_courier_instant_ref_id = '.$trxTable.'.trx_id')
            ->leftJoin('shipping_courier_manual as list_manual', 'list_manual.shipping_courier_manual_ref_id = '.$trxTable.'.trx_id')
            ->leftJoinSub($pickupSummary, 'pickup_summary', "pickup_summary.transaction_id = {$trxTable}.trx_id")
            ->leftJoin($pickupTable.' as list_pickup', 'list_pickup.shipping_pickup_ref_id = '.$trxTable.'.trx_id')
            ->where("{$trxTable}.trx_buyer_id", $member->getKey())
            ->whereIn("{$trxTable}.trx_buyer_type", ['distributor', 'agent', 'reseller'])
            ->where("{$trxTable}.trx_type", 'stock')
            ->whereRaw($this->visiblePurchaseOrderCondition($trxTable))
            ->search(['code', 'seller_code', 'seller_name', 'warehouse_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    /** @return array<string, int> */
    public function orderSummary(MemberAccount $account): array
    {
        $member = $this->member($account);
        $summary = Trx::query()
            ->where('trx_buyer_id', $member->getKey())
            ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
            ->where('trx_type', 'stock')
            ->whereRaw($this->visiblePurchaseOrderCondition((new Trx)->getTable()))
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_stock_screening' THEN 1 END) as waiting_stock_screening")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment'
                AND (trx_parent_trx_id = 0 OR EXISTS (
                    SELECT 1 FROM trx_payment_transfer previous_payment
                    WHERE previous_payment.payment_transfer_trx_id = trx_parent_trx_id
                        AND previous_payment.payment_transfer_approval_status = 'approved'
                )) THEN 1 END) as action_required")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment' THEN 1 END) as waiting_payment")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment_approval' THEN 1 END) as waiting_payment_approval")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'processing' THEN 1 END) as processing")
            ->selectRaw("COUNT(CASE WHEN trx_status IN ('shipped', 'reship_required', 'ready_to_pickup', 'received') THEN 1 END) as delivery")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'completed' THEN 1 END) as completed")
            ->selectRaw("COUNT(CASE WHEN trx_status IN ('cancelled', 'rejected') THEN 1 END) as cancelled")
            ->selectRaw("COUNT(CASE WHEN trx_parent_trx_id = 0
                AND goods_receive.goods_receive_id IS NULL AND (
                trx_status = 'received'
                OR (
                    trx_status = 'shipped'
                    AND trx_is_preorder = 0
                    AND (
                        trx_shipping_method = 'courier_manual'
                        OR (trx_shipping_method = 'pickup' AND EXISTS (
                            SELECT 1 FROM shipping_pickup ready_pickup
                            INNER JOIN shipping_pickup_status ready_pickup_status
                                ON ready_pickup_status.shipping_pickup_status_shipping_pickup_id = ready_pickup.shipping_pickup_id
                            WHERE ready_pickup.shipping_pickup_ref_type = 'trx'
                                AND ready_pickup.shipping_pickup_ref_id = trx.trx_id
                                AND ready_pickup_status.shipping_pickup_status_value = 'picked_up'
                        ))
                        OR (trx_shipping_method = 'courier_express' AND EXISTS (
                            SELECT 1 FROM shipping_courier_express ready_express
                            INNER JOIN shipping_courier_express_status ready_express_status
                                ON ready_express_status.shipping_courier_express_status_shipping_courier_express_id = ready_express.shipping_courier_express_id
                            WHERE ready_express.shipping_courier_express_ref_type = 'trx'
                                AND ready_express.shipping_courier_express_ref_id = trx.trx_id
                                AND ready_express_status.shipping_courier_express_status_value IN ('finished_packages', 'completed')
                        ))
                        OR (trx_shipping_method = 'courier_instant' AND EXISTS (
                            SELECT 1 FROM shipping_courier_instant ready_instant
                            INNER JOIN shipping_courier_instant_status ready_instant_status
                                ON ready_instant_status.shipping_courier_instant_status_shipping_courier_instant_id = ready_instant.shipping_courier_instant_id
                            WHERE ready_instant.shipping_courier_instant_ref_type = 'trx'
                                AND ready_instant.shipping_courier_instant_ref_id = trx.trx_id
                                AND ready_instant_status.shipping_courier_instant_status_value IN ('finished_packages', 'completed')
                        ))
                    )
                )
            ) THEN 1 END) as goods_receipt")
            ->leftJoin('goods_receive', 'goods_receive.goods_receive_trx_id', '=', 'trx.trx_id')
            ->first();

        return collect((array) $summary)->map(fn (mixed $value): int => (int) $value)->all();
    }

    private function visiblePurchaseOrderCondition(string $trxTable): string
    {
        // Tahap lanjutan PO dibuat saat checkout untuk menahan stok, tetapi
        // belum menjadi pesanan pembeli berikutnya sampai pembayaran tahap
        // sebelumnya disetujui. Riwayat pembatalan tetap terlihat.
        return "({$trxTable}.trx_is_preorder = 0
            OR {$trxTable}.trx_parent_trx_id = 0
            OR {$trxTable}.trx_status IN ('cancelled', 'rejected')
            OR EXISTS (
                SELECT 1 FROM trx_payment_transfer previous_payment
                WHERE previous_payment.payment_transfer_trx_id = {$trxTable}.trx_parent_trx_id
                    AND previous_payment.payment_transfer_approval_status = 'approved'
            ))";
    }

    public function order(MemberAccount $account, Trx $trx): Trx
    {
        $member = $this->member($account);
        $order = $this->orderQuery($member)->findOrFail($trx->getKey());
        $order = $this->preorderChainService->loadVisiblePurchaseChain($order);
        $order->setAttribute(
            'member_tracking_histories',
            $this->shippingService->trackingHistories($order),
        );

        return $order;
    }

    /** @return array<string, mixed> */
    public function checkoutOptions(MemberAccount $account, array $items = []): array
    {
        $member = $this->member($account)->loadMissing([
            'level',
            'parent.level',
            'parent.defaultAddress.province',
            'parent.defaultAddress.city',
            'parent.defaultAddress.district',
            'parent.defaultAddress.subdistrict',
            'parent.bankAccounts.bank',
        ]);
        $voucher = $this->availableVoucher($member);
        $warehouse = Warehouse::query()
            ->with(['province', 'city', 'district', 'subdistrict'])
            ->whereKey(1)
            ->where('warehouse_is_active', 1)
            ->first();
        $addresses = $member->addresses()
            ->with(['province', 'city', 'district', 'subdistrict'])
            ->orderByDesc('member_address_is_default')
            ->orderBy('member_address_id')
            ->get();
        $defaultAddress = $addresses->first();
        $seller = $member->level?->member_level_code === 'DST'
            ? $this->warehouseSellerData($warehouse)
            : $this->memberSellerData($member->parent);
        if ($items !== []) {
            $seller['origin'] = $this->shippingService->purchaseOrigin($account, $items);
        }

        return [
            'buyer' => [
                'id' => (int) $member->getKey(),
                'code' => $member->member_code,
                'name' => $member->member_name,
                'level' => $member->level?->member_level_name,
                'minimum_order' => (int) ($member->level?->member_level_min_order ?? 0),
                'destination' => $defaultAddress
                    ? $this->memberAddressDestinationData($defaultAddress)
                    : null,
            ],
            'seller' => $seller,
            'addresses' => $addresses
                ->map(fn (MemberAddress $address): array => [
                    'id' => (int) $address->getKey(),
                    'label' => $address->member_address_label,
                    'recipient' => $address->member_address_recipient,
                    'phone' => $address->member_address_phone,
                    'address' => $address->member_address_full,
                    'is_default' => (bool) $address->member_address_is_default,
                    'destination' => $this->memberAddressDestinationData($address),
                ])->values(),
            'banks' => $seller['type'] === 'warehouse'
                ? $this->companyBankOptions('company')
                : ($member->parent?->bankAccounts
                    ->where('member_bank_account_is_active', 1)
                    ->map(function (MemberBankAccount $bank): array {
                        $reference = RefBank::query()->where('bank_id', $bank->member_bank_account_bank_id)->first();

                        return [
                            'id' => (int) $bank->getKey(),
                            'type' => 'member',
                            'code' => $reference?->bank_code,
                            'name' => $reference?->bank_name,
                            'account_name' => $bank->member_bank_account_name,
                            'account_number' => $bank->member_bank_account_number,
                            'is_default' => (bool) $bank->member_bank_account_is_default,
                        ];
                    })->sortByDesc('is_default')->values() ?? collect()),
            'spread_payment_banks' => $this->spreadPaymentUpline($member)
                ? $this->companyBankOptions('spread_payment')
                : collect(),
            'shipping_methods' => $this->shippingMethodsForSellerType($seller['type']),
            'voucher' => $voucher ? [
                'id' => (int) $voucher->getKey(),
                'amount' => max(
                    0,
                    (int) $voucher->reward_stockist_bonus_value
                        - (int) $voucher->reward_stockist_used_value
                ),
                'expiry_date' => $voucher->reward_stockist_expiry_date?->toDateString(),
                'can_apply' => true,
            ] : null,
        ];
    }

    /** @return Collection<int, array{id: int, type: string, code: ?string, name: ?string, account_name: string, account_number: string}> */
    private function companyBankOptions(string $type): Collection
    {
        $bankTable = (new BankCompany)->getTable();
        $referenceBankTable = (new RefBank)->getTable();

        return DB::table($bankTable)
            ->leftJoin(
                $referenceBankTable,
                "{$referenceBankTable}.bank_id",
                '=',
                "{$bankTable}.bank_company_bank_id"
            )
            ->where("{$bankTable}.bank_company_type", $type)
            ->where("{$bankTable}.bank_company_bank_is_active", 1)
            ->orderBy("{$referenceBankTable}.bank_name")
            ->orderBy("{$bankTable}.bank_company_id")
            ->get([
                "{$bankTable}.bank_company_id as id",
                "{$bankTable}.bank_company_type as type",
                "{$referenceBankTable}.bank_code as code",
                "{$referenceBankTable}.bank_name as name",
                "{$bankTable}.bank_company_bank_acc_name as account_name",
                "{$bankTable}.bank_company_bank_acc_number as account_number",
            ])
            ->map(fn (object $bank): array => [
                'id' => (int) $bank->id,
                'type' => (string) $bank->type,
                'code' => $bank->code,
                'name' => $bank->name,
                'account_name' => (string) $bank->account_name,
                'account_number' => (string) $bank->account_number,
            ]);
    }

    /** @param array<string, mixed> $data */
    public function checkout(MemberAccount $account, array $data): Trx
    {
        $member = $this->member($account)->loadMissing(['level', 'parent.level']);

        return DB::transaction(function () use ($account, $member, $data): Trx {
            $seller = $this->resolveSeller($member, $data);
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

            $prices = ProductPrice::query()
                ->where('product_price_member_level_id', $member->member_member_level_id)
                ->whereIn('product_price_product_id', $productIds)
                ->get()
                ->keyBy('product_price_product_id');
            $stocks = $this->sellerStocks($seller, $productIds);
            $detailAttributes = [];
            $grossTotal = 0;
            $productDiscountTotal = 0;
            $hasPreorder = false;
            $hasStockLine = false;
            $hasPreorderLine = false;

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                /** @var Product $product */
                $product = $products->get($productId);
                $price = (int) ($prices->get($productId)?->product_price_value
                    ?? $product->product_customer_price);
                $discountPercent = 0.0;
                $discountValue = 0;
                $netPrice = max(0, $price - $discountValue);
                $availableStock = max(0, (int) ($stocks->get($productId) ?? 0));
                if ($seller['type'] !== 'warehouse'
                    && $availableStock > 0
                    && $quantity > $availableStock) {
                    throw new ProcessException(
                        "Jumlah {$product->product_name} tidak boleh melebihi stok tersedia. Maksimal {$availableStock} pcs."
                    );
                }
                $preorderQuantity = max(0, $quantity - $availableStock);
                if ($preorderQuantity > 0) {
                    $hasPreorderLine = true;
                } else {
                    $hasStockLine = true;
                }
                $grossTotal += $price * $quantity;
                $productDiscountTotal += $discountValue * $quantity;
                $detailAttributes[] = [
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

            if ($seller['type'] !== 'warehouse' && $hasStockLine && $hasPreorderLine) {
                throw new ProcessException(
                    'Pesanan tidak dapat mencampur produk siap kirim dan produk inden. '
                    .'Pisahkan menjadi pesanan siap kirim atau pesanan inden.'
                );
            }

            $hasPreorder = $hasPreorderLine;
            $terminalSeller = null;
            if ($hasPreorder) {
                $terminalSeller = $this->preorderChainService->terminalSeller($items, $seller);
            }
            $shippingSeller = $terminalSeller ?? $seller;
            $this->ensureShippingMethodAllowed(
                $shippingSeller['type'],
                $data['shipping_method'],
                $hasPreorder,
            );

            $productTotal = max(0, $grossTotal - $productDiscountTotal);
            $minimumOrder = (int) ($member->level?->member_level_min_order ?? 0);
            if ($productTotal < $minimumOrder) {
                throw new ProcessException("Minimum pembelian untuk tingkat kemitraan Anda adalah Rp{$minimumOrder}.");
            }

            $voucher = ($data['use_voucher'] ?? false)
                ? $this->availableVoucher($member, true)
                : null;
            if (($data['use_voucher'] ?? false) && ! $voucher) {
                throw new ProcessException('Voucher bulanan tidak tersedia atau sudah tidak dapat digunakan.');
            }

            $data = $this->quoteStcShipping($account, $member, $shippingSeller, $items, $data);
            $shippingCost = $data['shipping_method'] === 'pickup'
                ? 0
                : (int) $data['courier']['cost'];
            $shippingExtra = match ($data['shipping_method']) {
                'courier_express', 'courier_manual' => ShippingInsurance::amount($data['courier']),
                'courier_instant' => (int) ($data['courier']['admin_fee'] ?? 0),
                default => 0,
            };
            $shippingCharge = $shippingCost + $shippingExtra;
            $transactionTotal = $productTotal + $shippingCharge;
            $voucherValue = $voucher
                ? min(
                    $transactionTotal,
                    max(
                        0,
                        (int) $voucher->reward_stockist_bonus_value
                            - (int) $voucher->reward_stockist_used_value
                    )
                )
                : 0;
            $afterDiscount = max(0, $productTotal - $voucherValue);
            $billAmount = max(0, $transactionTotal - $voucherValue);
            $this->ensureUnsignedIntegerTotals(
                $grossTotal,
                $productDiscountTotal,
                $shippingCharge,
                $voucherValue,
                $billAmount,
            );
            $bank = $this->paymentDestination($seller, $data);
            $now = now();
            $buyerType = $this->memberType($member);
            $initialStatus = $seller['type'] === 'warehouse' && $buyerType === 'distributor'
                ? 'waiting_stock_screening'
                : 'waiting_payment';
            $trx = Trx::query()->create([
                'trx_code' => $this->transactionCodeService->next($seller['type'], $buyerType),
                'trx_parent_trx_id' => 0,
                'trx_is_preorder' => $hasPreorder,
                'trx_seller_type' => $seller['type'],
                'trx_seller_id' => $seller['id'],
                'trx_buyer_type' => $buyerType,
                'trx_buyer_id' => $member->getKey(),
                'trx_type' => 'stock',
                'trx_reference_id' => 0,
                'trx_total_price' => $grossTotal,
                'trx_discount' => $grossTotal === 0
                    ? 0
                    : (int) round($productDiscountTotal * 100 / $grossTotal),
                'trx_discount_value' => $productDiscountTotal,
                'trx_voucher_id' => (int) ($voucher?->getKey() ?? 0),
                'trx_voucher_value' => $voucherValue,
                'trx_grand_total_price' => $afterDiscount,
                'trx_shipping_cost' => $shippingCharge,
                'trx_payment_charge' => 0,
                'trx_grand_total_nett_price' => $billAmount,
                'trx_bill_remaining' => $billAmount,
                'trx_bill_augment' => 0,
                'trx_bill_amount' => $billAmount,
                'trx_payment_method' => 'transfer',
                'trx_shipping_method' => $data['shipping_method'],
                'trx_status' => $initialStatus,
                'trx_status_datetime' => $now,
                'trx_datetime' => $now,
            ]);

            $trx->details()->createMany($detailAttributes);
            $trx->load('details');
            $this->stockAllocationService->reserve($trx);
            if ($hasPreorder && $seller['type'] !== 'warehouse') {
                $this->preorderChainService->reserveTerminalStock($items, $trx, $terminalSeller);
            }
            TrxPaymentTransfer::query()->create([
                'payment_transfer_trx_id' => $trx->getKey(),
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
            $spreadUpline = $this->spreadPaymentUpline($member);
            if ($spreadUpline) {
                $this->createSpreadPayment($trx, $spreadUpline, $billAmount, $now);
            }
            $this->createShipping($trx, $member, $seller, $products, $items, $data, $shippingSeller);
            if ($hasPreorder) {
                $this->preorderChainService->createRemainingOrders($trx);
            }
            $this->notifySellerAboutPreorder($trx, $member, $seller, $detailAttributes);

            if ($voucher) {
                $voucher->update([
                    'reward_stockist_used_value' => $voucherValue,
                    'reward_stockist_used_trx_id' => $trx->getKey(),
                ]);
            }

            return $this->order($account, $trx);
        });
    }

    /** @param array<string, mixed> $data */
    public function submitPayment(MemberAccount $account, Trx $trx, array $data): TrxPaymentTransfer
    {
        $member = $this->member($account);

        $submittedPayment = DB::transaction(function () use ($member, $trx, $data): TrxPaymentTransfer {
            $lockedTrx = $this->orderQuery($member)
                ->whereKey($trx->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTrx->trx_status === 'waiting_stock_screening') {
                throw new ProcessException(
                    'Bukti pembayaran dapat dikirim setelah screening stok perusahaan disetujui.',
                );
            }
            if ($lockedTrx->trx_status !== 'waiting_payment') {
                throw new ProcessException('Bukti pembayaran hanya dapat dikirim untuk pesanan yang menunggu pembayaran.');
            }
            $this->preorderPaymentSequenceGuard->ensurePreviousPaymentsApproved($lockedTrx);
            $payment = TrxPaymentTransfer::query()
                ->where('payment_transfer_trx_id', $lockedTrx->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($payment->payment_transfer_approval_status, ['pending', 'rejected'], true)) {
                throw new ProcessException('Bukti pembayaran pesanan ini sudah pernah dikirim.');
            }

            $spread = TrxSpreadPayment::query()
                ->where('trx_spread_payment_trx_id', $lockedTrx->getKey())
                ->lockForUpdate()
                ->first();
            if ($spread) {
                if (blank($data['spread_receipt_url'] ?? null)) {
                    throw new ProcessException('Bukti pembagian pembayaran wajib diunggah.');
                }
            }

            $payment->update([
                'payment_transfer_amount' => $lockedTrx->trx_bill_amount,
                'payment_transfer_datetime' => now(),
                'payment_transfer_receipt_file' => MediaUrl::canonicalPrivateUrl(
                    $data['receipt_url'],
                    'member',
                ),
                'payment_transfer_approval_status' => 'submitted',
                'payment_transfer_approval_admin_id' => 0,
                'payment_transfer_approval_datetime' => null,
                'payment_transfer_note' => $data['note'] ?? '',
            ]);
            if ($spread) {
                $spread->update([
                    'trx_spread_payment_receipt_file' => MediaUrl::canonicalPrivateUrl(
                        $data['spread_receipt_url'],
                        'member',
                    ),
                    'trx_spread_payment_transfer_datetime' => now(),
                    'trx_spread_payment_status' => 'submitted',
                ]);
            }
            $lockedTrx->update([
                'trx_status' => 'waiting_payment_approval',
                'trx_status_datetime' => now(),
            ]);

            return $payment->refresh()->load(
                'bank',
                'trx.details.product',
                'trx.spreadPayments.bank',
            );
        });
        $this->partnershipEmailService->sendPaymentSubmitted($submittedPayment);

        return $submittedPayment;
    }

    public function cancel(MemberAccount $account, Trx $trx): Trx
    {
        $member = $this->member($account);

        return DB::transaction(function () use ($account, $member, $trx): Trx {
            $lockedTrx = $this->orderQuery($member)
                ->whereKey($trx->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTrx->trx_is_preorder) {
                throw new ProcessException('Pesanan PO hanya dapat dibatalkan oleh penjual PO terakhir.');
            }
            if (! in_array($lockedTrx->trx_status, ['waiting_stock_screening', 'waiting_payment'], true)) {
                throw new ProcessException('Pesanan pada status saat ini tidak dapat dibatalkan.');
            }

            $this->stockAllocationService->release($lockedTrx);
            $lockedTrx->update(['trx_status' => 'cancelled', 'trx_status_datetime' => now()]);
            RewardStockist::query()
                ->where('reward_stockist_used_trx_id', $lockedTrx->getKey())
                ->update([
                    'reward_stockist_used_value' => 0,
                    'reward_stockist_used_trx_id' => 0,
                ]);

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

    /** @param array<string, mixed> $data
     * @return array{type: string, id: int, model: Member|Warehouse}
     */
    private function resolveSeller(Member $member, array $data): array
    {
        if ($member->level?->member_level_code === 'DST') {
            $warehouse = Warehouse::query()
                ->whereKey(1)
                ->where('warehouse_is_active', 1)
                ->firstOrFail();

            return ['type' => 'warehouse', 'id' => (int) $warehouse->getKey(), 'model' => $warehouse];
        }

        $parent = Member::query()
            ->with('level')
            ->whereKey($member->member_parent_member_id)
            ->where('member_status', 1)
            ->first();
        if (! $parent) {
            throw new ProcessException('Mitra penjual di atas jaringan tidak ditemukan.');
        }

        return [
            'type' => $this->memberType($parent),
            'id' => (int) $parent->getKey(),
            'model' => $parent,
        ];
    }

    /** @param array{type: string, id: int, model: Member|Warehouse} $seller
     * @param  list<int>  $productIds
     * @return Collection<int, int>
     */
    private function sellerStocks(array $seller, array $productIds): Collection
    {
        if ($seller['type'] === 'warehouse') {
            return WarehouseStock::query()
                ->where('warehouse_stock_warehouse_id', $seller['id'])
                ->whereIn('warehouse_stock_product_id', $productIds)
                ->get()
                ->mapWithKeys(fn (WarehouseStock $stock): array => [
                    // Checkout reservations already reduce balance. The
                    // transfer_out column only tracks stock awaiting shipment,
                    // so subtracting it again would hide valid stock.
                    (int) $stock->warehouse_stock_product_id => max(0, (int) $stock->warehouse_stock_balance),
                ]);
        }

        return MemberStock::query()
            ->where('member_stock_member_id', $seller['id'])
            ->whereIn('member_stock_product_id', $productIds)
            ->get()
            ->mapWithKeys(fn (MemberStock $stock): array => [
                (int) $stock->member_stock_product_id => max(0, (int) $stock->member_stock_balance),
            ]);
    }

    /** @return array{bank_id: int, account_name: string, account_number: string} */
    private function paymentDestination(array $seller, array $data): array
    {
        if ($seller['type'] !== 'warehouse' && isset($data['bank_account_id'])) {
            $account = MemberBankAccount::query()
                ->whereKey($data['bank_account_id'])
                ->where('member_bank_account_member_id', $seller['id'])
                ->where('member_bank_account_is_active', 1)
                ->lockForUpdate()
                ->firstOrFail();

            return [
                'bank_id' => (int) $account->member_bank_account_bank_id,
                'account_name' => $account->member_bank_account_name,
                'account_number' => $account->member_bank_account_number,
            ];
        }

        if ($seller['type'] !== 'warehouse' && blank($data['bank_account_id'] ?? null)) {
            $account = MemberBankAccount::query()
                ->where('member_bank_account_member_id', $seller['id'])
                ->where('member_bank_account_is_active', 1)
                ->orderByDesc('member_bank_account_is_default')
                ->orderBy('member_bank_account_id')
                ->lockForUpdate()
                ->first();

            if ($account) {
                return [
                    'bank_id' => (int) $account->member_bank_account_bank_id,
                    'account_name' => $account->member_bank_account_name,
                    'account_number' => $account->member_bank_account_number,
                ];
            }

            throw new ProcessException(sprintf(
                'Rekening Penjual (%s) belum ditentukan.',
                Str::headline($seller['type']),
            ));
        }

        $bank = BankCompany::query()
            ->whereKey($data['bank_company_id'] ?? 0)
            ->where('bank_company_type', 'company')
            ->where('bank_company_bank_is_active', 1)
            ->lockForUpdate()
            ->firstOrFail();

        return [
            'bank_id' => (int) $bank->bank_company_bank_id,
            'account_name' => (string) $bank->bank_company_bank_acc_name,
            'account_number' => (string) $bank->bank_company_bank_acc_number,
        ];
    }

    private function createSpreadPayment(
        Trx $trx,
        Member $upline,
        int $billAmount,
        \DateTimeInterface $now,
    ): void {
        $percentage = (float) BusinessConfig::get('partnership.spread_payment_percentage', 1);
        $percentage = $percentage > 0 ? $percentage : 1.0;
        $amount = (int) ceil($billAmount * $percentage / 100);

        if ($amount <= 0) {
            return;
        }

        $spreadBank = BankCompany::query()
            ->where('bank_company_type', 'spread_payment')
            ->where('bank_company_bank_is_active', 1)
            ->orderBy('bank_company_id')
            ->lockForUpdate()
            ->first();
        if (! $spreadBank) {
            throw new ProcessException('Rekening pembagian pembayaran tidak ditemukan.');
        }

        TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => $trx->getKey(),
            'trx_spread_payment_upline_id' => $upline->getKey(),
            'trx_spread_payment_member_id' => $trx->trx_buyer_id,
            'trx_spread_payment_bank_id' => $spreadBank->bank_company_bank_id,
            'trx_spread_payment_account_name' => $spreadBank->bank_company_bank_acc_name,
            'trx_spread_payment_account_number' => $spreadBank->bank_company_bank_acc_number,
            'trx_spread_payment_percentage' => $percentage,
            'trx_spread_payment_amount' => $amount,
            'trx_spread_payment_status' => 'pending',
            'trx_spread_payment_approved_by' => 0,
            'trx_spread_payment_paid_by' => 0,
            'trx_spread_payment_note' => 'Pembayaran spread wajib dilampirkan bersama bukti pembayaran utama.',
            'trx_spread_payment_created_datetime' => $now,
        ]);
    }

    private function spreadPaymentUpline(Member $member): ?Member
    {
        if ($member->level?->member_level_code !== 'DST') {
            return null;
        }

        $parent = $member->parent;

        if (! $parent || (int) $parent->member_status !== 1) {
            return null;
        }

        return $parent->level?->member_level_code === 'DST' ? $parent : null;
    }

    private function availableVoucher(Member $member, bool $lock = false): ?RewardStockist
    {
        $query = RewardStockist::query()
            ->where('reward_stockist_member_id', $member->getKey())
            ->where('reward_stockist_used_trx_id', 0)
            ->whereColumn('reward_stockist_used_value', '<', 'reward_stockist_bonus_value')
            ->where(function ($voucherQuery): void {
                $voucherQuery
                    ->whereNull('reward_stockist_expiry_date')
                    ->orWhereDate('reward_stockist_expiry_date', '>=', today());
            })
            ->latest('reward_stockist_year')
            ->latest('reward_stockist_month')
            ->latest('reward_stockist_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse}  $seller
     * @param  list<array<string, mixed>>  $details
     */
    private function notifySellerAboutPreorder(
        Trx $trx,
        Member $buyer,
        array $seller,
        array $details,
    ): void {
        if (! $trx->trx_is_preorder || $seller['model'] instanceof Warehouse) {
            return;
        }

        $shortages = collect($details)
            ->filter(fn (array $detail): bool => $trx->trx_is_preorder)
            ->map(fn (array $detail): string => sprintf(
                '%s %d pcs',
                $detail['trx_detail_product_name'],
                $detail['trx_detail_qty'],
            ))
            ->implode(', ');

        $this->memberNotificationService->transactionSeller(
            $trx,
            'Pre-Order ke Upline Diperlukan',
            "Pesanan {$trx->trx_code} dari {$buyer->member_name} kekurangan stok: {$shortages}. Buat pesanan ke upline untuk memenuhi kekurangan ini.",
            'stock',
        );
    }

    /** @param array{type: string, id: int, model: Member|Warehouse} $seller
     * @param  EloquentCollection<int, Product>  $products
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @param  null|array{type: string, id: int, model: Member|Warehouse}  $shippingSeller
     */
    private function createShipping(
        Trx $trx,
        Member $buyer,
        array $seller,
        EloquentCollection $products,
        Collection $items,
        array $data,
        ?array $shippingSeller = null,
    ): void {
        $origin = $this->originSnapshot($shippingSeller ?? $seller);

        if ($data['shipping_method'] === 'pickup') {
            $pickup = ShippingPickup::query()->create([
                'shipping_pickup_ref_type' => 'trx',
                'shipping_pickup_ref_id' => $trx->getKey(),
                'shipping_pickup_seller_address' => $origin['address'],
                'shipping_pickup_seller_name' => Str::substr((string) $origin['name'], 0, 50),
                'shipping_pickup_seller_mobilephone' => Str::substr($origin['phone'], 0, 16),
                'shipping_pickup_schedule_datetime' => null,
                'shipping_pickup_pin' => (string) random_int(10000, 99999),
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

        $address = MemberAddress::query()
            ->whereKey($data['address_id'])
            ->where('member_address_member_id', $buyer->getKey())
            ->firstOrFail();
        $destination = $this->destinationSnapshot($address);
        $package = $this->packageSnapshot($products, $items);
        $courier = is_array($data['courier'] ?? null) ? $data['courier'] : [];

        match ($data['shipping_method']) {
            'courier_express' => ShippingCourierExpress::query()->create([
                'shipping_courier_express_ref_type' => 'trx',
                'shipping_courier_express_ref_id' => $trx->getKey(),
                'shipping_courier_express_type' => $courier['service_type'] ?? '',
                'shipping_courier_express_expedition_name' => $courier['courier_code'] ?? '',
                'shipping_courier_express_expedition_service' => $courier['courier_name'] ?? '',
                'shipping_courier_express_etd' => $courier['etd'] ?? '',
                'shipping_courier_express_order_id' => '',
                'shipping_courier_express_pickup_method' => ($courier['drop_off_available'] ?? false)
                    ? 'DROP-OFF'
                    : 'PICKUP',
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
                ...$this->regionalShippingFields('shipping_courier_express', $origin, $destination),
            ]),
            'courier_instant' => ShippingCourierInstant::query()->create([
                'shipping_courier_instant_ref_type' => 'trx',
                'shipping_courier_instant_ref_id' => $trx->getKey(),
                'shipping_courier_instant_type' => $courier['type'] ?? '',
                'shipping_courier_instant_expedition_name' => $courier['name'] ?? '',
                'shipping_courier_instant_expedition_service' => $courier['service'] ?? '',
                'shipping_courier_instant_expedition_vehicle' => $courier['vehicle'] ?? '',
                'shipping_courier_instant_estimation_hours' => $courier['etd'] ?? '',
                'shipping_courier_instant_order_id' => '',
                'shipping_courier_instant_awb' => null,
                'shipping_courier_instant_admin_fee' => $courier['admin_fee'] ?? 0,
                'shipping_courier_instant_cost' => $courier['cost'],
                'shipping_courier_instant_insurance' => 0,
                'shipping_courier_instant_package_weight' => $package['weight'],
                'shipping_courier_instant_origin_name' => Str::substr((string) $origin['name'], 0, 50),
                'shipping_courier_instant_origin_phone' => Str::substr($origin['phone'], 0, 16),
                'shipping_courier_instant_origin_address' => $origin['address'],
                'shipping_courier_instant_origin_address_note' => '',
                'shipping_courier_instant_origin_latitude' => $courier['origin_latitude'],
                'shipping_courier_instant_origin_longitude' => $courier['origin_longitude'],
                'shipping_courier_instant_destination_name' => Str::substr((string) $destination['name'], 0, 50),
                'shipping_courier_instant_destination_phone' => Str::substr($destination['phone'], 0, 16),
                'shipping_courier_instant_destination_address' => $destination['address'],
                'shipping_courier_instant_destination_address_note' => $address->member_address_label,
                'shipping_courier_instant_destination_latitude' => $courier['destination_latitude'],
                'shipping_courier_instant_destination_longitude' => $courier['destination_longitude'],
            ]),
            'courier_manual' => ShippingCourierManual::query()->create([
                'shipping_courier_manual_ref_type' => 'trx',
                'shipping_courier_manual_ref_id' => $trx->getKey(),
                'shipping_courier_manual_name' => $courier['courier_code'] ?? '',
                'shipping_courier_manual_service' => $courier['courier_name'] ?? '',
                'shipping_courier_manual_type' => $courier['service_type'] ?? '',
                'shipping_courier_manual_awb' => null,
                'shipping_courier_manual_price' => (int) $courier['cost'],
                'shipping_courier_manual_insurance' => ShippingInsurance::amount($courier),
                'shipping_courier_manual_package_weight' => $package['weight'],
                'shipping_courier_manual_package_dimension' => "{$package['length']}x{$package['width']}x{$package['height']}",
                ...$this->regionalShippingFields('shipping_courier_manual', $origin, $destination),
            ]),
            default => throw new ProcessException('Metode pengiriman tidak dikenali.'),
        };
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse}  $seller
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function quoteStcShipping(
        MemberAccount $account,
        Member $member,
        array $seller,
        Collection $items,
        array $data,
    ): array {
        if (! in_array($data['shipping_method'], [
            'courier_express',
            'courier_instant',
            'courier_manual',
        ], true)) {
            return $data;
        }

        $address = MemberAddress::query()
            ->whereKey($data['address_id'])
            ->where('member_address_member_id', $member->getKey())
            ->firstOrFail();
        $courier = $data['courier'];
        $origin = $this->originSnapshot($seller);

        if (in_array($data['shipping_method'], ['courier_express', 'courier_manual'], true)) {
            $requestedDropOff = (bool) $courier['drop_off_available'];
            $rate = collect($this->shippingService->expressRates($account, [
                'origin' => [
                    'district_id' => (int) $origin['district_id'],
                    'subdistrict_id' => (int) $origin['subdistrict_id'],
                ],
                'destination' => [
                    'district_id' => (int) $address->member_address_district_id,
                    'subdistrict_id' => (int) $address->member_address_subdistrict_id,
                ],
                'couriers' => [$courier['courier_code']],
                'items' => $items->all(),
            ], 'purchase')['results'])->first(fn (array $candidate): bool => (string) $candidate['courier_code'] === (string) $courier['courier_code']
                && (string) $candidate['courier_name'] === (string) $courier['courier_name']
                && (string) $candidate['service_type'] === (string) $courier['service_type']
            );
            if (! $rate) {
                throw new ProcessException(
                    'Layanan kurir yang dipilih tidak tersedia lagi. Silakan cek ongkir kembali.'
                );
            }
            if ($requestedDropOff && ! $rate['drop_off_available']) {
                throw new ProcessException('Layanan kurir yang dipilih tidak mendukung pengantaran paket ke gerai kurir.');
            }

            $data['courier'] = [
                'courier_code' => $rate['courier_code'],
                'courier_name' => $rate['courier_name'],
                'service_type' => $rate['service_type'],
                'cost' => $rate['cost'],
                'etd' => $rate['etd'],
                'drop_off_available' => $requestedDropOff,
                'force_insurance' => ShippingInsurance::isForced($rate),
                'insurance' => ShippingInsurance::amount($rate),
                'logo_url' => $rate['logo_url'],
            ];

            return $data;
        }

        $destination = $this->destinationSnapshot($address);
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
        ], 'purchase')['results'])->first(fn (array $candidate): bool => (string) $candidate['courier_code'] === (string) $courier['name']
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
            'cost' => $rate['cost'],
            'vehicle' => $rate['vehicle'],
            'etd' => $rate['estimation'],
            'admin_fee' => $rate['admin_fee'],
            'origin_latitude' => $courier['origin_latitude'],
            'origin_longitude' => $courier['origin_longitude'],
            'destination_latitude' => $courier['destination_latitude'],
            'destination_longitude' => $courier['destination_longitude'],
        ];

        return $data;
    }

    /** @param array{type: string, id: int, model: Member|Warehouse} $seller
     * @return array<string, mixed>
     */
    private function originSnapshot(array $seller): array
    {
        $model = $seller['model'];
        if ($model instanceof Warehouse) {
            return $this->locationSnapshot([
                'name' => $model->warehouse_name,
                'phone' => $model->warehouse_phone ?? '',
                'address' => $model->warehouse_address,
                'province_id' => $model->warehouse_province_id,
                'city_id' => $model->warehouse_city_id,
                'district_id' => $model->warehouse_district_id,
                'subdistrict_id' => $model->warehouse_subdistrict_id,
                'latitude' => $model->warehouse_latitude,
                'longitude' => $model->warehouse_longitude,
            ]);
        }

        return $this->locationSnapshot([
            'name' => $model->member_name,
            'phone' => $model->member_mobilephone,
            'address' => $model->member_address ?? '',
            'province_id' => $model->member_province_id,
            'city_id' => $model->member_city_id,
            'district_id' => $model->member_district_id,
            'subdistrict_id' => $model->member_subdistrict_id,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function destinationSnapshot(MemberAddress $address): array
    {
        return $this->locationSnapshot([
            'name' => $address->member_address_recipient,
            'phone' => $address->member_address_phone,
            'address' => $address->member_address_full,
            'province_id' => $address->member_address_province_id,
            'city_id' => $address->member_address_city_id,
            'district_id' => $address->member_address_district_id,
            'subdistrict_id' => $address->member_address_subdistrict_id,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    /** @param array<string, mixed> $location
     * @return array<string, mixed>
     */
    private function locationSnapshot(array $location): array
    {
        $subdistrict = RefSubdistrict::query()
            ->where('subdistrict_id', $location['subdistrict_id'])
            ->first();

        return [
            ...$location,
            'province_name' => RefProvince::query()
                ->where('province_id', $location['province_id'])
                ->value('province_name') ?? '',
            'city_name' => RefCity::query()
                ->where('city_id', $location['city_id'])
                ->value('city_name') ?? '',
            'district_name' => RefDistrict::query()
                ->where('district_id', $location['district_id'])
                ->value('district_name') ?? '',
            'subdistrict_name' => $subdistrict?->subdistrict_name ?? '',
            'zipcode' => $subdistrict?->subdistrict_zip_code,
        ];
    }

    /** @param EloquentCollection<int, Product> $products
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{weight: int, length: int, width: int, height: int}
     */
    private function packageSnapshot(EloquentCollection $products, Collection $items): array
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
    private function regionalShippingFields(string $prefix, array $origin, array $destination): array
    {
        return [
            "{$prefix}_origin_name" => Str::substr((string) $origin['name'], 0, 50),
            "{$prefix}_origin_phone" => Str::substr($origin['phone'], 0, 16),
            "{$prefix}_origin_address" => $origin['address'],
            "{$prefix}_origin_subdistrict_id" => (int) ($origin['subdistrict_id'] ?? 0),
            "{$prefix}_origin_subdistrict_name" => Str::substr((string) $origin['subdistrict_name'], 0, 50),
            "{$prefix}_origin_district_name" => Str::substr((string) $origin['district_name'], 0, 50),
            "{$prefix}_origin_city_name" => Str::substr((string) $origin['city_name'], 0, 50),
            "{$prefix}_origin_province_name" => Str::substr((string) $origin['province_name'], 0, 50),
            "{$prefix}_origin_zipcode" => $origin['zipcode'] === null
                ? null
                : Str::substr((string) $origin['zipcode'], 0, 5),
            "{$prefix}_destination_name" => Str::substr((string) $destination['name'], 0, 50),
            "{$prefix}_destination_phone" => Str::substr($destination['phone'], 0, 16),
            "{$prefix}_destination_address" => $destination['address'],
            "{$prefix}_destination_subdistrict_id" => (int) ($destination['subdistrict_id'] ?? 0),
            "{$prefix}_destination_subdistrict_name" => Str::substr((string) $destination['subdistrict_name'], 0, 50),
            "{$prefix}_destination_district_name" => Str::substr((string) $destination['district_name'], 0, 50),
            "{$prefix}_destination_city_name" => Str::substr((string) $destination['city_name'], 0, 50),
            "{$prefix}_destination_province_name" => Str::substr((string) $destination['province_name'], 0, 50),
            "{$prefix}_destination_zipcode" => $destination['zipcode'] === null
                ? null
                : Str::substr((string) $destination['zipcode'], 0, 5),
        ];
    }

    private function memberSellerData(?Member $seller): ?array
    {
        if (! $seller) {
            return null;
        }

        return [
            'type' => $this->memberType($seller),
            'id' => (int) $seller->getKey(),
            'code' => $seller->member_code,
            'name' => $seller->member_name,
            'address' => $seller->member_address,
            'origin' => [
                'name' => $seller->member_name,
                'phone' => $seller->member_mobilephone,
                'address' => $seller->member_address,
                'province_id' => (int) $seller->member_province_id,
                'province_name' => $seller->province?->province_name,
                'city_id' => (int) $seller->member_city_id,
                'city_name' => $seller->city?->city_name,
                'district_id' => (int) $seller->member_district_id,
                'district_name' => $seller->district?->district_name,
                'subdistrict_id' => (int) $seller->member_subdistrict_id,
                'subdistrict_name' => $seller->subdistrict?->subdistrict_name,
                'zipcode' => $seller->subdistrict?->subdistrict_zip_code,
                'latitude' => null,
                'longitude' => null,
            ],
        ];
    }

    private function warehouseSellerData(?Warehouse $warehouse): ?array
    {
        if (! $warehouse) {
            return null;
        }

        return [
            'type' => 'warehouse',
            'id' => (int) $warehouse->getKey(),
            'code' => null,
            'name' => $warehouse->warehouse_name,
            'address' => $warehouse->warehouse_address,
            'origin' => $this->warehouseOriginData($warehouse),
        ];
    }

    /** @return array<string, mixed> */
    private function warehouseOriginData(Warehouse $warehouse): array
    {
        return [
            'name' => $warehouse->warehouse_name,
            'phone' => $warehouse->warehouse_phone,
            'address' => $warehouse->warehouse_address,
            'province_id' => (int) $warehouse->warehouse_province_id,
            'province_name' => $warehouse->province?->province_name,
            'city_id' => (int) $warehouse->warehouse_city_id,
            'city_name' => $warehouse->city?->city_name,
            'district_id' => (int) $warehouse->warehouse_district_id,
            'district_name' => $warehouse->district?->district_name,
            'subdistrict_id' => (int) $warehouse->warehouse_subdistrict_id,
            'subdistrict_name' => $warehouse->subdistrict?->subdistrict_name,
            'zipcode' => $warehouse->subdistrict?->subdistrict_zip_code,
            'latitude' => $warehouse->warehouse_latitude,
            'longitude' => $warehouse->warehouse_longitude,
        ];
    }

    /** @return array<string, mixed> */
    private function memberAddressDestinationData(MemberAddress $address): array
    {
        return [
            'address_id' => (int) $address->getKey(),
            'name' => $address->member_address_recipient,
            'phone' => $address->member_address_phone,
            'address' => $address->member_address_full,
            'province_id' => (int) $address->member_address_province_id,
            'province_name' => $address->province?->province_name,
            'city_id' => (int) $address->member_address_city_id,
            'city_name' => $address->city?->city_name,
            'district_id' => (int) $address->member_address_district_id,
            'district_name' => $address->district?->district_name,
            'subdistrict_id' => (int) $address->member_address_subdistrict_id,
            'subdistrict_name' => $address->subdistrict?->subdistrict_name,
            'zipcode' => $address->subdistrict?->subdistrict_zip_code,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /** @return list<array{code: string, name: string}> */
    private function shippingMethodsForSellerType(?string $sellerType): array
    {
        if ($sellerType === 'warehouse') {
            return [
                ['code' => 'courier_express', 'name' => 'Kurir Ekspres'],
                ['code' => 'pickup', 'name' => 'Ambil di Tempat'],
            ];
        }

        return [
            ['code' => 'courier_manual', 'name' => 'Kurir'],
            ['code' => 'pickup', 'name' => 'Ambil di Tempat'],
        ];
    }

    private function ensureShippingMethodAllowed(
        string $sellerType,
        string $shippingMethod,
        bool $isPreorder,
    ): void {
        $allowedMethods = $sellerType === 'warehouse'
            ? collect($isPreorder
                ? ['courier_express', 'pickup']
                : ['courier_express', 'courier_instant', 'pickup'])
            : collect(['courier_manual', 'pickup']);

        if (! $allowedMethods->contains($shippingMethod)) {
            throw new ProcessException('Metode pengiriman tidak tersedia untuk penjual transaksi ini.');
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

    private function orderQuery(Member $member): Builder
    {
        return Trx::query()
            ->with([
                'seller.level',
                'seller.defaultAddress.province',
                'seller.defaultAddress.city',
                'seller.defaultAddress.district',
                'seller.defaultAddress.subdistrict',
                'sellerWarehouse.province',
                'sellerWarehouse.city',
                'sellerWarehouse.district',
                'sellerWarehouse.subdistrict',
                'buyer',
                'details.product',
                'paymentTransfer.bank',
                'paymentTransfer.trx.spreadPayments.bank',
                'parent.paymentTransfer',
                'shippingExpress',
                'shippingExpress.latestStatus',
                'shippingInstant',
                'shippingInstant.latestStatus',
                'shippingManual',
                'shippingManual.latestStatus',
                'shippingPickup',
                'shippingPickup.latestStatus',
                'goodsReceives.details.product',
                'goodsReceives.details.activeReturnDetails',
            ])
            ->where('trx_buyer_id', $member->getKey())
            ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
            ->where('trx_type', 'stock');
    }
}
