<?php

namespace App\Http\Controllers\Api\V1\Member\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Purchase\ListCatalogProductsRequest;
use App\Http\Resources\DataTableResource;
use App\Http\Resources\Api\V1\Member\MemberProductResource;
use App\Http\Resources\Api\V1\Member\ProductCategoryResource;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Services\Catalog\MemberCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CatalogController
 * Module: Member
 * Menu: Purchase
 * API: Member product catalog
 */
class CatalogController extends Controller
{
    public function __construct(private readonly MemberCatalogService $catalogService) {}

    public function index(ListCatalogProductsRequest $request): DataTableResource
    {
        $products = $this->catalogService->list(
            $this->memberAccount($request),
            $request->validated()
        );

        return (new DataTableResource($products, MemberProductResource::class))->additional([
            'success' => true,
            'message' => 'Katalog produk berhasil dimuat.',
        ]);
    }

    public function show(ListCatalogProductsRequest $request, Product $product): MemberProductResource
    {
        return (new MemberProductResource(
            $this->catalogService->product(
                $this->memberAccount($request),
                $product,
                $request->validated('warehouse_id')
            )
        ))->additional([
            'success' => true,
            'message' => 'Detail produk berhasil dimuat.',
        ]);
    }

    public function categories(): AnonymousResourceCollection
    {
        return ProductCategoryResource::collection($this->catalogService->categories())->additional([
            'success' => true,
            'message' => 'Kategori produk berhasil dimuat.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();

        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
