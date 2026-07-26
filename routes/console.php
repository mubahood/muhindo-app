<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// §6.4 — nightly at-risk tagging, ahead of the working day.
Schedule::command('app:detect-at-risk-enrollments')->dailyAt('02:00');

// §6.4 — weekly instructor digest, after Monday's nightly at-risk run.
Schedule::command('app:send-weekly-instructor-digest')->weeklyOn(1, '07:00');
