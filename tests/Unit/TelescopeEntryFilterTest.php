<?php

namespace Tests\Unit;

use App\Support\TelescopeEntryFilter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use PHPUnit\Framework\TestCase;

class TelescopeEntryFilterTest extends TestCase
{
    public function test_all_http_request_batches_are_recorded(): void
    {
        $filter = new TelescopeEntryFilter;

        foreach (['GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertTrue($filter->shouldRecordBatch(collect([
                $this->requestEntry($method),
                (new IncomingEntry(['sql' => 'select 1']))->type(EntryType::QUERY),
            ])));
        }

        $this->assertFalse($filter->shouldRecordBatch(collect([
            (new IncomingEntry(['sql' => 'select 1']))->type(EntryType::QUERY),
        ])));
    }

    public function test_command_job_and_scheduled_task_batches_are_recorded(): void
    {
        $filter = new TelescopeEntryFilter;

        foreach ([EntryType::COMMAND, EntryType::JOB, EntryType::SCHEDULED_TASK] as $entryType) {
            $this->assertTrue($filter->shouldRecordBatch(collect([
                (new IncomingEntry([]))->type($entryType),
            ])));
        }
    }

    private function requestEntry(string $method): IncomingEntry
    {
        return (new IncomingEntry(['method' => $method]))->type(EntryType::REQUEST);
    }
}
