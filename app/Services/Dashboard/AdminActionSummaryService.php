<?php

namespace App\Services\Dashboard;

use App\Models\MemberRegistration;
use App\Models\MemberUpgradeQualified;
use App\Models\ReturnModel;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use Illuminate\Support\Facades\DB;

class AdminActionSummaryService
{
    /** @return array{total: int, menus: list<array{route: string, count: int}>} */
    public function summary(): array
    {
        $counts = [
            '/admin/partnership/registration-approvals' => $this->registrationApprovalCount(),
            '/admin/partnership/member-upgrades' => $this->upgradeApprovalCount(),
            '/admin/transactions/stock-screening' => $this->stockScreeningCount(),
            '/admin/transactions/payment-verification' => $this->paymentVerificationCount(),
            '/admin/inventory/shipping' => $this->shippingCount(),
            '/admin/transactions/sales-returns' => $this->returnActionCount(),
            '/admin/rewards/profit-sharing' => $this->sharingProfitCount(),
        ];

        return [
            'total' => array_sum($counts),
            'menus' => collect($counts)
                ->map(fn (int $count, string $route): array => [
                    'route' => $route,
                    'count' => $count,
                ])
                ->values()
                ->all(),
        ];
    }

    private function registrationApprovalCount(): int
    {
        return MemberRegistration::query()
            ->where('member_registration_status', 'requested')
            ->count();
    }

    private function upgradeApprovalCount(): int
    {
        return MemberUpgradeQualified::query()
            ->where('member_upgrade_qualified_status', 'requested')
            ->count();
    }

    private function stockScreeningCount(): int
    {
        $trxTable = (new Trx)->getTable();
        $paymentTable = (new TrxPaymentTransfer)->getTable();

        return DB::table("{$trxTable} as screening_trx")
            ->leftJoin("{$trxTable} as parent_trx", 'parent_trx.trx_id', '=', 'screening_trx.trx_parent_trx_id')
            ->leftJoin("{$trxTable} as root_trx", 'root_trx.trx_id', '=', 'parent_trx.trx_parent_trx_id')
            ->leftJoin("{$paymentTable} as parent_payment", 'parent_payment.payment_transfer_trx_id', '=', 'parent_trx.trx_id')
            ->leftJoin("{$paymentTable} as root_payment", 'root_payment.payment_transfer_trx_id', '=', 'root_trx.trx_id')
            ->where('screening_trx.trx_status', 'waiting_stock_screening')
            ->whereRaw('(
                screening_trx.trx_is_preorder = 0
                OR screening_trx.trx_parent_trx_id = 0
                OR (
                    parent_payment.payment_transfer_approval_status = ?
                    AND (
                        parent_trx.trx_parent_trx_id = 0
                        OR root_payment.payment_transfer_approval_status = ?
                    )
                )
            )', ['approved', 'approved'])
            ->distinct()
            ->count('screening_trx.trx_id');
    }

    private function paymentVerificationCount(): int
    {
        $paymentTable = (new TrxPaymentTransfer)->getTable();
        $trxTable = (new Trx)->getTable();

        return DB::table("{$paymentTable} as payment")
            ->join("{$trxTable} as payment_trx", 'payment_trx.trx_id', '=', 'payment.payment_transfer_trx_id')
            ->where('payment.payment_transfer_approval_status', 'submitted')
            ->where('payment_trx.trx_status', 'waiting_payment_approval')
            ->where('payment_trx.trx_seller_type', 'warehouse')
            ->distinct()
            ->count('payment.payment_transfer_id');
    }

    private function shippingCount(): int
    {
        return Trx::query()
            ->where('trx_seller_type', 'warehouse')
            ->where('trx_buyer_type', 'distributor')
            ->whereIn('trx_status', ['processing', 'reship_required'])
            ->count();
    }

    private function returnActionCount(): int
    {
        return ReturnModel::query()
            ->whereIn('return_status', [
                'submitted',
                'return_shipping_failed',
                'waiting_member_shipment',
                'return_in_transit',
                'received_by_company',
                'replacement_shipping_failed',
            ])
            ->count();
    }

    private function sharingProfitCount(): int
    {
        $spreadTable = (new TrxSpreadPayment)->getTable();
        $trxTable = (new Trx)->getTable();

        return DB::table("{$spreadTable} as spread")
            ->join("{$trxTable} as spread_trx", 'spread_trx.trx_id', '=', 'spread.trx_spread_payment_trx_id')
            ->whereIn('spread.trx_spread_payment_status', ['pending', 'submitted'])
            ->whereNotIn('spread_trx.trx_status', [
                'waiting_payment',
                'waiting_payment_approval',
                'rejected',
                'cancelled',
            ])
            ->distinct()
            ->count('spread.trx_spread_payment_upline_id');
    }
}
