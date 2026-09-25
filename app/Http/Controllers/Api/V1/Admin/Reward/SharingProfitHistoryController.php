<?php

namespace App\Http\Controllers\Api\V1\Admin\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reward\ListSharingProfitHistoryRequest;
use App\Http\Resources\Api\V1\Admin\Reward\AdminSharingProfitHistoryResource;
use App\Http\Resources\DataTableResource;
use App\Services\Reward\AdminSharingProfitService;

class SharingProfitHistoryController extends Controller
{
    public function __construct(private readonly AdminSharingProfitService $sharingProfitService) {}

    public function index(ListSharingProfitHistoryRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->sharingProfitService->historyList($request->validated()),
            AdminSharingProfitHistoryResource::class,
        ))->additional(['success' => true, 'message' => 'Riwayat sharing profit berhasil dimuat.']);
    }

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        $detail = $this->sharingProfitService->historyDetail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail riwayat sharing profit berhasil dimuat.',
            'data' => new AdminSharingProfitHistoryResource($detail),
        ]);
    }
}
