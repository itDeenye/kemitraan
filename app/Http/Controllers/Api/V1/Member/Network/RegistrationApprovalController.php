<?php

namespace App\Http\Controllers\Api\V1\Member\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Network\ApproveRegistrationRequest;
use App\Http\Requests\Api\V1\Member\Network\ListRegistrationApprovalsRequest;
use App\Http\Requests\Api\V1\Member\Network\RejectRegistrationRequest;
use App\Http\Resources\Api\V1\Partnership\MemberRegistrationResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\MemberRegistration;
use App\Services\Partnership\MemberRegistrationService;

class RegistrationApprovalController extends Controller
{
    public function __construct(private readonly MemberRegistrationService $registrationService) {}

    public function index(ListRegistrationApprovalsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new DataTableResource(
            $this->registrationService->distributorRegistrations($request->validated(), (int) $account->member_account_member_id),
            MemberRegistrationResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar persetujuan pendaftaran mitra berhasil dimuat.']);
    }

    public function show(ListRegistrationApprovalsRequest $request, MemberRegistration $registration): MemberRegistrationResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->distributorRegistration($registration, (int) $account->member_account_member_id),
        ))->additional(['success' => true, 'message' => 'Detail persetujuan pendaftaran mitra berhasil dimuat.']);
    }

    public function approve(ApproveRegistrationRequest $request, MemberRegistration $registration): MemberRegistrationResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->approveByDistributor($registration, $account, $request->validated('note')),
        ))->additional(['success' => true, 'message' => 'Pendaftaran mitra disetujui Distributor dan diteruskan ke admin.']);
    }

    public function reject(RejectRegistrationRequest $request, MemberRegistration $registration): MemberRegistrationResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->rejectByDistributor($registration, $account, $request->validated('note')),
        ))->additional(['success' => true, 'message' => 'Pendaftaran mitra ditolak Distributor.']);
    }
}
