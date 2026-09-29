<?php

namespace App\Http\Controllers\Api\V1\Member\SalesOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Sales\ApproveSalePaymentRequest;
use App\Http\Requests\Api\V1\Member\Sales\RejectSalePaymentRequest;
use App\Http\Resources\Api\V1\Member\MemberSaleOrderResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Sales\MemberSalesService;
use Illuminate\Http\Request;

/**
 * PaymentController
 * Module: Member
 * Menu: SalesOrder
 */
class PaymentController extends Controller
{
    public function __construct(private readonly MemberSalesService $salesService) {}

    public function approve(ApproveSalePaymentRequest $request, Trx $trx): MemberSaleOrderResource
    {
        return (new MemberSaleOrderResource(
            $this->salesService->approvePayment(
                $this->memberAccount($request),
                $trx,
                $request->validated()
            )
        ))->additional([
            'success' => true,
            'message' => 'Pembayaran berhasil disetujui.',
        ]);
    }

    public function reject(RejectSalePaymentRequest $request, Trx $trx): MemberSaleOrderResource
    {
        return (new MemberSaleOrderResource(
            $this->salesService->rejectPayment(
                $this->memberAccount($request),
                $trx,
                $request->validated('note')
            )
        ))->additional([
            'success' => true,
            'message' => 'Pembayaran berhasil ditolak.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();
        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
