<?php

namespace App\Console\Commands;

use App\Models\AnalyticsDaily;
use App\Services\Analytics\Insights;
use App\Support\Analytics\Events;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Collapses a day of raw traffic into one row.
 *
 * The overview screen asks "how many visitors over 12 months" on every load.
 * Against page_views that question gets slower every week, for an answer that
 * has not changed since midnight. Rebuilt rather than incremented, so a day
 * can be recomputed after a fix without the numbers drifting.
 */
class AnalyticsRollup extends Command
{
    protected $signature = 'analytics:rollup
        {--days=2 : How many days back to rebuild}
        {--all : Rebuild every day since the first recorded visit}';

    protected $description = 'Summarise each day of traffic into the analytics_daily table';

    public function handle(): int
    {
        $today = CarbonImmutable::today();

        // Yesterday is rebuilt by default as well as today, because a visit
        // that starts at 23:58 keeps recording after midnight.
        $from = $this->option('all')
            ? CarbonImmutable::parse(\App\Models\Visit::min('started_at') ?? $today)->startOfDay()
            : $today->subDays(max(0, (int) $this->option('days') - 1));

        $built = 0;

        for ($day = $from; $day->lte($today); $day = $day->addDay()) {
            $insights = Insights::between($day, $day);
            $totals = $insights->totals();
            $events = $insights->eventCounts();

            AnalyticsDaily::updateOrCreate(['date' => $day->toDateString()], [
                'visitors' => $totals['visitors'],
                'new_visitors' => $totals['new_visitors'],
                'visits' => $totals['visits'],
                'page_views' => $totals['page_views'],
                'bounces' => (int) round($totals['bounce_rate'] / 100 * $totals['visits']),
                'engaged_seconds' => $totals['engaged_seconds'],
                'signups' => $events[Events::SIGNUP] ?? 0,
                'enrollments' => $events[Events::ENROLLED] ?? 0,
                'orders' => $events[Events::ORDER_PAID] ?? 0,
                'inquiries' => $events[Events::INQUIRY] ?? 0,
                'revenue' => $insights->revenue(),
                'by_channel' => $insights->byChannel(),
                'by_country' => $insights->byCountry(20),
                'by_device' => $insights->byDevice(),
            ]);

            $built++;
        }

        $this->info("Rolled up {$built} ".str('day')->plural($built).'.');

        return self::SUCCESS;
    }
}
