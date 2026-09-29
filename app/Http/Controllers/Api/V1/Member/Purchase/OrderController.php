<?php

namespace App\Http\Controllers\Api\V1\Member\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Purchase\CheckoutOptionsRequest;
use App\Http\Requests\Api\V1\Member\Purchase\CheckoutPurchaseRequest;
use App\Http\Requests\Api\V1\Member\Purchase\ListPurchaseOrdersRequest;
use App\Http\Resources\Api\V1\Member\MemberPurchaseOrderResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Purchase\MemberPurchaseService;
use App\Services\Sales\MemberSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly MemberPurchaseService $purchaseService,
        private readonly MemberSalesService $salesService,
    ) {}

    public function options(CheckoutOptionsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan checkout pembelian berhasil dimuat.',
            'data' => $this->purchaseService->checkoutOptions(
                $this->memberAccount($request),
                $request->validated('items', []),
            ),
        ]);
    }

    public function index(ListPurchaseOrdersRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->purchaseService->orders(
                $this->memberAccount($request),
                $request->validated()
            ),
            MemberPurchaseOrderResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar pesanan pembelian berhasil dimuat.',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $account = $this->memberAccount($request);
        $purchaseSummary = $this->purchaseService->orderSummary($account);
        $salesSummary = $this->salesService->orderSummary($account);
        $readyToReceive = $purchaseSummary['goods_receipt'];
        unset($purchaseSummary['goods_receipt']);

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan transaksi Member berhasil dimuat.',
            'data' => [
                'purchases' => $purchaseSummary,
                'sales' => $salesSummary,
                'goods_receipts' => [
                    'ready_to_receive' => $readyToReceive,
                ],
            ],
        ]);
    }

    public function show(Request $request, Trx $trx): MemberPurchaseOrderResource
    {
        return (new MemberPurchaseOrderResource(
            $this->purchaseService->order($this->memberAccount($request), $trx)
        ))->additional([
            'success' => true,
            'message' => 'Detail pesanan pembelian berhasil dimuat.',
        ]);
    }

    public function store(CheckoutPurchaseRequest $request): MemberPurchaseOrderResource
    {
        $trx = $this->purchaseService->checkout(
            $this->memberAccount($request),
            $request->validated(),
        );

        return (new MemberPurchaseOrderResource($trx))->additional([
            'success' => true,
            'message' => $trx->trx_status === 'waiting_stock_screening'
                ? 'Pesanan pembelian berhasil dibuat dan menunggu screening stok perusahaan.'
                : 'Pesanan pembelian berhasil dibuat dan menunggu pembayaran.',
        ]);
    }

    public function cancel(Request $request, Trx $trx): MemberPurchaseOrderResource
    {
        return (new MemberPurchaseOrderResource(
            $this->purchaseService->cancel($this->memberAccount($request), $trx)
        ))->additional([
            'success' => true,
            'message' => 'Pesanan pembelian berhasil dibatalkan.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();

        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
