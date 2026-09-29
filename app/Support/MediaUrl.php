<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MediaUrl
{
    /** @var array<string, string|null> */
    private static array $privateExtensions = [];

    /** @var array<string, Media|null> */
    private static array $mediaByUuid = [];

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $media = self::publicMediaFromUrl($path);
        if ($media !== null) {
            return self::publicContentUrl($media);
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, ['/api/', 'api/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public static function publicContentUrl(Media $media): ?string
    {
        $extension = self::extension($media->media_path);

        if (
            ! self::isPublicMedia($media)
            || $media->media_status !== Media::STATUS_READY
            || $extension === null
        ) {
            return null;
        }

        return URL::route('api.v1.media.public-content', [
            'media' => $media->media_uuid,
            'extension' => $extension,
        ]);
    }

    public static function privateContentUrl(Media $media, string $audience): ?string
    {
        $extension = self::extension($media->media_path);

        if ($media->media_status !== Media::STATUS_READY || $extension === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            "api.v1.{$audience}.media.content",
            now()->addHour(),
            ['media' => $media->media_uuid, 'extension' => $extension],
        );
    }

    public static function canonicalPrivateUrl(?string $url, ?string $audience = null): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        if ($audience !== null && preg_match(
            '#^(?:https?://[^/]+)?/storage/media/.+/(?<uuid>[0-9a-f-]{36})\.(?<extension>[a-z0-9]+)(?:\?.*)?$#i',
            $url,
            $storageMatches,
        )) {
            $media = Media::query()
                ->where('media_uuid', Str::lower($storageMatches['uuid']))
                ->first();

            if ($media !== null) {
                $extension = self::extension($media->media_path);

                return $extension === null
                    ? null
                    : URL::to("/api/v1/{$audience}/media/uploads/{$media->media_uuid}/content.{$extension}");
            }
        }

        if (! preg_match(
            '#^(?:https?://[^/]+)?/api/v1/(?:admin|member)/media/uploads/(?<uuid>[0-9a-f-]{36})/content(?:\.(?<extension>[a-z0-9]+))?(?:\?.*)?$#i',
            $url,
            $matches,
        )) {
            return $url;
        }

        $uuid = Str::lower($matches['uuid']);
        $extension = isset($matches['extension']) && $matches['extension'] !== ''
            ? Str::lower($matches['extension'])
            : (self::$privateExtensions[$uuid]
                ??= self::extension(
                    Media::query()->where('media_uuid', $uuid)->value('media_path')
                ));

        if ($audience !== null) {
            $url = preg_replace(
                '#/api/v1/(?:admin|member)/#i',
                "/api/v1/{$audience}/",
                $url,
                1,
            ) ?? $url;
        }

        if ($extension !== null && ! Str::endsWith(Str::lower($url), ".{$extension}")) {
            $url = "{$url}.{$extension}";
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $url;
        }

        $absoluteUrl = URL::to($path);

        return $absoluteUrl;
    }

    public static function temporaryPrivateUrl(?string $url, string $audience): ?string
    {
        $canonicalUrl = self::canonicalPrivateUrl($url, $audience);
        if ($canonicalUrl === null || $canonicalUrl === '') {
            return $canonicalUrl;
        }

        if (! preg_match(
            '#^(?:https?://[^/]+)?/api/v1/(?:admin|member)/media/uploads/(?<uuid>[0-9a-f-]{36})/content(?:\.[a-z0-9]+)?$#i',
            $canonicalUrl,
            $matches,
        )) {
            return $canonicalUrl;
        }

        $media = Media::query()
            ->where('media_uuid', Str::lower($matches['uuid']))
            ->first();

        return $media === null ? $canonicalUrl : self::privateContentUrl($media, $audience);
    }

    public static function extension(?string $path): ?string
    {
        if ($path === null || $path === '' || ! Str::contains($path, '.')) {
            return null;
        }

        $extension = Str::lower(Str::afterLast($path, '.'));

        return preg_match('/^[a-z0-9]+$/', $extension) === 1 ? $extension : null;
    }

    private static function publicMediaFromUrl(string $url): ?Media
    {
        $matched = preg_match(
            '#(?:^|/)(?:storage/)?media/.+/(?<uuid>[0-9a-f-]{36})\.[a-z0-9]+(?:\?.*)?$#i',
            $url,
            $matches,
        ) === 1 || preg_match(
            '#(?:^|/)api/v1/(?:admin|member)/media/uploads/(?<uuid>[0-9a-f-]{36})/content(?:\.[a-z0-9]+)?(?:\?.*)?$#i',
            $url,
            $matches,
        ) === 1 || preg_match(
            '#(?:^|/)api/v1/media/public/(?<uuid>[0-9a-f-]{36})/content(?:\.[a-z0-9]+)?(?:\?.*)?$#i',
            $url,
            $matches,
        ) === 1;

        if (! $matched) {
            return null;
        }

        $uuid = Str::lower($matches['uuid']);
        $media = self::$mediaByUuid[$uuid]
            ??= Media::query()->where('media_uuid', $uuid)->first();

        return $media !== null && self::isPublicMedia($media) ? $media : null;
    }

    private static function isPublicMedia(Media $media): bool
    {
        return $media->media_disk === 'public'
            || config("media.collections.{$media->media_collection}.disk") === 'public';
    }
}
