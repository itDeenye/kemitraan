<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ListMemberStocksRequest;
use App\Http\Resources\Api\V1\Admin\AdminMemberStockResource;
use App\Http\Resources\DataTableResource;
use App\Services\Partnership\AdminMemberStockService;

class MemberStockController extends Controller
{
    public function __construct(private readonly AdminMemberStockService $memberStockService) {}

    public function index(ListMemberStocksRequest $request): DataTableResource
    {
        return (new DataTableResource($this->memberStockService->list($request->validated()), AdminMemberStockResource::class))
            ->additional(['success' => true, 'message' => 'Daftar stok mitra berhasil dimuat.']);
    }
}
