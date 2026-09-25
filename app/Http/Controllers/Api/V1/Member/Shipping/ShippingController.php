<?php

namespace App\Http\Controllers\Api\V1\Member\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Shipping\InstantShippingRateRequest;
use App\Http\Requests\Api\V1\Member\Shipping\ShippingRateRequest;
use App\Models\MemberAccount;
use App\Services\Shipping\MemberShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private readonly MemberShippingService $shippingService) {}

    public function rates(ShippingRateRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tarif pengiriman berhasil dimuat.',
            'data' => $this->shippingService->purchaseExpressRates(
                $this->memberAccount($request),
                $request->validated(),
            ),
        ]);
    }

    public function instantRates(InstantShippingRateRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tarif pengiriman instan berhasil dimuat.',
            'data' => $this->shippingService->instantRates(
                $this->memberAccount($request),
                $request->validated(),
            ),
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();

        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
