<?php

namespace App\Http\Controllers\Api\V1\Member\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Account\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Member\Account\UpdateProfilePhotoRequest;
use App\Http\Requests\Api\V1\Member\Account\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Member\MemberProfileResource;
use App\Models\MemberAccount;
use App\Services\Account\MemberAccountService;
use App\Services\Reward\AnnualRewardReportService;
use App\Services\Reward\MemberMonthlyRewardService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly MemberAccountService $accountService,
        private readonly MemberMonthlyRewardService $monthlyRewardService,
        private readonly AnnualRewardReportService $annualRewardService,
    ) {}

    public function show(): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diambil.',
            'data' => $this->profileData($account),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        $this->accountService->updateProfile($account->member, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $this->profileData($account->fresh()),
        ]);
    }

    public function updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        $this->accountService->updateProfilePhoto(
            $account->member,
            $request->validated('image_url'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => $this->profileData($account->fresh()),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        $data = $request->validated();
        $this->accountService->updatePassword($account, $data['current_password'], $data['password']);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
            'data' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function profileData(MemberAccount $account): array
    {
        $account->loadMissing([
            'group',
            'member.level',
            'member.parent',
            'member.stockist',
            'member.defaultAddress.province',
            'member.defaultAddress.city',
            'member.defaultAddress.district',
            'member.defaultAddress.subdistrict',
            'member.defaultAddress.country',
            'member.defaultBankAccount.bank',
        ]);
        $memberId = (int) $account->member_account_member_id;
        $year = (int) now()->year;
        $month = (int) now()->month;
        $monthly = $this->monthlyRewardService->summary($memberId, [
            'year' => $year,
            'month' => $month,
        ]);
        $annual = $this->annualRewardService->memberReport($memberId, $year);
        $profile = (new MemberProfileResource($account))->resolve(request());

        $profile['reward_summary'] = [
            'monthly' => [
                'year' => $year,
                'month' => $month,
                'total_reward' => $monthly['accumulated'],
                'total_paid' => $monthly['paid'],
                'total_available' => $monthly['unpaid'],
            ],
            'annual' => [
                'year' => $year,
                'total_points' => $annual['total_points'],
            ],
        ];

        return $profile;
    }
}
