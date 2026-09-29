<?php

namespace App\Http\Controllers\Api\V1\Admin\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reward\ApproveSharingProfitRequest;
use App\Http\Requests\Api\V1\Admin\Reward\ListSharingProfitRequest;
use App\Http\Requests\Api\V1\Admin\Reward\TransferSharingProfitRequest;
use App\Http\Resources\Api\V1\Admin\Reward\AdminSharingProfitDetailResource;
use App\Http\Resources\Api\V1\Admin\Reward\AdminSharingProfitResource;
use App\Http\Resources\DataTableResource;
use App\Models\Member;
use App\Models\SiteAdministrator;
use App\Services\Reward\AdminSharingProfitService;
use Illuminate\Http\JsonResponse;

class SharingProfitController extends Controller
{
    public function __construct(private readonly AdminSharingProfitService $sharingProfitService) {}

    public function index(ListSharingProfitRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->sharingProfitService->list($request->validated()),
            AdminSharingProfitResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar sharing profit (mitra) berhasil dimuat.']);
    }

    public function show(Member $upline, ListSharingProfitRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->sharingProfitService->detail($upline, $request->validated()),
            AdminSharingProfitDetailResource::class,
        ))->additional(['success' => true, 'message' => 'Detail sharing profit mitra berhasil dimuat.']);
    }

    public function approve(ApproveSharingProfitRequest $request): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();
        $processed = $this->sharingProfitService->approve(
            $request->validated('upline_ids'),
            $administrator,
        );

        return response()->json([
            'success' => true,
            'message' => 'Bukti spread payment berhasil disetujui dan siap ditransfer.',
            'data' => ['processed_count' => $processed],
        ]);
    }

    public function transfer(TransferSharingProfitRequest $request): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();
        $processed = $this->sharingProfitService->transfer(
            $request->validated('upline_ids'),
            $administrator,
            $request->validated('note'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer sharing profit berhasil dicatat.',
            'data' => ['processed_count' => $processed],
        ]);
    }
}
