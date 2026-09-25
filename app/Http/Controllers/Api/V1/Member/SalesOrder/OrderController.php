<?php

namespace App\Http\Controllers\Api\V1\Member\SalesOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Sales\CreateSaleOrderRequest;
use App\Http\Requests\Api\V1\Member\Sales\ListSaleOrdersRequest;
use App\Http\Requests\Api\V1\Member\Sales\ListSaleProductsRequest;
use App\Http\Resources\Api\V1\Member\MemberSaleOrderResource;
use App\Http\Resources\Api\V1\Member\MemberSaleProductResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Sales\MemberSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrderController
 * Module: Member
 * Menu: SalesOrder
 */
class OrderController extends Controller
{
    public function __construct(private readonly MemberSalesService $salesService) {}

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan penjualan berhasil dimuat.',
            'data' => $this->salesService->options($this->memberAccount($request)),
        ]);
    }

    public function products(ListSaleProductsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->salesService->products($this->memberAccount($request), $request->validated()),
            MemberSaleProductResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Katalog produk penjualan berhasil dimuat.',
        ]);
    }

    public function index(ListSaleOrdersRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->salesService->orders($this->memberAccount($request), $request->validated()),
            MemberSaleOrderResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar pesanan penjualan berhasil dimuat.',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ringkasan status pesanan penjualan berhasil dimuat.',
            'data' => $this->salesService->orderSummary($this->memberAccount($request)),
        ]);
    }

    public function show(Request $request, Trx $trx): MemberSaleOrderResource
    {
        return (new MemberSaleOrderResource(
            $this->salesService->order($this->memberAccount($request), $trx)
        ))->additional([
            'success' => true,
            'message' => 'Detail pesanan penjualan berhasil dimuat.',
        ]);
    }

    public function store(CreateSaleOrderRequest $request): MemberSaleOrderResource
    {
        return (new MemberSaleOrderResource(
            $this->salesService->checkout($this->memberAccount($request), $request->validated())
        ))->additional([
            'success' => true,
            'message' => 'Pesanan penjualan berhasil dibuat.',
        ]);
    }

    public function cancel(Request $request, Trx $trx): MemberSaleOrderResource
    {
        return (new MemberSaleOrderResource(
            $this->salesService->cancel($this->memberAccount($request), $trx)
        ))->additional([
            'success' => true,
            'message' => 'Pesanan penjualan berhasil dibatalkan.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();
        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
