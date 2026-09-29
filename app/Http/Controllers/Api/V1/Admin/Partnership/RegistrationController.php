<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ApproveRegistrationRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ListRegistrationsRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\RejectRegistrationRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\StoreDistributorRegistrationRequest;
use App\Http\Resources\Api\V1\Partnership\MemberRegistrationResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberRegistration;
use App\Models\SiteAdministrator;
use App\Services\Partnership\MemberRegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly MemberRegistrationService $registrationService) {}

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan formulir Distributor berhasil dimuat.',
            'data' => $this->registrationService->adminOptions(),
        ]);
    }

    public function index(ListRegistrationsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->registrationService->adminRegistrations($request->validated()),
            MemberRegistrationResource::class
        ))->additional(['success' => true, 'message' => 'Daftar pengajuan mitra berhasil dimuat.']);
    }

    public function show(MemberRegistration $registration): MemberRegistrationResource
    {
        return (new MemberRegistrationResource(
            $this->registrationService->adminRegistration($registration)
        ))->additional(['success' => true, 'message' => 'Detail pengajuan mitra berhasil dimuat.']);
    }

    public function store(
        StoreDistributorRegistrationRequest $request
    ): MemberRegistrationResource {
        return (new MemberRegistrationResource(
            $this->registrationService->createDistributor($request->validated())
        ))->additional(['success' => true, 'message' => 'Pendaftaran Distributor berhasil diajukan.']);
    }

    public function approve(
        ApproveRegistrationRequest $request,
        MemberRegistration $registration
    ): MemberRegistrationResource {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->approve(
                $registration,
                $administrator,
                $request->validated('note')
            )
        ))->additional(['success' => true, 'message' => 'Pendaftaran mitra berhasil disetujui.']);
    }

    public function reject(
        RejectRegistrationRequest $request,
        MemberRegistration $registration
    ): MemberRegistrationResource {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->reject(
                $registration,
                $administrator,
                $request->validated('note')
            )
        ))->additional(['success' => true, 'message' => 'Pendaftaran mitra berhasil ditolak.']);
    }
}
