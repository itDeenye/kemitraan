<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\Media\MediaUploadService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMediaUpload implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $mediaId) {}

    public function handle(MediaUploadService $mediaUploadService): void
    {
        $mediaUploadService->process($this->mediaId);
    }

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function failed(?Throwable $exception): void
    {
        Media::query()->whereKey($this->mediaId)->update([
            'media_status' => Media::STATUS_FAILED,
            'media_error_message' => 'Media gagal diproses. Silakan unggah kembali.',
        ]);

        Log::error('Pemrosesan media gagal.', [
            'media_id' => $this->mediaId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
