<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly at-risk tagging, ahead of the working day.
Schedule::command('app:detect-at-risk-enrollments')->dailyAt('02:00');

// Weekly instructor digest, after Monday's nightly at-risk run.
Schedule::command('app:send-weekly-instructor-digest')->weeklyOn(1, '07:00');

// Nightly streak-badge check.
Schedule::command('app:award-streak-badges')->dailyAt('02:30');

// Monthly retention prune; volume at this scale makes a tighter schedule unnecessary.
Schedule::command('app:prune-learning-events')->monthlyOn(1, '03:30');

/*
 * Analytics maintenance.
 *
 * The rollup runs hourly rather than nightly so today's figures are current on
 * a dashboard somebody is looking at now, and it rebuilds yesterday too, which
 * is what closes off a visit that ran past midnight.
 */
Schedule::command('analytics:rollup')->hourly();
Schedule::command('analytics:prune')->weeklyOn(0, '03:45');
Schedule::command('analytics:geolocate')->hourlyAt(20)->when(fn () => (bool) config('analytics.geo.enabled'));

/*
 * Repeating tasks become real ones before the day starts.
 *
 * 05:00 so the day view is already correct at 09:00, and early enough that a
 * missed run can be caught by hand with --date before anybody looks.
 */
Schedule::command('tasks:generate-recurring')->dailyAt('05:00');
