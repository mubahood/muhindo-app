<?php

namespace Tests\Feature\Performance;

use App\Enums\CourseProgression;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A query budget for the pages students actually sit on.
 *
 * An N+1 is invisible in every other kind of test: the page renders, the
 * content is right, the assertions pass, and the only symptom is that the site
 * gets slower as courses get bigger. Two were live when this was written. The
 * course page ran one query per module because the relation was eager loaded
 * one line after it was first read, and every page in the site asked the
 * database whether the settings table existed, once per setting, because the
 * check sat outside the cache.
 *
 * The budgets are deliberately loose. They are not a target to optimise
 * towards; they are a tripwire that fires when a page starts querying in a
 * loop. A change that adds two queries should pass. A change that adds one per
 * module should not.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** Twelve modules, eighty four lessons: the shape of the real Bootstrap course. */
    private function bigCourse(): Course
    {
        $course = Course::factory()->create([
            'is_published' => true,
            'is_coming_soon' => false,
            'price' => 0,
            'progression' => CourseProgression::Free,
        ]);

        foreach (range(1, 12) as $m) {
            $module = CourseModule::create([
                'course_id' => $course->id,
                'title' => "Module {$m}",
                'sort_order' => $m,
            ]);

            foreach (range(1, 7) as $l) {
                Lesson::create([
                    'course_module_id' => $module->id,
                    'title' => "Lesson {$m}.{$l}",
                    'sort_order' => $l,
                    'is_published' => true,
                    'video_url' => 'https://www.youtube-nocookie.com/embed/abc12345678',
                    'content' => 'The body of the lesson.',
                ]);
            }
        }

        return $course;
    }

    /** @return int the number of queries the callback ran */
    private function countQueries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $run();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Reports the repeated statement when a budget is blown, because "41
     * queries, expected 25" tells you nothing about which line to look at.
     */
    private function assertWithinBudget(int $budget, string $page, callable $run): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $run();

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        if (count($log) <= $budget) {
            $this->addToAssertionCount(1);

            return;
        }

        $shapes = [];
        foreach ($log as $row) {
            $shape = preg_replace(['/\d+/', '/\s+/'], ['?', ' '], $row['query']);
            $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
        }
        arsort($shapes);
        $worst = (string) array_key_first($shapes);

        $this->fail(sprintf(
            "%s ran %d queries, budget is %d.\nMost repeated (x%d):\n  %s",
            $page, count($log), $budget, $shapes[$worst], mb_substr($worst, 0, 160),
        ));
    }

    public function test_the_catalogue_does_not_query_per_course(): void
    {
        foreach (range(1, 14) as $i) {
            Course::factory()->create(['is_published' => true, 'is_coming_soon' => false]);
        }

        $this->assertWithinBudget(25, 'The catalogue', fn () => $this->get('/e-learning')->assertOk());
    }

    /**
     * The one that was broken. Twelve modules meant twelve extra queries,
     * because the preview list read the relation before it was loaded.
     */
    public function test_the_course_page_does_not_query_per_module(): void
    {
        $course = $this->bigCourse();

        $this->assertWithinBudget(28, 'The course page', fn () => $this->get(route('courses.show', $course))->assertOk());
    }

    public function test_the_lesson_page_does_not_query_per_lesson_in_the_sidebar(): void
    {
        $course = $this->bigCourse();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $student->syncSpatieRole();

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        $lesson = Lesson::first();
        LessonProgress::create([
            'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'user_id' => $student->id,
        ]);

        // 84 lessons in the sidebar. A budget of 50 still catches one query per
        // lesson, which is what this is here to catch.
        $this->assertWithinBudget(50, 'The lesson page',
            fn () => $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertOk());
    }

    public function test_the_home_page_stays_cheap(): void
    {
        $this->assertWithinBudget(25, 'The home page', fn () => $this->get('/')->assertOk());
    }

    /**
     * Settings are read several times on every page: the site name, the
     * tagline, the contact details. Each read used to cost a schema query
     * before it reached the cache, and on MySQL that is an information_schema
     * lookup, which is among the slowest things it does.
     */
    public function test_reading_settings_many_times_costs_one_lookup(): void
    {
        \App\Models\Setting::create(['key' => 'site.name', 'group' => 'general', 'type' => 'string', 'value' => json_encode('Test')]);
        \App\Support\Settings::flush();
        \Illuminate\Support\Facades\Cache::flush();

        $first = $this->countQueries(fn () => \App\Support\Settings::get('site.name'));
        $later = $this->countQueries(function () {
            for ($i = 0; $i < 20; $i++) {
                \App\Support\Settings::get('site.name');
            }
        });

        $this->assertLessThanOrEqual(2, $first, 'a cold read should cost one schema check and one select');
        $this->assertSame(0, $later, '20 further reads should cost nothing; the check used to run on each');
    }
}
