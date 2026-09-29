<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Media\InitiateMediaUploadRequest;
use App\Http\Requests\Api\V1\Media\UploadMediaChunkRequest;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Models\Media;
use App\Services\Media\MediaUploadService;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UploadController extends Controller
{
    public function __construct(private readonly MediaUploadService $mediaUploadService) {}

    public function store(InitiateMediaUploadRequest $request): MediaResource
    {
        return (new MediaResource(
            $this->mediaUploadService->initiate($request->user(), $request->validated())
        ))->additional(['success' => true, 'message' => 'Sesi unggah media berhasil dibuat.']);
    }

    public function show(Media $media): MediaResource
    {
        Gate::authorize('view', $media);

        return (new MediaResource($media))
            ->additional(['success' => true, 'message' => 'Status unggah media berhasil dimuat.']);
    }

    public function chunk(
        UploadMediaChunkRequest $request,
        Media $media,
        int $chunk
    ): MediaResource {
        return (new MediaResource(
            $this->mediaUploadService->uploadChunk($media, $chunk, $request->file('chunk'))
        ))->additional(['success' => true, 'message' => "Bagian berkas ke-{$chunk} berhasil diunggah."]);
    }

    public function complete(Media $media): JsonResponse
    {
        Gate::authorize('update', $media);
        $media = $this->mediaUploadService->complete($media);

        return response()->json([
            'success' => true,
            'message' => 'Media berhasil diproses.',
            'data' => (new MediaResource($media))->resolve(request()),
        ]);
    }

    public function content(Media $media, ?string $extension = null): BinaryFileResponse
    {
        if (! request()->hasValidSignature()) {
            Gate::authorize('view', $media);
        }

        abort_unless(
            $media->media_status === Media::STATUS_READY && $media->media_path !== null,
            404
        );

        $storedExtension = MediaUrl::extension($media->media_path);
        abort_unless(
            $extension === null
                || ($storedExtension !== null && Str::lower($extension) === $storedExtension),
            404,
        );

        return response()->file(
            Storage::disk($media->media_disk)->path($media->media_path),
            ['Content-Type' => $media->media_mime_type]
        );
    }

    public function publicContent(Media $media, string $extension): BinaryFileResponse
    {
        abort_unless(
            ($media->media_disk === 'public'
                || config("media.collections.{$media->media_collection}.disk") === 'public')
                && $media->media_status === Media::STATUS_READY
                && $media->media_path !== null,
            404,
        );

        $storedExtension = MediaUrl::extension($media->media_path);
        abort_unless(
            $storedExtension !== null && Str::lower($extension) === $storedExtension,
            404,
        );

        return response()->file(
            Storage::disk($media->media_disk)->path($media->media_path),
            [
                'Content-Type' => $media->media_mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ],
        );
    }

    public function destroy(Media $media): JsonResponse
    {
        Gate::authorize('delete', $media);
        $this->mediaUploadService->abort($media);

        return response()->json([
            'success' => true,
            'message' => 'Unggahan media berhasil dibatalkan.',
            'data' => null,
        ]);
    }
}
