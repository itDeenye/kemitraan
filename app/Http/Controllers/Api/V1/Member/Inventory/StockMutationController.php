<?php

namespace App\Http\Controllers\Api\V1\Member\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Inventory\ListStockMutationsRequest;
use App\Http\Resources\Api\V1\Member\MemberStockMutationResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Services\Inventory\MemberInventoryService;

class StockMutationController extends Controller
{
    public function __construct(private readonly MemberInventoryService $inventoryService) {}

    public function index(ListStockMutationsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $memberId = $account->member_account_member_id;

        return (new DataTableResource(
            $this->inventoryService->memberStockMutations($request->validated(), $memberId),
            MemberStockMutationResource::class
        ))->additional(['success' => true, 'message' => 'Daftar mutasi stok berhasil dimuat.']);
    }
}
