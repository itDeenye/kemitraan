<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListAdministratorsRequest;
use App\Http\Requests\Api\V1\Admin\System\SaveAdministratorRequest;
use App\Http\Requests\Api\V1\Admin\System\UpdateAdministratorPasswordRequest;
use App\Http\Resources\Api\V1\Admin\AdminAdministratorResource;
use App\Http\Resources\DataTableResource;
use App\Models\SiteAdministrator;
use App\Services\System\AdminAdministratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdministratorController extends Controller
{
    public function __construct(private readonly AdminAdministratorService $administratorService) {}

    public function index(ListAdministratorsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->administratorService->administrators($request->validated()),
            AdminAdministratorResource::class
        ))->additional(['success' => true, 'message' => 'Daftar administrator berhasil dimuat.']);
    }

    public function show(SiteAdministrator $administrator): AdminAdministratorResource
    {
        return (new AdminAdministratorResource(
            $this->administratorService->administrator($administrator)
        ))->additional(['success' => true, 'message' => 'Detail administrator berhasil dimuat.']);
    }

    public function store(SaveAdministratorRequest $request): AdminAdministratorResource
    {
        return (new AdminAdministratorResource(
            $this->administratorService->createAdministrator($request->validated())
        ))->additional(['success' => true, 'message' => 'Administrator berhasil dibuat.']);
    }

    public function update(
        SaveAdministratorRequest $request,
        SiteAdministrator $administrator
    ): AdminAdministratorResource {
        return (new AdminAdministratorResource(
            $this->administratorService->updateAdministrator(
                $administrator,
                $request->validated(),
                (int) $request->user()->getAuthIdentifier()
            )
        ))->additional(['success' => true, 'message' => 'Administrator berhasil diperbarui.']);
    }

    public function updatePassword(
        UpdateAdministratorPasswordRequest $request,
        SiteAdministrator $administrator
    ): AdminAdministratorResource {
        return (new AdminAdministratorResource(
            $this->administratorService->updatePassword(
                $administrator,
                $request->validated('password')
            )
        ))->additional(['success' => true, 'message' => 'Kata sandi administrator berhasil diperbarui.']);
    }

    public function destroy(Request $request, SiteAdministrator $administrator): JsonResponse
    {
        $this->administratorService->deleteAdministrator(
            $administrator,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'success' => true,
            'message' => 'Administrator berhasil dihapus.',
            'data' => null,
        ]);
    }
}
