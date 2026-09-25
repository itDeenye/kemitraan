<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune --hours=48')->daily();
Schedule::command('rewards:close-month')
    ->monthlyOn(1, '00:05')
    ->withoutOverlapping();
Schedule::command('members:apply-upgrade-downgrade')
    ->dailyAt('00:20')
    ->withoutOverlapping();
