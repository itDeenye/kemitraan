<?php

namespace App\Http\Controllers\Api\V1\Member\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Inventory\ListStocksRequest;
use App\Http\Resources\Api\V1\Member\MemberStockResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Services\Inventory\MemberInventoryService;

class StockController extends Controller
{
    public function __construct(private readonly MemberInventoryService $inventoryService) {}

    public function index(ListStocksRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $memberId = $account->member_account_member_id;

        return (new DataTableResource(
            $this->inventoryService->memberStocks($request->validated(), $memberId),
            MemberStockResource::class
        ))->additional(['success' => true, 'message' => 'Stok terkini berhasil dimuat.']);
    }
}
