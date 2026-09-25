<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\ReturnModel;
use App\Models\Trx;
use Illuminate\Support\Str;
use Throwable;

class MemberNotificationService
{
    /** @var list<string> */
    private const MEMBER_TYPES = ['distributor', 'agent', 'reseller'];

    public function transactionBuyer(
        Trx $trx,
        string $title,
        string $content,
        string $category = 'transaction',
    ): ?Notification {
        if (! in_array($trx->trx_buyer_type, self::MEMBER_TYPES, true)) {
            return null;
        }

        return $this->create(
            (int) $trx->trx_buyer_id,
            $title,
            $content,
            $category,
            'trx_purchase',
            (int) $trx->getKey(),
        );
    }

    public function transactionSeller(
        Trx $trx,
        string $title,
        string $content,
        string $category = 'transaction',
    ): ?Notification {
        if (! in_array($trx->trx_seller_type, self::MEMBER_TYPES, true)) {
            return null;
        }

        return $this->create(
            (int) $trx->trx_seller_id,
            $title,
            $content,
            $category,
            'trx_sale',
            (int) $trx->getKey(),
        );
    }

    public function returnStatus(
        ReturnModel $return,
        string $title,
        string $content,
    ): ?Notification {
        return $this->create(
            (int) $return->return_member_id,
            $title,
            $content,
            'return',
            'return',
            (int) $return->getKey(),
        );
    }

    public function create(
        int $memberId,
        string $title,
        string $content,
        string $category,
        string $referenceTable,
        int $referenceId,
    ): ?Notification {
        if ($memberId < 1 || $referenceId < 1) {
            return null;
        }

        try {
            return Notification::query()->create([
                'notification_user_type' => 'member',
                'notification_user_id' => $memberId,
                'notification_title' => Str::limit($title, 150, ''),
                'notification_content' => $content,
                'notification_category' => $category,
                'notification_ref_table' => $referenceTable,
                'notification_ref_id' => $referenceId,
                'notification_is_read' => 0,
                'notification_read_datetime' => null,
                'notification_created_datetime' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
