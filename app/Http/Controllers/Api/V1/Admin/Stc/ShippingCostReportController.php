<?php

namespace App\Http\Controllers\Api\V1\Admin\Stc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Stc\ListShippingCostsRequest;
use App\Services\Stc\ShippingCostReportService;
use Illuminate\Http\JsonResponse;

class ShippingCostReportController extends Controller
{
    public function __invoke(ListShippingCostsRequest $request, ShippingCostReportService $service): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Laporan ongkir non-pembeli berhasil dimuat.',
            'data' => $service->report($request->validated())]);
    }
}
