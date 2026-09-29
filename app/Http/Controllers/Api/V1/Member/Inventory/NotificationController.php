<?php

namespace App\Http\Controllers\Api\V1\Member\Inventory;

use App\Http\Controllers\Controller;
use App\Models\MemberAccount;
use App\Models\Notification;
use App\Services\Inventory\MemberInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly MemberInventoryService $inventoryService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $memberId = $account->member_account_member_id;

        $notifications = $this->inventoryService->notifications($memberId);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi mitra berhasil dimuat.',
            'data' => $notifications,
        ]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $notification = $this->inventoryService->markNotificationRead(
            (int) $account->member_account_member_id,
            $notification,
        );

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil ditandai sudah dibaca.',
            'data' => [
                'id' => (int) $notification->getKey(),
                'is_read' => (bool) $notification->notification_is_read,
                'read_at' => $notification->notification_read_datetime,
                'unread_count' => $this->inventoryService->unreadNotificationCount(
                    (int) $account->member_account_member_id,
                ),
            ],
        ]);
    }
}
