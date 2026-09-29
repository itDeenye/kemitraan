<?php

namespace App\Http\Controllers\Api\V1\Member\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Inventory\ListMemberStockAdjustmentsRequest;
use App\Http\Requests\Api\V1\Member\Inventory\SaveMemberStockAdjustmentRequest;
use App\Http\Requests\Api\V1\Member\Inventory\ViewMemberStockAdjustmentRequest;
use App\Http\Resources\Api\V1\Member\MemberStockAdjustmentResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\MemberStockAdjustment;
use App\Services\Inventory\MemberInventoryService;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly MemberInventoryService $inventoryService) {}

    public function index(ListMemberStockAdjustmentsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new DataTableResource(
            $this->inventoryService->stockAdjustments($request->validated(), $account->member_account_member_id),
            MemberStockAdjustmentResource::class
        ))->additional(['success' => true, 'message' => 'Daftar penyesuaian stok berhasil dimuat.']);
    }

    public function show(
        MemberStockAdjustment $adjustment,
        ViewMemberStockAdjustmentRequest $request,
    ): MemberStockAdjustmentResource {
        /** @var MemberAccount $account */
        $account = $request->user();

        abort_unless(
            $adjustment->stock_adjustment_member_id === $account->member_account_member_id,
            404,
        );

        return (new MemberStockAdjustmentResource($this->inventoryService->stockAdjustment($adjustment)))
            ->additional(['success' => true, 'message' => 'Detail penyesuaian stok berhasil dimuat.']);
    }

    public function store(SaveMemberStockAdjustmentRequest $request): MemberStockAdjustmentResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberStockAdjustmentResource(
            $this->inventoryService->createStockAdjustment($account, $request->validated())
        ))->additional(['success' => true, 'message' => 'Penyesuaian stok berhasil disimpan.']);
    }
}
