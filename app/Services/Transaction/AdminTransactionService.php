<?php

namespace App\Services\Transaction;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Customer;
use App\Models\GoodsReceive;
use App\Models\Member;
use App\Models\RefBank;
use App\Models\ReturnModel;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\SiteAdministrator;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use App\Models\Warehouse;
use App\Services\Inventory\StockAllocationService;
use App\Services\Notification\PartnershipEmailService;
use App\Services\Purchase\PreorderPaymentSequenceGuard;
use App\Services\Reward\MemberPointService;
use Illuminate\Support\Facades\DB;

class AdminTransactionService
{
    public function __construct(
        private readonly StockAllocationService $stockAllocationService,
        private readonly MemberPointService $memberPointService,
        private readonly AdminPreorderChainService $preorderChainService,
        private readonly PreorderPaymentSequenceGuard $preorderPaymentSequenceGuard,
        private readonly PartnershipEmailService $partnershipEmailService,
    ) {}

    /** @param array<string, mixed> $params */
    public function orders(array $params): array
    {
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $customerTable = (new Customer)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $pickupTable = (new ShippingPickup)->getTable();
        $pickupStatusTable = (new ShippingPickupStatus)->getTable();
        $paymentTable = (new TrxPaymentTransfer)->getTable();
        $bankTable = (new RefBank)->getTable();
        $canProcessStockScreening = "(
            {$trxTable}.trx_status = 'waiting_stock_screening'
            AND (
                {$trxTable}.trx_is_preorder = 0
                OR {$trxTable}.trx_parent_trx_id = 0
                OR (
                    preorder_parent_payment.payment_transfer_approval_status = 'approved'
                    AND (
                        preorder_parent.trx_parent_trx_id = 0
                        OR preorder_root_payment.payment_transfer_approval_status = 'approved'
                    )
                )
            )
        )";
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

