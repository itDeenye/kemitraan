<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\ReturnModel;
use App\Models\SiteAdministrator;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use Illuminate\Contracts\Auth\Authenticatable;

class MediaPolicy
{
    public function view(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media)
            || ($user instanceof SiteAdministrator && $this->isBusinessAttachment($media));
    }

    public function update(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    public function delete(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    private function owns(Authenticatable $user, Media $media): bool
    {
        return $media->media_uploader_type === $user->getMorphClass()
            && $media->media_uploader_id === (int) $user->getAuthIdentifier();
    }

    private function isBusinessAttachment(Media $media): bool
    {
        $uuidPattern = "%{$media->media_uuid}%";

        return TrxPaymentTransfer::query()
            ->where('payment_transfer_receipt_file', 'like', $uuidPattern)
            ->exists()
            || TrxSpreadPayment::query()
                ->where('trx_spread_payment_receipt_file', 'like', $uuidPattern)
                ->exists()
            || ReturnModel::query()
                ->where(function ($query) use ($media): void {
                    $query->where('return_attachment_image_url_json', 'like', "%{$media->media_uuid}%")
                        ->orWhere('return_attachment_video_url', 'like', "%{$media->media_uuid}%");
                })
                ->exists();
    }
}
