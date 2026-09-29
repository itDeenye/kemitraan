<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Admin\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\Admin\AdminMenuResource;
use App\Http\Resources\Api\V1\Admin\AuthenticatedAdminResource;
use App\Models\SiteAdministrator;
use App\Services\Auth\AdminAuthService;
use App\Services\Auth\PasswordResetService;
use App\Services\Menu\AdminMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $authService,
        private readonly AdminMenuService $menuService,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $result = $this->authService->login(
            $credentials['username'],
            $credentials['password'],
            $credentials['device_name'] ?? 'admin-web',
        );
        $administrator = $result['account'];

        return response()->json([
            'success' => true,
            'message' => 'Login administrator berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_at' => $result['expires_at'],
                'refresh_token_expires_at' => $result['refresh_token_expires_at'],
                'user' => (new AuthenticatedAdminResource($administrator))->resolve($request),
                'menus' => AdminMenuResource::collection($this->menuService->for($administrator))->resolve($request),
            ],
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->authService->refresh($request->validated('refresh_token'));

        return response()->json([
            'success' => true,
            'message' => 'Sesi administrator berhasil diperbarui.',
            'data' => [
                'token_type' => 'Bearer',
                ...$tokens,
            ],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink('admin', $request->validated('identifier'));

        return response()->json([
            'success' => true,
            'message' => 'Tautan reset password sudah dikirim ke email Anda. Silakan cek email.',
            'data' => null,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset('admin', $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Password administrator berhasil diatur ulang. Silakan masuk kembali.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user()->loadMissing('group');

        return response()->json([
            'success' => true,
            'message' => 'Profil administrator berhasil diambil.',
            'data' => (new AuthenticatedAdminResource($administrator))->resolve($request),
        ]);
    }

    public function menus(Request $request): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Menu administrator berhasil diambil.',
            'data' => AdminMenuResource::collection($this->menuService->for($administrator))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();
        $this->authService->logout($administrator);

        return response()->json([
            'success' => true,
            'message' => 'Logout administrator berhasil.',
            'data' => null,
        ]);
    }
}
