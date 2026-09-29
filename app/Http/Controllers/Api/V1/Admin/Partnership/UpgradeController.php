<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ApproveMemberUpgradeRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ListMemberUpgradesRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\RejectMemberUpgradeRequest;
use App\Http\Resources\Api\V1\Admin\AdminMemberUpgradeResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberUpgradeQualified;
use App\Models\SiteAdministrator;
use App\Services\Partnership\MemberLevelChangeService;

/**
 * UpgradeController
 * Module: Admin / Menu: Kemitraan / Submenu: Approval Upgrade
 */
class UpgradeController extends Controller
{
    public function __construct(private readonly MemberLevelChangeService $levelChangeService) {}

    public function index(ListMemberUpgradesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->levelChangeService->upgrades($request->validated()),
            AdminMemberUpgradeResource::class
        ))->additional([
            'success' => true,
            'message' => 'Daftar kualifikasi upgrade berhasil dimuat.',
        ]);
    }

    public function show(MemberUpgradeQualified $upgrade): AdminMemberUpgradeResource
    {
        return (new AdminMemberUpgradeResource($this->levelChangeService->upgrade($upgrade)))
            ->additional([
                'success' => true,
                'message' => 'Detail kualifikasi upgrade berhasil dimuat.',
            ]);
    }

    public function approve(
        ApproveMemberUpgradeRequest $request,
        MemberUpgradeQualified $upgrade
    ): AdminMemberUpgradeResource {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminMemberUpgradeResource($this->levelChangeService->approveUpgrade(
            $upgrade,
            $administrator,
            $request->validated('note')
        )))->additional([
            'success' => true,
            'message' => 'Kenaikan tingkat berhasil disetujui dan dijadwalkan bulan berikutnya.',
        ]);
    }

    public function reject(
        RejectMemberUpgradeRequest $request,
        MemberUpgradeQualified $upgrade
    ): AdminMemberUpgradeResource {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminMemberUpgradeResource(
            $this->levelChangeService->rejectUpgrade($upgrade, $administrator)
        ))->additional([
            'success' => true,
            'message' => 'Kualifikasi upgrade berhasil ditolak.',
        ]);
    }
}
