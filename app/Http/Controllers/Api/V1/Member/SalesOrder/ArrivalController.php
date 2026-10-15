<?php

namespace App\Http\Controllers\Api\V1\Member\SalesOrder;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Member\MemberSaleOrderResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Sales\MemberSalesService;
use Illuminate\Http\Request;

class ArrivalController extends Controller
{
    public function __construct(private readonly MemberSalesService $salesService) {}

    public function store(Request $request, Trx $trx): MemberSaleOrderResource
    {
        $account = $request->user();
        abort_unless($account instanceof MemberAccount, 401);

        return (new MemberSaleOrderResource(
            $this->salesService->confirmUplineArrival($account, $trx),
        ))->additional([
            'success' => true,
            'message' => 'Kedatangan barang di lokasi pengambilan berhasil dikonfirmasi.',
        ]);
    }
}
