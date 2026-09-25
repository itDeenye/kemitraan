<?php

namespace App\Http\Controllers\Api\V1\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Product\ListPromotionsRequest;
use App\Http\Requests\Api\V1\Admin\Product\SavePromotionRequest;
use App\Http\Resources\Api\V1\Admin\AdminPromotionResource;
use App\Http\Resources\DataTableResource;
use App\Models\Promotion;
use App\Services\Product\AdminPromotionService;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    public function __construct(private readonly AdminPromotionService $promotionService) {}

    public function index(ListPromotionsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->promotionService->promotions($request->validated()),
            AdminPromotionResource::class
        ))->additional(['success' => true, 'message' => 'Daftar promosi produk berhasil dimuat.']);
    }

    public function show(Promotion $promotion): AdminPromotionResource
    {
        return (new AdminPromotionResource($this->promotionService->promotion($promotion)))
            ->additional(['success' => true, 'message' => 'Detail promosi produk berhasil dimuat.']);
    }

    public function store(SavePromotionRequest $request): AdminPromotionResource
    {
        return (new AdminPromotionResource(
            $this->promotionService->createPromotion($request->validated())
        ))->additional(['success' => true, 'message' => 'Promosi produk berhasil dibuat.']);
    }

    public function update(
        SavePromotionRequest $request,
        Promotion $promotion
    ): AdminPromotionResource {
        return (new AdminPromotionResource(
            $this->promotionService->updatePromotion($promotion, $request->validated())
        ))->additional(['success' => true, 'message' => 'Promosi produk berhasil diperbarui.']);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->promotionService->deletePromotion($promotion);

        return response()->json([
            'success' => true,
            'message' => 'Promosi produk berhasil dihapus.',
            'data' => null,
        ]);
    }
}
