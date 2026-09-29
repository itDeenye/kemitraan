<?php

namespace App\Http\Resources\Api\V1\Media;

use App\Http\Resources\ApiResource;
use App\Models\Media;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $progress = $this->media_total_chunks > 0
            ? (int) floor(($this->media_uploaded_chunks / $this->media_total_chunks) * 100)
            : 0;

        return [
            'id' => $this->media_uuid,
            'collection' => $this->media_collection,
            'original_name' => $this->media_original_name,
            'original_mime_type' => $this->media_original_mime_type,
            'original_size' => $this->media_original_size,
            'mime_type' => $this->media_mime_type,
            'size' => $this->media_size,
            'chunk_size' => $this->media_chunk_size,
            'total_chunks' => $this->media_total_chunks,
            'uploaded_chunks' => $this->media_uploaded_chunks,
            'progress' => $this->media_status === Media::STATUS_READY ? 100 : $progress,
            'status' => $this->media_status,
            'url' => $this->when(
                $this->media_status === Media::STATUS_READY,
                fn (): ?string => $this->media_disk === 'public'
                    ? MediaUrl::publicContentUrl($this->resource)
                    : MediaUrl::privateContentUrl(
                        $this->resource,
                        Str::startsWith($request->path(), 'api/v1/admin/') ? 'admin' : 'member',
                    )
            ),
            'error' => $this->when(
                $this->media_status === Media::STATUS_FAILED,
                $this->media_error_message
            ),
            'expires_at' => $this->media_expires_at?->toIso8601String(),
        ];
    }
}
