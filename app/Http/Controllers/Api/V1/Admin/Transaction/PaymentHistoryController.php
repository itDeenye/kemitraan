<?php

namespace App\Http\Controllers\Api\V1\Admin\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Transaction\PaymentHistoryRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrderResource;
use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use App\Http\Resources\DataTableResource;
use App\Models\TrxPaymentTransfer;
use App\Services\Transaction\AdminTransactionService;

class PaymentHistoryController extends Controller
{
    public function __construct(private readonly AdminTransactionService $transactionService) {}

    public function index(PaymentHistoryRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->transactionService->payments($request->validated(), historyOnly: true),
            AdminPaymentResource::class,
        ))->additional(['success' => true, 'message' => 'Riwayat verifikasi pembayaran berhasil dimuat.']);
    }

    public function show(PaymentHistoryRequest $request, TrxPaymentTransfer $payment): AdminOrderResource
    {
        $payment = $this->transactionService->paymentHistoryDetail($payment);
        $payment->trx->setRelation('paymentTransfer', $payment);

        return (new AdminOrderResource($payment->trx))
            ->additional(['success' => true, 'message' => 'Detail riwayat verifikasi pembayaran berhasil dimuat.']);
    }
}
