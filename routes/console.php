<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// §6.4 — nightly at-risk tagging, ahead of the working day.
Schedule::command('app:detect-at-risk-enrollments')->dailyAt('02:00');
