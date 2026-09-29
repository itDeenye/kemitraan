<?php

namespace App\Http\Controllers\Api\V1\Member\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Purchase\ConfirmGoodsReceiveRequest;
use App\Http\Requests\Api\V1\Member\Purchase\ListGoodsReceivesRequest;
use App\Http\Resources\Api\V1\Member\MemberGoodsReceiveResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\Trx;
use App\Services\Purchase\MemberGoodsReceiveService;
use Illuminate\Http\Request;

class GoodsReceiveController extends Controller
{
    public function __construct(private readonly MemberGoodsReceiveService $goodsReceiveService) {}

    public function index(ListGoodsReceivesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->goodsReceiveService->goodsReceives(
                $this->memberAccount($request),
                $request->validated()
            ),
            MemberGoodsReceiveResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar penerimaan barang berhasil dimuat.',
        ]);
    }

    public function show(Request $request, Trx $trx): MemberGoodsReceiveResource
    {
        return (new MemberGoodsReceiveResource(
            $this->goodsReceiveService->goodsReceive($this->memberAccount($request), $trx)
        ))->additional([
            'success' => true,
            'message' => 'Detail penerimaan barang berhasil dimuat.',
        ]);
    }

    public function confirm(
        ConfirmGoodsReceiveRequest $request,
        Trx $trx,
    ): MemberGoodsReceiveResource {
        return (new MemberGoodsReceiveResource(
            $this->goodsReceiveService->confirm(
                $this->memberAccount($request),
                $trx,
                $request->validated()
            )
        ))->additional([
            'success' => true,
            'message' => 'Penerimaan barang berhasil dikonfirmasi dan stok telah ditambahkan.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();

        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
