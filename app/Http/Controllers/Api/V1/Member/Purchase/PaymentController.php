<?php

namespace App\Http\Controllers\Api\V1\Member\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Purchase\SubmitPurchasePaymentRequest;
use App\Http\Resources\Api\V1\Member\MemberPurchasePaymentResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Purchase\MemberPurchaseService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly MemberPurchaseService $purchaseService) {}

    public function store(
        SubmitPurchasePaymentRequest $request,
        Trx $trx,
    ): MemberPurchasePaymentResource {
        return (new MemberPurchasePaymentResource(
            $this->purchaseService->submitPayment(
                $this->memberAccount($request),
                $trx,
                $request->validated()
            )
        ))->additional([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();

        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