        $query = DataTable::select([
            "{$trxTable}.trx_id as id",
            "{$trxTable}.trx_code as code",
            "{$trxTable}.trx_type as type",
            "{$trxTable}.trx_parent_trx_id as parent_transaction_id",
            DB::raw("EXISTS (SELECT 1 FROM {$trxTable} AS child_trx WHERE child_trx.trx_parent_trx_id = {$trxTable}.trx_id) AS has_child_transaction"),
            "{$trxTable}.trx_is_preorder as is_preorder",
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            'seller_member.member_code as seller_member_code',
            'seller_member.member_name as seller_member_name',
            "{$warehouseTable}.warehouse_name as seller_warehouse_name",
            "{$trxTable}.trx_buyer_type as buyer_type",
            "{$trxTable}.trx_buyer_id as buyer_id",
            'buyer_member.member_code as buyer_member_code',
            'buyer_member.member_name as buyer_member_name',
            "{$customerTable}.customer_name as buyer_customer_name",
            "{$trxTable}.trx_total_price as product_total",
            "{$trxTable}.trx_discount as discount_percent",
            "{$trxTable}.trx_discount_value as discount_value",
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
            DB::raw("COALESCE(
                NULLIF(list_express.shipping_courier_express_order_id, ''),
                NULLIF(preorder_parent_express.shipping_courier_express_order_id, ''),
                NULLIF(preorder_root_express.shipping_courier_express_order_id, ''),
                NULLIF(list_instant.shipping_courier_instant_order_id, '')
            ) as shipping_order_id"),
            DB::raw("COALESCE(
                NULLIF(list_express.shipping_courier_express_awb, ''),
                NULLIF(preorder_parent_express.shipping_courier_express_awb, ''),
                NULLIF(preorder_root_express.shipping_courier_express_awb, ''),
                NULLIF(list_instant.shipping_courier_instant_awb, '')
            ) as shipping_tracking_number"),
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
            'pickup_summary.pickup_status as pickup_status',
            "{$paymentTable}.payment_transfer_id as payment_id",
            "{$paymentTable}.payment_transfer_bank_id as payment_bank_id",
            "{$bankTable}.bank_code as payment_bank_code",
            "{$bankTable}.bank_name as payment_bank_name",
            "{$paymentTable}.payment_transfer_account_name as payment_account_name",
            "{$paymentTable}.payment_transfer_account_number as payment_account_number",
            "{$paymentTable}.payment_transfer_bill_amount as payment_bill_amount",
            "{$paymentTable}.payment_transfer_amount as payment_amount",
            "{$paymentTable}.payment_transfer_receipt_file as payment_receipt_url",
            "{$paymentTable}.payment_transfer_approval_status as payment_status",
            "{$paymentTable}.payment_transfer_note as payment_note",
            "{$paymentTable}.payment_transfer_datetime as payment_transferred_at",
            "{$paymentTable}.payment_transfer_approval_datetime as payment_verified_at",
        ])
            ->selectRaw("CASE WHEN {$canProcessStockScreening} THEN 1 ELSE 0 END as can_approve_stock_screening")
            ->from($trxTable)
            ->leftJoin("{$trxTable} as preorder_parent", "preorder_parent.trx_id = {$trxTable}.trx_parent_trx_id")
            ->leftJoin("{$trxTable} as preorder_root", 'preorder_root.trx_id = preorder_parent.trx_parent_trx_id')
            ->leftJoin("{$memberTable} as seller_member", "seller_member.member_id = {$trxTable}.trx_seller_id")
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$trxTable}.trx_seller_id")
            ->leftJoin("{$memberTable} as buyer_member", "buyer_member.member_id = {$trxTable}.trx_buyer_id")
            ->leftJoin($customerTable, "{$customerTable}.customer_id = {$trxTable}.trx_buyer_id")
            ->leftJoin('shipping_courier_express as list_express', 'list_express.shipping_courier_express_ref_id = '.$trxTable.'.trx_id')
            ->leftJoin('shipping_courier_express as preorder_parent_express', 'preorder_parent_express.shipping_courier_express_ref_id = preorder_parent.trx_id')
            ->leftJoin('shipping_courier_express as preorder_root_express', 'preorder_root_express.shipping_courier_express_ref_id = preorder_root.trx_id')
            ->leftJoin('shipping_courier_instant as list_instant', 'list_instant.shipping_courier_instant_ref_id = '.$trxTable.'.trx_id')
            ->leftJoin('shipping_courier_manual as list_manual', 'list_manual.shipping_courier_manual_ref_id = '.$trxTable.'.trx_id')
            ->leftJoinSub($pickupSummary, 'pickup_summary', "pickup_summary.transaction_id = {$trxTable}.trx_id")
            ->leftJoin($pickupTable.' as list_pickup', 'list_pickup.shipping_pickup_ref_id = '.$trxTable.'.trx_id')
            ->leftJoin($paymentTable, "{$paymentTable}.payment_transfer_trx_id = {$trxTable}.trx_id")
            ->leftJoin("{$paymentTable} as preorder_parent_payment", 'preorder_parent_payment.payment_transfer_trx_id = preorder_parent.trx_id')
            ->leftJoin("{$paymentTable} as preorder_root_payment", 'preorder_root_payment.payment_transfer_trx_id = preorder_root.trx_id')
            ->leftJoin($bankTable, "{$bankTable}.bank_id = {$paymentTable}.payment_transfer_bank_id")
            ->search([
                'code', 'seller_member_code', 'seller_member_name', 'seller_warehouse_name',
                'buyer_member_code', 'buyer_member_name', 'buyer_customer_name',
            ])
            ->defaultSort('-id')
            ->allowUnpaginated();

        if (isset($params['_statuses']) && is_array($params['_statuses'])) {
            $query->whereIn("{$trxTable}.trx_status", $params['_statuses']);
        }

        $canApproveStockScreening = data_get($params, 'filter.can_approve_stock_screening');
        if ($canApproveStockScreening !== null) {
            $query->whereRaw(
                "{$canProcessStockScreening} = ?",
                [filter_var($canApproveStockScreening, FILTER_VALIDATE_BOOLEAN) ? 1 : 0],
            );
        }

        $this->applyDateRange($query, "{$trxTable}.trx_datetime", $params);

        return $query->get($params);
    }

    /** @param array<string, mixed> $params */
    public function orderSummary(array $params): array
    {
        $query = Trx::query();

        if (isset($params['date_from']) || isset($params['date_to'])) {
            $query->whereBetween('trx_datetime', [
                ($params['date_from'] ?? '1970-01-01').' 00:00:00',
                ($params['date_to'] ?? '2999-12-31').' 23:59:59',
            ]);
        }

        $summary = $query->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_stock_screening' THEN 1 END) as waiting_stock_screening")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment' THEN 1 END) as waiting_payment")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'waiting_payment_approval' THEN 1 END) as waiting_payment_approval")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'processing' THEN 1 END) as processing")
            ->selectRaw("COUNT(CASE WHEN trx_status IN ('shipped', 'reship_required', 'ready_to_pickup', 'received') THEN 1 END) as delivery")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'reship_required' THEN 1 END) as reship_required")
            ->selectRaw("COUNT(CASE WHEN trx_status = 'completed' THEN 1 END) as completed")
            ->selectRaw("COUNT(CASE WHEN trx_status IN ('cancelled', 'rejected') THEN 1 END) as cancelled")
            ->first();

        return collect((array) $summary)->map(fn (mixed $value): int => (int) $value)->all();
    }

    public function order(Trx $trx): Trx
    {
        $trx->load([
            'buyer.level',
            'buyer.stocks',
            'buyer.defaultAddress.province',
            'buyer.defaultAddress.city',
            'buyer.defaultAddress.district',
            'buyer.defaultAddress.subdistrict',
            'buyerCustomer',
            'seller.level',
            'seller.defaultAddress.province',
            'seller.defaultAddress.city',
            'seller.defaultAddress.district',
            'seller.defaultAddress.subdistrict',
            'sellerWarehouse.province',
            'sellerWarehouse.city',
            'sellerWarehouse.district',
            'sellerWarehouse.subdistrict',
            'details.product.category',
            'paymentTransfer.bank',
            'spreadPayments.bank',
            'returns',
            'shippingExpress',
            'shippingExpress.details',
            'shippingExpress.latestStatus',
            'shippingInstant',
            'shippingInstant.details',
            'shippingInstant.latestStatus',
            'shippingManual',
            'shippingManual.details',
            'shippingManual.latestStatus',
            'shippingPickup',
            'shippingPickup.details',
            'shippingPickup.latestStatus',
        ]);
        $trx->loadExists('children');

        $trx = $this->preorderChainService->load($trx);
        $canProcessStockScreening = $trx->trx_status === 'waiting_stock_screening'
            && $this->preorderPaymentSequenceGuard->previousPaymentsApproved($trx);
        $trx->setAttribute('can_approve_stock_screening', $canProcessStockScreening);

        return $trx;
    }

    /** @param array<string, mixed> $params */
    public function payments(array $params): array
    {
        $paymentTable = (new TrxPaymentTransfer)->getTable();
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $customerTable = (new Customer)->getTable();
        $bankTable = (new RefBank)->getTable();
        $spreadPaymentTable = (new TrxSpreadPayment)->getTable();

        $query = DataTable::select([
            "{$paymentTable}.payment_transfer_id as id",
            "{$paymentTable}.payment_transfer_trx_id as trx_id",
            "{$trxTable}.trx_code as trx_code",
            "{$trxTable}.trx_code as transaction_code",
            "{$trxTable}.trx_type as transaction_type",
            "{$trxTable}.trx_parent_trx_id as parent_transaction_id",
            "{$trxTable}.trx_is_preorder as is_preorder",
            "{$trxTable}.trx_buyer_type as buyer_type",
            "{$trxTable}.trx_buyer_id as buyer_id",
            "{$memberTable}.member_code as buyer_member_code",
            "{$memberTable}.member_name as buyer_member_name",
            "{$customerTable}.customer_name as buyer_customer_name",
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            'seller_member.member_code as seller_member_code',
            'seller_member.member_name as seller_member_name',
            'warehouse.warehouse_name as seller_warehouse_name',
            "{$trxTable}.trx_status as transaction_status",
            "{$trxTable}.trx_payment_method as transaction_payment_method",
            "{$trxTable}.trx_shipping_method as transaction_shipping_method",
            "{$trxTable}.trx_datetime as transaction_ordered_at",
            "{$trxTable}.trx_total_price as product_total",
            "{$trxTable}.trx_discount_value as discount_value",
            "{$trxTable}.trx_grand_total_price as after_discount",
            "{$trxTable}.trx_shipping_cost as shipping_cost_total",
            DB::raw("COALESCE(list_express.shipping_courier_express_cost, list_instant.shipping_courier_instant_cost, list_manual.shipping_courier_manual_price, {$trxTable}.trx_shipping_cost, 0) as shipping_cost"),
            DB::raw('COALESCE(list_express.shipping_courier_express_insurance, list_instant.shipping_courier_instant_insurance, list_manual.shipping_courier_manual_insurance, 0) as shipping_cost_insurance'),
            "{$trxTable}.trx_payment_charge as payment_charge",
            "{$trxTable}.trx_grand_total_nett_price as grand_total",
            "{$paymentTable}.payment_transfer_bank_id as bank_id",
            "{$bankTable}.bank_code as bank_code",
            "{$bankTable}.bank_name as bank_name",
            "{$paymentTable}.payment_transfer_account_name as account_name",
            "{$paymentTable}.payment_transfer_account_number as account_number",
            "{$paymentTable}.payment_transfer_bill_amount as bill_amount",
            "{$paymentTable}.payment_transfer_amount as amount",
            "{$paymentTable}.payment_transfer_receipt_file as receipt_url",
            "{$paymentTable}.payment_transfer_approval_status as status",
            "{$paymentTable}.payment_transfer_approval_admin_id as administrator_id",
            "{$paymentTable}.payment_transfer_approval_datetime as approved_at",
            "{$paymentTable}.payment_transfer_note as note",
            "{$paymentTable}.payment_transfer_datetime as transferred_at",
            "{$spreadPaymentTable}.trx_spread_payment_id as spread_payment_id",
            "{$spreadPaymentTable}.trx_spread_payment_amount as spread_payment_amount",
            "{$spreadPaymentTable}.trx_spread_payment_percentage as spread_payment_percentage",
            "{$spreadPaymentTable}.trx_spread_payment_bank_id as spread_payment_bank_id",
            'spread_bank.bank_code as spread_payment_bank_code',
            'spread_bank.bank_name as spread_payment_bank_name',
            "{$spreadPaymentTable}.trx_spread_payment_account_name as spread_payment_account_name",
            "{$spreadPaymentTable}.trx_spread_payment_account_number as spread_payment_account_number",
            "{$spreadPaymentTable}.trx_spread_payment_receipt_file as spread_payment_receipt_url",
            "{$spreadPaymentTable}.trx_spread_payment_transfer_datetime as spread_payment_transferred_at",
            "{$spreadPaymentTable}.trx_spread_payment_status as spread_payment_status",
        ])
            ->from($paymentTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$paymentTable}.payment_transfer_trx_id")
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$trxTable}.trx_buyer_id")
            ->leftJoin($customerTable, "{$customerTable}.customer_id = {$trxTable}.trx_buyer_id")
            ->leftJoin("{$memberTable} as seller_member", "seller_member.member_id = {$trxTable}.trx_seller_id")
            ->leftJoin((new Warehouse)->getTable().' as warehouse', "warehouse.warehouse_id = {$trxTable}.trx_seller_id")
            ->leftJoin('shipping_courier_express as list_express', "list_express.shipping_courier_express_ref_id = {$trxTable}.trx_id")
            ->leftJoin('shipping_courier_instant as list_instant', "list_instant.shipping_courier_instant_ref_id = {$trxTable}.trx_id")
            ->leftJoin('shipping_courier_manual as list_manual', "list_manual.shipping_courier_manual_ref_id = {$trxTable}.trx_id")
            ->leftJoin($bankTable, "{$bankTable}.bank_id = {$paymentTable}.payment_transfer_bank_id")
            ->leftJoin($spreadPaymentTable, "{$spreadPaymentTable}.trx_spread_payment_trx_id = {$trxTable}.trx_id")
            ->leftJoin("{$bankTable} as spread_bank", "spread_bank.bank_id = {$spreadPaymentTable}.trx_spread_payment_bank_id")
            ->search(['transaction_code', 'trx_code', 'buyer_member_code', 'buyer_member_name', 'buyer_customer_name', 'account_name', 'account_number'])
            ->defaultSort('-id')
            ->allowUnpaginated();

        $this->applyDateRange($query, "{$paymentTable}.payment_transfer_datetime", $params);

        return $query->get($params);
    }

    public function payment(TrxPaymentTransfer $payment): TrxPaymentTransfer
    {
        $payment->load([
            'bank',
            'trx.buyer.level',
            'trx.buyer.defaultAddress.province',
            'trx.buyer.defaultAddress.city',
            'trx.buyer.defaultAddress.district',
            'trx.buyer.defaultAddress.subdistrict',
            'trx.buyerCustomer',
            'trx.seller.level',
            'trx.seller.defaultAddress.province',
            'trx.seller.defaultAddress.city',
            'trx.seller.defaultAddress.district',
            'trx.seller.defaultAddress.subdistrict',
            'trx.sellerWarehouse.province',
            'trx.sellerWarehouse.city',
            'trx.sellerWarehouse.district',
            'trx.sellerWarehouse.subdistrict',
            'trx.details.product',
            'trx.spreadPayments.bank',
            'trx.shippingExpress',
            'trx.shippingExpress.details',
            'trx.shippingExpress.latestStatus',
            'trx.shippingInstant',
            'trx.shippingInstant.details',
            'trx.shippingInstant.latestStatus',
            'trx.shippingManual',
            'trx.shippingManual.details',
            'trx.shippingManual.latestStatus',
            'trx.shippingPickup',
            'trx.shippingPickup.details',
            'trx.shippingPickup.latestStatus',
        ]);

        if ($payment->trx) {
            $this->preorderChainService->load($payment->trx);
        }

        return $payment;
    }

    public function approvePayment(
        TrxPaymentTransfer $payment,
        SiteAdministrator $administrator,
        ?string $note,
    ): TrxPaymentTransfer {
        return $this->updatePayment($payment, $administrator, 'approved', 'processing', $note);
    }

    public function rejectPayment(
        TrxPaymentTransfer $payment,
        SiteAdministrator $administrator,
        ?string $note,
    ): TrxPaymentTransfer {
        return $this->updatePayment($payment, $administrator, 'rejected', 'waiting_payment', $note);
    }

    /** @param array<string, mixed> $params */
    public function returns(array $params): array
    {
        $returnTable = (new ReturnModel)->getTable();
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $receiveTable = (new GoodsReceive)->getTable();

        $query = DataTable::select([
            "{$returnTable}.return_id as id",
            "{$returnTable}.return_code as code",
            "{$receiveTable}.goods_receive_trx_id as trx_id",
            "{$trxTable}.trx_code as trx_code",
            "{$returnTable}.return_goods_receive_id as goods_receive_id",
            "{$receiveTable}.goods_receive_number as goods_receive_number",
            "{$returnTable}.return_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
            "{$returnTable}.return_description as description",
            "{$returnTable}.return_status as status",
            "{$returnTable}.return_shipping_cost_bearer as shipping_cost_bearer",
            "{$returnTable}.return_shipping_method as shipping_method",
            "{$returnTable}.return_shipping_cost as shipping_cost",
            "{$returnTable}.return_replacement_shipping_method as replacement_shipping_method",
            "{$returnTable}.return_replacement_shipping_cost as replacement_shipping_cost",
            "{$returnTable}.return_created_datetime as created_at",
        ])
            ->from($returnTable)
            ->leftJoin(
                $receiveTable,
                "{$receiveTable}.goods_receive_id = {$returnTable}.return_goods_receive_id"
            )
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$receiveTable}.goods_receive_trx_id")
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$returnTable}.return_member_id")
            ->search([
                'code', 'trx_code', 'goods_receive_number', 'member_code', 'member_name', 'description',
            ])
            ->defaultSort('-id')
            ->allowUnpaginated();

        $this->applyDateRange($query, "{$returnTable}.return_created_datetime", $params);

        return $query->get($params);
    }

    public function returnDetail(ReturnModel $return): ReturnModel
    {
        return $return->load([
            'trx.details.product',
            'goodsReceive',
            'replacementShippingExpress',
            'replacementShippingExpress.latestStatus',
            'shippingExpress',
            'shippingExpress.latestStatus',
            'shippingInstant',
            'shippingInstant.latestStatus',
            'shippingManual',
            'shippingManual.latestStatus',
            'shippingPickup',
            'shippingPickup.latestStatus',
            'replacementShippingInstant',
            'replacementShippingInstant.latestStatus',
            'replacementShippingManual',
            'replacementShippingManual.latestStatus',
            'replacementShippingPickup',
            'replacementShippingPickup.latestStatus',
            'member.level',
            'details.product',
            'details.goodsReceiveDetail',
            'statusLogs',
        ]);
    }

    private function updatePayment(
        TrxPaymentTransfer $payment,
        SiteAdministrator $administrator,
        string $paymentStatus,
        string $trxStatus,
        ?string $note,
    ): TrxPaymentTransfer {
        $reviewedPayment = DB::transaction(function () use ($payment, $administrator, $paymentStatus, $trxStatus, $note): TrxPaymentTransfer {
            $lockedPayment = TrxPaymentTransfer::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedPayment->payment_transfer_approval_status !== 'submitted') {
                throw new ProcessException('Pembayaran ini sudah pernah diverifikasi.');
            }

            $trx = Trx::query()->whereKey($lockedPayment->payment_transfer_trx_id)->lockForUpdate()->firstOrFail();
            if (! in_array($trx->trx_status, ['waiting_payment', 'waiting_payment_approval'], true)) {
                throw new ProcessException('Status transaksi tidak sesuai untuk verifikasi pembayaran.');
            }
            if ($paymentStatus === 'approved') {
                $this->preorderPaymentSequenceGuard->ensurePreviousPaymentsApproved($trx);
            }

            $lockedPayment->update([
                'payment_transfer_approval_status' => $paymentStatus,
                'payment_transfer_approval_admin_id' => $administrator->getKey(),
                'payment_transfer_approval_datetime' => now(),
                'payment_transfer_note' => $this->appendNote($lockedPayment->payment_transfer_note, $note),
            ]);
            $spread = TrxSpreadPayment::query()
                ->where('trx_spread_payment_trx_id', $trx->getKey())
                ->lockForUpdate()
                ->first();
            if ($spread && $paymentStatus === 'rejected') {
                $spread->update([
                    'trx_spread_payment_status' => 'rejected',
                    'trx_spread_payment_approved_by' => $administrator->getKey(),
                    'trx_spread_payment_approved_datetime' => now(),
                ]);
            }
            $chainCancelled = false;
            if ($paymentStatus === 'rejected') {
                $chainCancelled = $this->preorderChainService->cancelAfterTerminalPaymentRejection($trx);
                if (! $chainCancelled && ! $trx->trx_is_preorder) {
                    $this->stockAllocationService->release($trx);
                }
            } else {
                $this->memberPointService->recordForApprovedPayment($trx);
            }
            if (! $chainCancelled) {
                $trx->update([
                    'trx_status' => $paymentStatus === 'rejected' && ! $trx->trx_is_preorder
                        ? 'cancelled'
                        : $trxStatus,
                    'trx_status_datetime' => now(),
                ]);
            }

            return $this->payment($lockedPayment->refresh());
        });
        $this->partnershipEmailService->sendPaymentReviewed($reviewedPayment);

        return $reviewedPayment;
    }

    /** @param array<string, mixed> $params */
    private function applyDateRange(DataTable $query, string $column, array $params): void
    {
        if (! isset($params['date_from']) && ! isset($params['date_to'])) {
            return;
        }

        $query->whereBetween($column, [
            ($params['date_from'] ?? '1970-01-01').' 00:00:00',
            ($params['date_to'] ?? '2999-12-31').' 23:59:59',
        ]);
    }

    private function appendNote(?string $currentNote, ?string $newNote): string
    {
        return collect([$currentNote, $newNote])
            ->filter(fn (?string $note): bool => $note !== null && $note !== '')
            ->implode(PHP_EOL);
    }
}
