<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\DeactivateMemberRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ListMemberDeactivationOptionsRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ListMembersRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\ResetMemberPasswordRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\SaveMemberRequest;
use App\Http\Resources\Api\V1\Admin\AdminMemberResource;
use App\Http\Resources\DataTableResource;
use App\Models\Member;
use App\Models\SiteAdministrator;
use App\Services\Partnership\AdminMemberService;
use App\Services\Partnership\MemberLifecycleService;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function __construct(
        private readonly AdminMemberService $memberService,
        private readonly MemberLifecycleService $lifecycleService,
    ) {}

    public function index(ListMembersRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->memberService->list($request->validated()),
            AdminMemberResource::class
        ))->additional(['success' => true, 'message' => 'Daftar mitra berhasil dimuat.']);
    }

    public function show(Member $member): AdminMemberResource
    {
        return (new AdminMemberResource($this->memberService->detail($member)))
            ->additional(['success' => true, 'message' => 'Detail mitra berhasil dimuat.']);
    }

    public function update(SaveMemberRequest $request, Member $member): AdminMemberResource
    {
        return (new AdminMemberResource($this->memberService->update($member, $request->validated())))
            ->additional(['success' => true, 'message' => 'Data mitra berhasil diperbarui.']);
    }

    public function deactivationOptions(
        ListMemberDeactivationOptionsRequest $request,
        Member $member,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Opsi sponsor pengganti berhasil dimuat.',
            'data' => $this->lifecycleService->deactivationOptions($member, $request->validated()),
        ]);
    }

    public function deactivate(DeactivateMemberRequest $request, Member $member): JsonResponse
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();
        $result = $this->lifecycleService->deactivate(
            $member,
            $administrator,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Mitra berhasil dinonaktifkan dan jaringan berhasil dipindahkan.',
            'data' => [
                'member' => [
                    'id' => (int) $result['member']->getKey(),
                    'code' => $result['member']->member_code,
                    'name' => $result['member']->member_name,
                    'status' => [
                        'code' => (int) $result['member']->member_status,
                        'label' => 'inactive',
                    ],
                ],
                'moved_downlines' => $result['moved_downlines'],
                'transferred_reward_liabilities' => $result['transferred_reward_liabilities'],
                'cancelled_transactions' => $result['cancelled_transactions'],
                'released_stock_orders' => $result['released_stock_orders'],
            ],
        ]);
    }

    public function resetPassword(
        ResetMemberPasswordRequest $request,
        Member $member,
    ): JsonResponse {
        $member = $this->lifecycleService->resetPassword($member);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset ke tanggal lahir dan informasi login telah dikirim ke email mitra.',
            'data' => [
                'member' => [
                    'id' => (int) $member->getKey(),
                    'code' => $member->member_code,
                    'name' => $member->member_name,
                    'email' => $member->member_email,
                ],
            ],
        ]);
    }
}
