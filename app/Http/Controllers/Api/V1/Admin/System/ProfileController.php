<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\UpdateAdminPasswordRequest;
use App\Http\Requests\Api\V1\Admin\System\UpdateAdminProfileRequest;
use App\Http\Resources\Api\V1\Admin\AuthenticatedAdminResource;
use App\Models\SiteAdministrator;
use App\Services\System\AdminProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProfileController
 * Module: Admin
 * Menu: System
 * Submenu: Profile
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AdminProfileService $profileService) {}

    public function show(Request $request): AuthenticatedAdminResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AuthenticatedAdminResource($this->profileService->profile($administrator)))
            ->additional(['success' => true, 'message' => 'Profil administrator berhasil dimuat.']);
    }

    public function update(UpdateAdminProfileRequest $request): AuthenticatedAdminResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AuthenticatedAdminResource(
            $this->profileService->updateProfile($administrator, $request->validated())
        ))->additional(['success' => true, 'message' => 'Profil administrator berhasil diperbarui.']);
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();
        $this->profileService->updatePassword($administrator, $request->validated('password'));

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi administrator berhasil diperbarui.',
            'data' => null,
        ]);
    }
}
