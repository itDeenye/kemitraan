<?php

namespace App\Http\Controllers\Api\V1\Admin\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Transaction\ListPaymentsRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\PaymentActionRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrderResource;
use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use App\Http\Resources\DataTableResource;
use App\Models\SiteAdministrator;
use App\Models\TrxPaymentTransfer;
use App\Services\Transaction\AdminTransactionService;

class PaymentController extends Controller
{
    public function __construct(private readonly AdminTransactionService $transactionService) {}

    public function index(ListPaymentsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->transactionService->payments($request->validated()),
            AdminPaymentResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar verifikasi pembayaran berhasil dimuat.']);
    }

    public function show(TrxPaymentTransfer $payment): AdminOrderResource
    {
        return $this->detailResource($this->transactionService->payment($payment))
            ->additional(['success' => true, 'message' => 'Detail pembayaran berhasil dimuat.']);
    }

    public function approve(PaymentActionRequest $request, TrxPaymentTransfer $payment): AdminOrderResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return $this->detailResource($this->transactionService->approvePayment(
            $payment,
            $administrator,
            $request->validated('note'),
        ))->additional(['success' => true, 'message' => 'Pembayaran berhasil disetujui.']);
    }

    public function reject(PaymentActionRequest $request, TrxPaymentTransfer $payment): AdminOrderResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        $rejectedPayment = $this->transactionService->rejectPayment(
            $payment,
            $administrator,
            $request->validated('note'),
        );
        $transaction = $rejectedPayment->trx;
        $message = $transaction->trx_status === 'cancelled'
            ? ($transaction->trx_is_preorder
                ? 'Pembayaran ditolak. Seluruh rangkaian PO dibatalkan dan alokasi stok dilepas.'
                : 'Pembayaran ditolak dan pesanan dibatalkan.')
            : 'Pembayaran ditolak. Mitra dapat mengunggah ulang bukti pembayaran.';

        return $this->detailResource($rejectedPayment)
            ->additional(['success' => true, 'message' => $message]);
    }

    private function detailResource(TrxPaymentTransfer $payment): AdminOrderResource
    {
        $transaction = $payment->trx;
        $transaction->setRelation('paymentTransfer', $payment);

        return new AdminOrderResource($transaction);
    }
}
