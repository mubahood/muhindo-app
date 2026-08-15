<?php

namespace App\Console\Commands;

use App\Models\ProjectTask;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Turns repeating templates into today's copies.
 *
 * The habit this exists for is a short update to every client every Friday.
 * That habit is the actual repair for having gone quiet on five clients at
 * once, and leaving it to memory would be leaving it to the exact faculty that
 * failed. So it becomes a row that appears on the day view by itself.
 *
 * The one property that matters here is idempotency. This is scheduled daily,
 * and it will also be run by hand after a laptop was closed over a weekend, or
 * twice by accident. A generator that produces a second copy on a second run
 * teaches you that your list lies to you, and a list you do not trust is worse
 * than no list at all. Every decision below serves that:
 *
 *   - a copy records which template made it, in repeats_from_id
 *   - existence is checked on (template, date), not on a count
 *   - the check runs before the write, for every template, every time
 */
class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring
        {--date= : Generate for this date instead of today (Y-m-d)}
        {--dry : Report what would be created, write nothing}';

    protected $description = 'Create today\'s copies of repeating tasks';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : Carbon::today();

        $dry = (bool) $this->option('dry');

        $templates = ProjectTask::whereNotNull('repeat_every')->get();

        if ($templates->isEmpty()) {
            $this->info('No repeating tasks are set up.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($templates as $template) {
            if (! $this->fallsOn($template, $date)) {
                continue;
            }

            $exists = ProjectTask::where('repeats_from_id', $template->id)
                ->whereDate('due_date', $date)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            if (! $dry) {
                ProjectTask::create([
                    'project_id' => $template->project_id,
                    'title' => $template->title,
                    'description' => $template->description,
                    'status' => 'todo',
                    'priority' => $template->priority,
                    'due_date' => $date->toDateString(),
                    'assigned_to' => $template->assigned_to,
                    'created_by' => $template->created_by,
                    'repeats_from_id' => $template->id,
                    'sort_order' => $template->sort_order,
                ]);
            }

            $created++;
            $this->line('  '.($dry ? 'would create' : 'created').': '.$template->title);
        }

        $this->info(sprintf(
            '%s %d task(s) for %s; %d already there.',
            $dry ? 'Would create' : 'Created',
            $created,
            $date->toDateString(),
            $skipped
        ));

        return self::SUCCESS;
    }

    /** Does this template's rule land on the given date? */
    private function fallsOn(ProjectTask $template, Carbon $date): bool
    {
        // An expired rule stops producing without anybody deleting it, so a
        // finished habit can be kept as a record of what used to happen.
        if ($template->repeat_until && $date->gt($template->repeat_until)) {
            return false;
        }

        // Nothing is generated before the habit was meant to start.
        if ($template->due_date && $date->lt($template->due_date)) {
            return false;
        }

        return match ($template->repeat_every) {
            'daily' => true,
            'weekdays' => ! $date->isWeekend(),
            // The template's own due_date carries the weekday. Without one
            // there is no way to know which day was meant, and guessing
            // "today" would silently move the habit every time it ran.
            'weekly' => $template->due_date !== null
                && $date->dayOfWeek === $template->due_date->dayOfWeek,
            default => false,
        };
    }
}
