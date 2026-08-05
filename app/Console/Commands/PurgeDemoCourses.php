<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes seeded demo courses and everything hanging off them, to make room for
 * the real catalogue.
 *
 * Two deliberate limits, both decided with the owner:
 *
 * 1. **Billing is never touched.** Invoices and payments are a financial record
 *    that outlives the thing they were for. This database holds ten invoices
 *    with over a million shillings outstanding. Access is course data; money is
 *    not. Invoice line items keep their stored description, so a settled or
 *    outstanding invoice still reads correctly after its course is gone.
 *
 * 2. **It names what it deletes before deleting it.** The brief that asked for
 *    this described the demo content as faker/lorem-ipsum. It is not. The
 *    seeded courses are hand-authored and carry real enrolments, progress and
 *    an issued certificate. Anything that destructive states its case first and
 *    refuses to guess.
 */
class PurgeDemoCourses extends Command
{
    protected $signature = 'courses:purge-demo
        {--dry-run : Show exactly what would go, change nothing}
        {--force : Required in production, and to skip the confirmation}
        {--id=* : Purge only these course ids (default: every course)}';

    protected $description = 'Delete demo courses and their dependent rows, leaving billing intact';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $ids = $this->option('id') ?: Course::pluck('id')->all();
        $courses = Course::whereIn('id', $ids)->withCount('modules')->get();

        if ($courses->isEmpty()) {
            $this->info('Nothing to purge, no courses matched.');

            return self::SUCCESS;
        }

        $counts = $this->countDependents($courses->pluck('id')->all());

        $this->table(['What', 'Rows'], collect($counts)->map(
            fn ($n, $what) => [$what, $n]
        )->values()->all());

        $this->newLine();
        $this->line('<comment>Untouched:</comment> invoices, payments, users, and every other table.');

        if ($this->option('dry-run')) {
            $this->info('Dry run. Nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all of the above? This cannot be undone.', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->purge($courses->pluck('id')->all());

        $this->info('Purged '.$courses->count().' course(s). Billing left intact.');

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<string,int>
     */
    private function countDependents(array $courseIds): array
    {
        [$moduleIds, $lessonIds, $enrollmentIds, $quizIds, $assignmentIds] = $this->graph($courseIds);

        return [
            'courses' => count($courseIds),
            'modules' => count($moduleIds),
            'lessons' => count($lessonIds),
            'lesson materials' => DB::table('lesson_materials')->whereIn('lesson_id', $lessonIds)->count(),
            'enrollments' => count($enrollmentIds),
            'lesson progress' => DB::table('lesson_progress')->whereIn('enrollment_id', $enrollmentIds)->count(),
            'learning events' => DB::table('learning_events')->whereIn('enrollment_id', $enrollmentIds)->count(),
            'lesson notes' => DB::table('lesson_notes')->whereIn('enrollment_id', $enrollmentIds)->count(),
            'enrollment notes' => DB::table('enrollment_notes')->whereIn('enrollment_id', $enrollmentIds)->count(),
            'certificates' => DB::table('certificates')->whereIn('enrollment_id', $enrollmentIds)->count(),
            'quizzes' => count($quizIds),
            'questions' => DB::table('questions')->whereIn('quiz_id', $quizIds)->count(),
            'quiz attempts' => DB::table('quiz_attempts')->whereIn('quiz_id', $quizIds)->count(),
            'assignments' => count($assignmentIds),
            'assignment submissions' => DB::table('assignment_submissions')->whereIn('assignment_id', $assignmentIds)->count(),
            'course reviews' => DB::table('course_reviews')->whereIn('course_id', $courseIds)->count(),
            'discussions' => DB::table('discussions')->whereIn('course_id', $courseIds)->count(),
            'announcements' => DB::table('announcements')->whereIn('course_id', $courseIds)->count(),
            'coupons' => DB::table('coupons')->whereIn('course_id', $courseIds)->count(),
        ];
    }

    /** @return array{0:list<int>,1:list<int>,2:list<int>,3:list<int>,4:list<int>} */
    private function graph(array $courseIds): array
    {
        $moduleIds = DB::table('course_modules')->whereIn('course_id', $courseIds)->pluck('id')->all();
        $lessonIds = DB::table('lessons')->whereIn('course_module_id', $moduleIds)->pluck('id')->all();
        $enrollmentIds = DB::table('enrollments')->whereIn('course_id', $courseIds)->pluck('id')->all();
        $quizIds = DB::table('quizzes')->whereIn('course_id', $courseIds)->pluck('id')->all();
        $assignmentIds = DB::table('assignments')->whereIn('course_id', $courseIds)->pluck('id')->all();

        return [$moduleIds, $lessonIds, $enrollmentIds, $quizIds, $assignmentIds];
    }

    /**
     * One transaction. A half-purged catalogue, lessons gone, enrolments
     * pointing at nothing, is worse than either outcome.
     *
     * @param  list<int>  $courseIds
     */
    private function purge(array $courseIds): void
    {
        DB::transaction(function () use ($courseIds) {
            [$moduleIds, $lessonIds, $enrollmentIds, $quizIds, $assignmentIds] = $this->graph($courseIds);

            // Leaves first, so nothing is ever orphaned mid-transaction.
            DB::table('assignment_submissions')->whereIn('assignment_id', $assignmentIds)->delete();
            DB::table('quiz_attempts')->whereIn('quiz_id', $quizIds)->delete();
            DB::table('questions')->whereIn('quiz_id', $quizIds)->delete();

            DB::table('certificates')->whereIn('enrollment_id', $enrollmentIds)->delete();
            DB::table('lesson_progress')->whereIn('enrollment_id', $enrollmentIds)->delete();
            DB::table('learning_events')->whereIn('enrollment_id', $enrollmentIds)->delete();
            DB::table('lesson_notes')->whereIn('enrollment_id', $enrollmentIds)->delete();
            DB::table('enrollment_notes')->whereIn('enrollment_id', $enrollmentIds)->delete();
            DB::table('course_reviews')->whereIn('enrollment_id', $enrollmentIds)->delete();

            DB::table('discussions')->whereIn('course_id', $courseIds)->delete();
            DB::table('announcements')->whereIn('course_id', $courseIds)->delete();
            DB::table('course_reviews')->whereIn('course_id', $courseIds)->delete();
            DB::table('coupons')->whereIn('course_id', $courseIds)->delete();

            // last_lesson_id points into the lessons about to go.
            DB::table('enrollments')->whereIn('course_id', $courseIds)->update(['last_lesson_id' => null]);
            DB::table('enrollments')->whereIn('course_id', $courseIds)->delete();

            DB::table('assignments')->whereIn('course_id', $courseIds)->delete();
            DB::table('quizzes')->whereIn('course_id', $courseIds)->delete();
            DB::table('lesson_materials')->whereIn('lesson_id', $lessonIds)->delete();
            DB::table('lessons')->whereIn('course_module_id', $moduleIds)->delete();
            DB::table('course_modules')->whereIn('course_id', $courseIds)->delete();
            DB::table('courses')->whereIn('id', $courseIds)->delete();
        });
    }
}
