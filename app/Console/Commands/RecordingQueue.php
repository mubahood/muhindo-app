<?php

namespace App\Console\Commands;

use App\Enums\LessonStage;
use App\Models\Course;
use App\Services\Catalog\CourseReadiness;
use Illuminate\Console\Command;

/**
 * The production plan: what is written, what is recorded, what is live, and
 * what is live that should not be.
 *
 * It exists as a command and not only as a screen because the work it
 * describes is done in batches, at a desk, between recording sessions, and
 * because the answer for the whole catalogue at once does not fit on the page
 * of any one course.
 */
class RecordingQueue extends Command
{
    protected $signature = 'courses:recording
                            {--course= : one course number, for the topic-by-topic list}
                            {--publish-ready : publish every topic that has a video and is still a draft}';

    protected $description = 'What is left to record, and what is recorded and waiting to go live';

    public function handle(): int
    {
        $courses = Course::query()
            ->when($this->option('course'), fn ($q, $n) => $q->where('course_number', $n))
            ->orderBy('course_number')
            ->get();

        if ($courses->isEmpty()) {
            $this->error('No course matched.');

            return self::FAILURE;
        }

        if ($this->option('publish-ready')) {
            return $this->publish($courses);
        }

        return $this->option('course') ? $this->detail($courses->first()) : $this->summary($courses);
    }

    /** @param  \Illuminate\Support\Collection<int,Course>  $courses */
    private function summary($courses): int
    {
        $rows = [];
        $totals = ['planned' => 0, 'ready' => 0, 'live' => 0, 'unrecorded' => 0, 'total' => 0, 'recorded' => 0];

        foreach ($courses as $course) {
            $r = CourseReadiness::forCourse($course);
            if ($r['total'] === 0) {
                continue;
            }

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $r[$key];
            }

            $rows[] = [
                str_pad((string) $course->course_number, 2, '0', STR_PAD_LEFT),
                mb_strimwidth($course->title, 0, 40, '…'),
                $course->is_published ? 'live' : 'draft',
                $r['total'],
                $r['live'] ?: '',
                $r['ready'] ?: '',
                $r['planned'] ?: '',
                // The only column worth colouring: it is a fault, not a stage.
                $r['unrecorded'] ? "<fg=red>{$r['unrecorded']}</>" : '',
                $r['percent'].'%',
            ];
        }

        $this->newLine();
        $this->table(
            ['#', 'Course', 'Page', 'Topics', 'Live', 'Ready', 'To record', 'No video', 'Done'],
            $rows,
        );

        $percent = $totals['total'] > 0 ? (int) round($totals['recorded'] / $totals['total'] * 100) : 0;
        $this->line("  <options=bold>{$totals['recorded']} of {$totals['total']} topics recorded across the catalogue ({$percent}%)</>");

        if ($totals['ready'] > 0) {
            $this->line("  {$totals['ready']} recorded and still a draft. Publish them with <options=bold>--publish-ready</>.");
        }

        if ($totals['unrecorded'] > 0) {
            $this->newLine();
            $this->line("  <fg=red;options=bold>{$totals['unrecorded']} published topics have no video.</>");
            $this->line('  A student can open one and there is nothing to watch. Unpublishing them');
            $this->line('  moves the progress bar of anybody already enrolled, so it stays your');
            $this->line('  decision: <options=bold>php artisan courses:recording --course=NN</> lists them by name.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function detail(Course $course): int
    {
        $r = CourseReadiness::forCourse($course);

        $this->newLine();
        $this->line("  <options=bold>{$course->title}</>");
        $this->line("  {$r['recorded']} of {$r['total']} topics recorded · {$r['percent']}%");

        foreach (LessonStage::byUrgency() as $stage) {
            $lessons = CourseReadiness::inStage($course, $stage);
            if ($lessons->isEmpty()) {
                continue;
            }

            $colour = $stage === LessonStage::Unrecorded ? 'red' : 'default';
            $this->newLine();
            $this->line("  <fg={$colour};options=bold>{$stage->label()} ({$lessons->count()})</>");
            $this->line("  <fg=gray>{$stage->nextStep()}</>");

            // Grouped by stage rather than by module, so sort_order alone
            // would print "1." eleven times. The module is what makes a topic
            // findable in the curriculum tree.
            foreach ($lessons as $lesson) {
                $this->line(sprintf(
                    '    %-52s <fg=gray>%s</>',
                    mb_strimwidth($lesson->title, 0, 52, '…'),
                    mb_strimwidth($lesson->module->title, 0, 34, '…'),
                ));
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /** @param  \Illuminate\Support\Collection<int,Course>  $courses */
    private function publish($courses): int
    {
        $published = 0;
        $skipped = 0;

        foreach ($courses as $course) {
            $result = CourseReadiness::publishReady($course);
            $skipped += $result['skipped'];

            if ($result['published'] === 0) {
                continue;
            }

            $published += $result['published'];
            $this->line("  <fg=green>✓</> {$result['published']} topics in <options=bold>{$course->title}</>");
        }

        $this->newLine();
        $this->line($published > 0
            ? "  <options=bold>{$published} topics are now live.</>"
            : '  <options=bold>Nothing to publish.</> No draft topic has a video yet.');

        if ($skipped > 0) {
            $this->line("  {$skipped} left as drafts, because they have no video.");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
