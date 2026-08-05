<?php

namespace App\Console\Commands;

use App\Models\LearningEvent;
use Illuminate\Console\Command;

/**
 * The retention half of the event stream: raw learning_events rows are pruned after
 * ~12 months, while the aggregates they fed (enrollments.progress_percent/total_watch_seconds,
 * lesson_progress, at-risk tags, badges) live forever on their own rows and are entirely
 * unaffected. This command only ever touches the append-only log itself.
 */
class PruneLearningEvents extends Command
{
    protected $signature = 'app:prune-learning-events';

    protected $description = 'Delete learning_events rows older than 12 months, aggregate columns elsewhere are unaffected';

    public function handle(): int
    {
        $cutoff = now()->subMonths(12);
        $deleted = LearningEvent::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} learning event(s) created before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
