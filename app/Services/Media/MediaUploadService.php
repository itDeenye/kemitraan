<?php

namespace App\Services\Media;

use App\Exceptions\ProcessException;
use App\Models\Media;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class MediaUploadService
{
    /** @param array<string, mixed> $data */
    public function initiate(Authenticatable $uploader, array $data): Media
    {
        $chunkSize = config('media.chunk_size');

        return Media::query()->create([
            'media_uuid' => (string) Str::uuid(),
            'media_uploader_type' => $uploader->getMorphClass(),
            'media_uploader_id' => (int) $uploader->getAuthIdentifier(),
            'media_collection' => $data['collection'],
            'media_original_name' => Str::limit(basename($data['filename']), 255, ''),
            'media_original_mime_type' => $data['mime_type'],
            'media_original_size' => $data['size'],
            'media_chunk_size' => $chunkSize,
            'media_total_chunks' => (int) ceil($data['size'] / $chunkSize),
            'media_disk' => config(
                "media.collections.{$data['collection']}.disk",
                config('media.default_disk')
            ),
            'media_checksum' => $data['checksum'] ?? null,
            'media_expires_at' => now()->addHours(config('media.expires_hours')),
        ]);
    }

    public function uploadChunk(Media $media, int $chunkNumber, UploadedFile $chunk): Media
    {
        if (! in_array($media->media_status, [Media::STATUS_PENDING, Media::STATUS_UPLOADING], true)) {
            throw new ProcessException('Unggahan media ini sudah tidak dapat menerima bagian berkas baru.');
        }

        if ($media->media_expires_at->isPast()) {
            throw new ProcessException('Sesi unggah media sudah kedaluwarsa.');
        }

        if ($chunkNumber < 1 || $chunkNumber > $media->media_total_chunks) {
            throw new ProcessException('Urutan bagian berkas tidak valid.');
        }

        $expectedSize = min(
            $media->media_chunk_size,
            $media->media_original_size - (($chunkNumber - 1) * $media->media_chunk_size)
        );

        if ($chunk->getSize() !== $expectedSize) {
            throw new ProcessException('Ukuran bagian berkas tidak sesuai dengan sesi unggah.');
        }

        return Cache::lock("media-upload:{$media->media_uuid}", 15)->block(10, function () use (
            $media,
            $chunkNumber,
            $chunk
        ): Media {
            $disk = Storage::disk(config('media.temporary_disk'));
            $path = $this->chunkPath($media, $chunkNumber);
            $stream = fopen($chunk->getRealPath(), 'rb');

            if ($stream === false || ! $disk->put($path, $stream)) {
                throw new ProcessException('Bagian berkas gagal disimpan. Silakan coba kembali.');
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $uploadedChunks = count($disk->files($this->chunkDirectory($media)));
            $media->update([
                'media_uploaded_chunks' => $uploadedChunks,
                'media_status' => Media::STATUS_UPLOADING,
            ]);

            return $media->refresh();
        });
    }

    public function complete(Media $media): Media
    {
        if (! in_array($media->media_status, [Media::STATUS_PENDING, Media::STATUS_UPLOADING], true)) {
            throw new ProcessException('Unggahan media sudah diproses atau dibatalkan.');
        }

        if ($media->media_uploaded_chunks !== $media->media_total_chunks) {
            throw new ProcessException('Semua bagian berkas harus selesai diunggah terlebih dahulu.');
        }

        $media->update([
            'media_status' => Media::STATUS_PROCESSING,
            'media_error_message' => null,
        ]);

        try {
            $this->process($media->getKey());
        } catch (Throwable $exception) {
            $media->update([
                'media_status' => Media::STATUS_FAILED,
                'media_error_message' => 'Media gagal diproses. Silakan unggah kembali.',
            ]);

            report($exception);

            throw new ProcessException('Media gagal diproses. Silakan unggah kembali.');
        }

        return $media->refresh();
    }

    public function abort(Media $media): Media
    {
        if ($media->media_status === Media::STATUS_PROCESSING) {
            throw new ProcessException('Media sedang diproses dan tidak dapat dibatalkan.');
        }

        Storage::disk(config('media.temporary_disk'))->deleteDirectory($this->chunkDirectory($media));

        if ($media->media_path !== null) {
            Storage::disk($media->media_disk)->delete($media->media_path);
        }

        $media->update([
            'media_status' => Media::STATUS_ABORTED,
            'media_path' => null,
        ]);

        return $media->refresh();
    }

    public function process(int $mediaId): void
    {
        $media = Media::query()->findOrFail($mediaId);

        if ($media->media_status !== Media::STATUS_PROCESSING) {
            return;
        }

        $temporaryDisk = Storage::disk(config('media.temporary_disk'));
        $assembledPath = "media/processing/{$media->media_uuid}.upload";
        $assembledAbsolutePath = $temporaryDisk->path($assembledPath);
        $temporaryDisk->makeDirectory('media/processing');
        $output = fopen($assembledAbsolutePath, 'wb');

        if ($output === false) {
            throw new \RuntimeException('Berkas sementara media tidak dapat dibuat.');
        }

        try {
            for ($chunkNumber = 1; $chunkNumber <= $media->media_total_chunks; $chunkNumber++) {
                $input = $temporaryDisk->readStream($this->chunkPath($media, $chunkNumber));

                if ($input === null || $input === false) {
                    throw new \RuntimeException("Bagian berkas ke-{$chunkNumber} tidak ditemukan.");
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        if (filesize($assembledAbsolutePath) !== $media->media_original_size) {
            throw new \RuntimeException('Ukuran media setelah semua bagian digabungkan tidak sesuai.');
        }

        $actualMimeType = mime_content_type($assembledAbsolutePath) ?: 'application/octet-stream';
        $allowedMimeTypes = config(
            "media.collections.{$media->media_collection}.mime_types",
            config('media.mime_types', [])
        );
        $processingMimeType = in_array($actualMimeType, $allowedMimeTypes, true)
            ? $actualMimeType
            : $media->media_original_mime_type;

        if (! in_array($processingMimeType, $allowedMimeTypes, true)) {
            throw new \RuntimeException('Tipe media hasil unggahan tidak diizinkan.');
        }

        $actualChecksum = hash_file('sha256', $assembledAbsolutePath);

        if ($media->media_checksum !== null && ! hash_equals($media->media_checksum, $actualChecksum)) {
            throw new \RuntimeException('Pemeriksaan keutuhan berkas gagal.');
        }

        [$processedPath, $extension] = $this->compress($assembledAbsolutePath, $processingMimeType, $media);
        $finalMimeType = mime_content_type($processedPath) ?: $processingMimeType;
        if (! in_array($finalMimeType, $allowedMimeTypes, true)) {
            $finalMimeType = $processingMimeType;
        }
        $finalPath = now()->format('Y/m')."/{$media->media_uuid}.{$extension}";
        $finalPath = "media/{$this->collectionDirectory($media->media_collection)}/{$finalPath}";
        $stream = fopen($processedPath, 'rb');

        if ($stream === false || ! Storage::disk($media->media_disk)->put($finalPath, $stream)) {
            throw new \RuntimeException('Media akhir gagal disimpan.');
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        $media->update([
            'media_mime_type' => $finalMimeType,
            'media_size' => filesize($processedPath),
            'media_status' => Media::STATUS_READY,
            'media_path' => $finalPath,
            'media_checksum' => hash_file('sha256', $processedPath),
            'media_error_message' => null,
        ]);

        $temporaryDisk->deleteDirectory($this->chunkDirectory($media));
        $temporaryDisk->delete($assembledPath);

        if ($processedPath !== $assembledAbsolutePath && is_file($processedPath)) {
            unlink($processedPath);
        }
    }

    /** @return array{string, string} */
    private function compress(string $source, string $mimeType, Media $media): array
    {
        $fallbackExtension = $this->extensionFor($mimeType);

        if (! config('media.compression_enabled')) {
            return [$source, $fallbackExtension];
        }

        $isImage = str_starts_with($mimeType, 'image/');
        $isVideo = str_starts_with($mimeType, 'video/');

        if (! $isImage && ! $isVideo) {
            return [$source, $fallbackExtension];
        }

        if ($isImage) {
            return $this->compressImageWithGd($source, $mimeType, $media);
        }

        return $this->compressVideoWithFfmpeg($source, $mimeType, $media);
    }

    /** @return array{string, string} */
    private function compressImageWithGd(string $source, string $mimeType, Media $media): array
    {
        $fallbackExtension = $this->extensionFor($mimeType);

        if (! extension_loaded('gd')) {
            return [$source, $fallbackExtension];
        }

        try {
            $image = match ($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($source),
                'image/png' => imagecreatefrompng($source),
                'image/webp' => imagecreatefromwebp($source),
                default => false,
            };

            if ($image === false) {
                return [$source, $fallbackExtension];
            }

            $originalWidth = (int) imagesx($image);
            $originalHeight = (int) imagesy($image);
            $maxWidth = max(1, (int) config('media.image_max_width', 1600));

            if ($originalWidth > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = (int) round($originalHeight * ($maxWidth / $originalWidth));

                // Ensure even dimensions for codec compatibility
                if ($newHeight % 2 !== 0) {
                    $newHeight++;
                }

                $resized = imagecreatetruecolor($newWidth, $newHeight);

                // Preserve transparency for PNG
                imagealphablending($resized, false);
                imagesavealpha($resized, true);

                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
                imagedestroy($image);
                $image = $resized;
            }

            $target = dirname($source).DIRECTORY_SEPARATOR."{$media->media_uuid}.compressed.webp";
            $quality = max(1, min(100, (int) config('media.image_quality', 80)));
            $succeeded = imagewebp($image, $target, $quality);
            imagedestroy($image);

            if ($succeeded && is_file($target) && filesize($target) > 0) {
                return [$target, 'webp'];
            }

            if (is_file($target)) {
                unlink($target);
            }
        } catch (Throwable) {
            // Fall through to original on any unexpected error
        }

        return [$source, $fallbackExtension];
    }

    /** @return array{string, string} */
    private function compressVideoWithFfmpeg(string $source, string $mimeType, Media $media): array
    {
        $fallbackExtension = $this->extensionFor($mimeType);
        $ffmpegBinary = (string) config('media.ffmpeg_binary', 'ffmpeg');

        // Skip gracefully if ffmpeg is not available
        if (! is_executable($ffmpegBinary) && ! shell_exec('which '.escapeshellarg($ffmpegBinary).' 2>/dev/null')) {
            return [$source, $fallbackExtension];
        }

        $maxWidth = max(1, (int) config('media.video_max_width', 1280));
        $scaleFilter = sprintf("scale='min(%d,iw)':trunc(ow/a/2)*2", $maxWidth);
        $target = dirname($source).DIRECTORY_SEPARATOR."{$media->media_uuid}.compressed.mp4";

        $command = [
            $ffmpegBinary,
            '-y', '-i', $source,
            '-vf', $scaleFilter,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', (string) max(0, min(51, (int) config('media.video_crf', 28))),
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $target,
        ];

        $process = new Process($command);
        $process->setTimeout(max(1, (int) config('media.compression_timeout', 120)));
        $process->run();

        if ($process->isSuccessful() && is_file($target) && filesize($target) > 0) {
            return [$target, 'mp4'];
        }

        if (is_file($target)) {
            unlink($target);
        }

        // Graceful fallback to original file
        return [$source, $fallbackExtension];
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }

    private function collectionDirectory(string $collection): string
    {
        $directory = Str::slug($collection, '_');

        return $directory !== '' ? $directory : hash('sha256', $collection);
    }

    private function chunkDirectory(Media $media): string
    {
        return "media/chunks/{$media->media_uuid}";
    }

    private function chunkPath(Media $media, int $chunkNumber): string
    {
        return $this->chunkDirectory($media)."/{$chunkNumber}.part";
    }
}
