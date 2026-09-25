<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Laravel\Telescope\IncomingEntry;

class TelescopeRequestTags
{
    /** @return list<string> */
    public function for(IncomingEntry $entry): array
    {
        if (! $entry->isRequest()) {
            return [];
        }

        $date = CarbonImmutable::instance($entry->recordedAt)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->toDateString();
        $endpoint = Str::before((string) ($entry->content['uri'] ?? '/'), '?');
        $tags = ["date:{$date}"];
        $endpointPrefix = '';

        foreach (Str::of($endpoint)->trim('/')->explode('/') as $segment) {
            if ($segment === '') {
                continue;
            }

            $endpointPrefix .= "/{$segment}";
            $tags[] = "endpoint:{$endpointPrefix}";
            $tags[] = "date-endpoint:{$date}:{$endpointPrefix}";
        }

        if ($endpointPrefix === '') {
            $tags[] = 'endpoint:/';
            $tags[] = "date-endpoint:{$date}:/";
        }

        return array_values(array_filter(
            $tags,
            fn (string $tag): bool => Str::length($tag) <= 255,
        ));
    }
}
