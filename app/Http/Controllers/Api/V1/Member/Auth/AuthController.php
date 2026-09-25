<?php

namespace App\Http\Controllers\Api\V1\Member\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Member\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Member\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Member\AuthenticatedMemberResource;
use App\Models\MemberAccount;
use App\Services\Auth\MemberAuthService;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly MemberAuthService $authService,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $result = $this->authService->login(
            $credentials['username'],
            $credentials['password'],
            $credentials['device_name'] ?? 'member-app',
        );
        $account = $result['account']->load([
            'member.level',
            'member.stockist',
            'member.activeBankAccounts',
            'group',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil masuk.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_at' => $result['expires_at'],
                'refresh_token_expires_at' => $result['refresh_token_expires_at'],
                'user' => (new AuthenticatedMemberResource($account))->resolve($request),
            ],
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->authService->refresh($request->validated('refresh_token'));

        return response()->json([
            'success' => true,
            'message' => 'Sesi berhasil diperbarui.',
            'data' => [
                'token_type' => 'Bearer',
                ...$tokens,
            ],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink('member', $request->validated('identifier'));

        return response()->json([
            'success' => true,
            'message' => 'Tautan reset password sudah dikirim ke email Anda. Silakan cek email.',
            'data' => null,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset('member', $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diatur ulang. Silakan masuk kembali.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user()->refresh()->load([
            'member.level',
            'member.stockist',
            'member.activeBankAccounts',
            'group',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil mitra berhasil dimuat.',
            'data' => (new AuthenticatedMemberResource($account))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $this->authService->logout($account);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar.',
            'data' => null,
        ]);
    }
}
