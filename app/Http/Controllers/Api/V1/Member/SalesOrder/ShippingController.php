<?php

namespace App\Http\Controllers\Api\V1\Member\SalesOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Sales\ShipSaleOrderRequest;
use App\Http\Resources\Api\V1\Member\MemberSaleOrderResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Sales\MemberSalesService;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private readonly MemberSalesService $salesService) {}

    public function store(ShipSaleOrderRequest $request, Trx $trx): MemberSaleOrderResource
    {
        return (new MemberSaleOrderResource(
            $this->salesService->ship(
                $this->memberAccount($request),
                $trx,
                $request->validated()
            )
        ))->additional([
            'success' => true,
            'message' => 'Pengiriman pesanan penjualan berhasil diproses.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();
        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
