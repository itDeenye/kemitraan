<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ListDowngradeSponsorOptionsRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ListMemberDowngradesRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ScheduleMemberDowngradeRequest;
use App\Http\Resources\Api\V1\Admin\AdminMemberNetworkSwitchResource;
use App\Http\Resources\DataTableResource;
use App\Models\Member;
use App\Models\MemberNetworkSwitch;
use App\Models\SiteAdministrator;
use App\Services\Partnership\MemberLevelChangeService;
use Illuminate\Http\JsonResponse;

/**
 * MemberDowngradeController
 * Module: Admin / Menu: Kemitraan / Submenu: Downgrade Mitra
 */
class MemberDowngradeController extends Controller
{
    public function __construct(private readonly MemberLevelChangeService $levelChangeService) {}

    public function index(ListMemberDowngradesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->levelChangeService->downgrades($request->validated()),
            AdminMemberNetworkSwitchResource::class
        ))->additional([
            'success' => true,
            'message' => 'Daftar downgrade mitra berhasil dimuat.',
        ]);
    }

    public function sponsorOptions(ListDowngradeSponsorOptionsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Opsi sponsor baru untuk downgrade berhasil dimuat.',
            'data' => $this->levelChangeService->downgradeSponsorOptions($request->validated()),
        ]);
    }

    public function show(MemberNetworkSwitch $downgrade): AdminMemberNetworkSwitchResource
    {
        return (new AdminMemberNetworkSwitchResource(
            $this->levelChangeService->downgrade($downgrade)
        ))->additional([
            'success' => true,
            'message' => 'Detail downgrade mitra berhasil dimuat.',
        ]);
    }

    public function store(
        ScheduleMemberDowngradeRequest $request,
        Member $member
    ): AdminMemberNetworkSwitchResource {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminMemberNetworkSwitchResource(
            $this->levelChangeService->scheduleDowngrade(
                $member,
                $administrator,
                $request->validated()
            )
        ))->additional([
            'success' => true,
            'message' => 'Penurunan tingkat dan pemindahan mitra bawahan berhasil dijadwalkan bulan berikutnya.',
        ]);
    }
}
