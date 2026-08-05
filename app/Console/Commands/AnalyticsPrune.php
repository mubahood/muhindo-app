<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\PageView;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Console\Command;

/**
 * Keeps the raw tables from growing without limit.
 *
 * The daily rollup is never pruned, so this loses the ability to drill into an
 * old afternoon, not the history of the numbers themselves. Crawler traffic
 * goes early and hard: it is kept only long enough to be audited.
 */
class AnalyticsPrune extends Command
{
    protected $signature = 'analytics:prune {--dry : Report what would go, delete nothing}';

    protected $description = 'Delete raw analytics rows past their retention window';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $viewsBefore = now()->subDays((int) config('analytics.retain_page_views_days', 400));
        $eventsBefore = now()->subDays((int) config('analytics.retain_events_days', 730));
        $botsBefore = now()->subDays(30);

        $plan = [
            'page views' => PageView::where('viewed_at', '<', $viewsBefore),
            'events' => AnalyticsEvent::where('occurred_at', '<', $eventsBefore),
            'visits' => Visit::where('started_at', '<', $viewsBefore),
            'crawlers' => Visitor::where('is_bot', true)->where('last_seen_at', '<', $botsBefore),
        ];

        foreach ($plan as $label => $query) {
            $count = (clone $query)->count();

            if ($count === 0) {
                $this->line("  {$label}: nothing to remove");

                continue;
            }

            if ($dry) {
                $this->line("  {$label}: would remove ".number_format($count));

                continue;
            }

            // Chunked so a large backlog cannot lock the table or exhaust
            // memory on shared hosting.
            $removed = 0;
            do {
                $batch = (clone $query)->limit(2000)->delete();
                $removed += $batch;
            } while ($batch > 0);

            $this->line("  {$label}: removed ".number_format($removed));
        }

        $this->info($dry ? 'Dry run, nothing deleted.' : 'Pruned.');

        return self::SUCCESS;
    }
}
