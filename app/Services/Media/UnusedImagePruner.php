<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UnusedImagePruner
{
    public function prune(): int
    {
        $deleted = 0;
        $references = $this->availableReferences();

        Media::query()
            ->where('media_expires_at', '<', now())
            ->where(function ($query): void {
                $query->where('media_original_mime_type', 'like', 'image/%')
                    ->orWhere('media_mime_type', 'like', 'image/%');
            })
            ->chunkById(100, function ($images) use (&$deleted, $references): void {
                foreach ($images as $image) {
                    if ($this->isReferenced($image, $references)) {
                        continue;
                    }

                    $this->deleteFiles($image);
                    $image->delete();
                    $deleted++;
                }
            }, 'media_id');

        return $deleted;
    }

    /** @return array<string, list<string>> */
    private function availableReferences(): array
    {
        return collect(config('media.references', []))
            ->filter(fn (array $columns, string $table): bool => Schema::hasTable($table))
            ->map(fn (array $columns, string $table): array => array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($table, $column),
            )))
            ->filter()
            ->all();
    }

    /** @param array<string, list<string>> $references */
    private function isReferenced(Media $media, array $references): bool
    {
        foreach ($references as $table => $columns) {
            $referenced = DB::table($table)
                ->where(function ($query) use ($columns, $media): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', "%{$media->media_uuid}%");
                    }
                })
                ->exists();

            if ($referenced) {
                return true;
            }
        }

        return false;
    }

    private function deleteFiles(Media $media): void
    {
        if ($media->media_path !== null) {
            Storage::disk($media->media_disk)->delete($media->media_path);
        }

        $temporaryDisk = Storage::disk(config('media.temporary_disk'));
        $temporaryDisk->deleteDirectory("media/chunks/{$media->media_uuid}");
        $temporaryDisk->delete([
            "media/processing/{$media->media_uuid}.upload",
            "media/processing/{$media->media_uuid}.compressed.webp",
        ]);
    }
}
