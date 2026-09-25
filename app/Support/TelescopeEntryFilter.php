<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;

class TelescopeEntryFilter
{
    private const BACKGROUND_ENTRY_TYPES = [
        EntryType::COMMAND,
        EntryType::JOB,
        EntryType::SCHEDULED_TASK,
    ];

    /** @param Collection<int, IncomingEntry> $entries */
    public function shouldRecordBatch(Collection $entries): bool
    {
        return $entries->contains(function (IncomingEntry $entry): bool {
            if (in_array($entry->type, self::BACKGROUND_ENTRY_TYPES, true)) {
                return true;
            }

            return $entry->type === EntryType::REQUEST;
        });
    }
}
