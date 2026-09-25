<?php

namespace App\Http\Controllers\Api\V1\Member\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Network\ListRegistrationsRequest;
use App\Http\Requests\Api\V1\Member\Network\StoreRegistrationRequest;
use App\Http\Resources\Api\V1\Partnership\MemberRegistrationResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\MemberRegistration;
use App\Services\Partnership\MemberRegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly MemberRegistrationService $registrationService) {}

    public function options(ListRegistrationsRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $sponsor = $account->member()->with('level')->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Pilihan formulir pendaftaran mitra berhasil dimuat.',
            'data' => $this->registrationService->memberOptions($sponsor),
        ]);
    }

    public function index(ListRegistrationsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new DataTableResource(
            $this->registrationService->memberRegistrations(
                $request->validated(),
                (int) $account->member_account_member_id
            ),
            MemberRegistrationResource::class
        ))->additional(['success' => true, 'message' => 'Daftar pendaftaran mitra berhasil dimuat.']);
    }

    public function show(
        MemberRegistration $registration,
        ListRegistrationsRequest $request
    ): MemberRegistrationResource {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->memberRegistration(
                $registration,
                (int) $account->member_account_member_id
            )
        ))->additional(['success' => true, 'message' => 'Detail pendaftaran mitra berhasil dimuat.']);
    }

    public function store(StoreRegistrationRequest $request): MemberRegistrationResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberRegistrationResource(
            $this->registrationService->createByMember($account, $request->validated())
        ))->additional(['success' => true, 'message' => 'Pendaftaran mitra berhasil diajukan.']);
    }
}
