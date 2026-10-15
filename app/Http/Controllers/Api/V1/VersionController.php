<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $backendVersion = config('api.version.backend');
        $frontendVersion = config('api.version.frontend');
        $backendReleasedAt = $this->releaseDate(config('api.released_at.backend'));
        $frontendReleasedAt = $this->releaseDate(config('api.released_at.frontend'));

        return response()->json([
            'success' => true,
            'message' => 'Versi aplikasi berhasil diambil.',
            'data' => [
                'backend' => filled($backendVersion) ? (string) $backendVersion : 'v1.0.0',
                'frontend' => filled($frontendVersion) ? (string) $frontendVersion : 'v1.0.0',
                'released_at' => [
                    'backend' => $backendReleasedAt['date'],
                    'frontend' => $frontendReleasedAt['date'],
                ],
                'released_at_datetime' => [
                    'backend' => $backendReleasedAt['datetime'],
                    'frontend' => $frontendReleasedAt['datetime'],
                ],
            ],
        ])->header('Cache-Control', 'no-store, max-age=0');
    }

    /** @return array{date: ?string, datetime: ?string} */
    private function releaseDate(?string $value): array
    {
        $unknown = ['date' => null, 'datetime' => null];

        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))?$/', $value)) {
            return $unknown;
        }

        $timezone = new DateTimeZone('Asia/Jakarta');

        try {
            $date = new DateTimeImmutable($value, $timezone);
        } catch (Exception) {
            return $unknown;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return $unknown;
        }

        $date = $date->setTimezone($timezone);

        return [
            'date' => $date->format('Y-m-d'),
            'datetime' => str_contains($value, 'T') ? $date->format(DATE_ATOM) : null,
        ];
    }
}
