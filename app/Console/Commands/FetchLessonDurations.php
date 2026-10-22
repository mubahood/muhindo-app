<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Services\YoutubeService;
use Illuminate\Console\Command;

/**
 * Fills in the runtime of lessons whose video is on YouTube.
 *
 * The markdown catalogue format records a title and a video link per lesson and
 * never a length, so every course imported from it had 43 or 55 lessons and no
 * hours. The buy box prints "X hours of content" only when it has a total, and
 * a paid course page that cannot say how long it is undersells itself against
 * one that can.
 *
 * Scoped by course on purpose, and slowly. Backfilling the whole catalogue is
 * hundreds of requests to somebody else's servers for courses nobody can buy
 * yet, so the default is the courses that are actually open for enrolment.
 */
class FetchLessonDurations extends Command
{
    protected $signature = 'courses:fetch-durations
        {--course=* : Course numbers to do; defaults to every course open for enrolment}
        {--all : Every published course instead}
        {--refresh : Re-fetch lessons that already have a duration}
        {--dry : Report what would change and write nothing}';

    protected $description = 'Fill in lesson runtimes from YouTube for the open courses';

    public function handle(YoutubeService $youtube): int
    {
        $courses = Course::query()
            ->when($numbers = $this->option('course'), fn ($q) => $q->whereIn('course_number', $numbers))
            ->when(! $numbers && ! $this->option('all'), fn ($q) => $q->sellable())
            ->when(! $numbers && $this->option('all'), fn ($q) => $q->where('is_published', true))
            ->orderBy('course_number')
            ->get();

        if ($courses->isEmpty()) {
            $this->warn('No courses matched.');

            return self::SUCCESS;
        }

        $filled = 0;
        $failed = 0;

        foreach ($courses as $course) {
            $lessons = Lesson::query()
                ->whereIn('course_module_id', $course->modules()->pluck('id'))
                ->where('is_published', true)
                ->whereNotNull('video_url')
                ->when(! $this->option('refresh'), fn ($q) => $q->whereNull('duration_minutes'))
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (Lesson $lesson) => $lesson->youtubeVideoId() !== null);

            $this->info("#{$course->course_number} {$course->title}: {$lessons->count()} lesson(s) to look up.");

            foreach ($lessons as $lesson) {
                $minutes = $youtube->fetchDurationMinutes((string) $lesson->youtubeVideoId());

                if ($minutes === null) {
                    $this->line("  <fg=yellow>?</>  {$lesson->title}");
                    $failed++;

                    continue;
                }

                if (! $this->option('dry')) {
                    $lesson->forceFill(['duration_minutes' => $minutes])->save();
                }

                $this->line("  <fg=green>✓</>  {$minutes} min  {$lesson->title}");
                $filled++;

                // Deliberate. These are somebody else's servers, and a
                // backfill has no deadline worth hammering them over.
                usleep(300_000);
            }
        }

        $verb = $this->option('dry') ? 'would fill' : 'filled';
        $this->newLine();
        $this->info("{$verb} {$filled} lesson duration(s); {$failed} could not be read.");

        return self::SUCCESS;
    }
}
