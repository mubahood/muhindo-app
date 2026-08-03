<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every course lets a stranger watch its first lesson.
 *
 * The catalogue shipped with 21 courses and not one previewable lesson, so
 * somebody deciding whether to spend UGX 60,000 could read a curriculum and
 * nothing else. The first lesson is the cheapest thing to give away and the
 * most persuasive: whoever watches it has already learned how this instructor
 * teaches, which is the actual question they are asking.
 */
class CoursePreviewAccessTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithLessons(int $lessons = 3, bool $video = true): Course
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 60000]);

        $module = CourseModule::create([
            'course_id' => $course->id, 'title' => 'Thinking in tables', 'sort_order' => 1,
        ]);

        for ($i = 1; $i <= $lessons; $i++) {
            Lesson::create([
                'course_module_id' => $module->id,
                'title' => "Lesson {$i}",
                'video_url' => $video ? "https://www.youtube-nocookie.com/embed/vid{$i}00000" : null,
                'is_published' => true,
                'sort_order' => $i,
            ]);
        }

        return $course->fresh();
    }

    public function test_the_command_opens_the_first_lesson_of_every_course(): void
    {
        $a = $this->courseWithLessons();
        $b = $this->courseWithLessons();

        $this->artisan('courses:open-first-lessons')->assertSuccessful();

        foreach ([$a, $b] as $course) {
            $lessons = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $course->id))
                ->orderBy('sort_order')->get();

            $this->assertTrue($lessons->first()->fresh()->is_free_preview,
                'the first lesson should be open');
            $this->assertFalse($lessons->last()->fresh()->is_free_preview,
                'only the first lesson should be open');
        }
    }

    public function test_it_skips_a_course_whose_opening_lesson_has_nothing_to_play(): void
    {
        // Advertising a preview that plays nothing is worse than offering none.
        $course = $this->courseWithLessons(video: false);

        $this->artisan('courses:open-first-lessons')->assertSuccessful();

        $this->assertSame(0, Lesson::whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->where('is_free_preview', true)->count());
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->courseWithLessons();

        $this->artisan('courses:open-first-lessons')->expectsOutputToContain('changed 1');
        $this->artisan('courses:open-first-lessons')->expectsOutputToContain('changed 0');
    }

    public function test_a_stranger_can_watch_it_without_an_account(): void
    {
        $course = $this->courseWithLessons();
        $this->artisan('courses:open-first-lessons');

        $lesson = Lesson::where('is_free_preview', true)->firstOrFail();

        $this->assertGuest();
        $this->get(route('courses.preview', [$course, $lesson]))->assertOk()
            ->assertSee($lesson->title);
    }

    public function test_the_course_page_offers_the_free_lesson_before_the_curriculum(): void
    {
        $course = $this->courseWithLessons();
        $this->artisan('courses:open-first-lessons');

        $html = (string) $this->get(route('courses.show', $course))->assertOk()->getContent();

        $this->assertStringContainsString('Watch the first lesson free', $html);
        $this->assertStringContainsString('Lesson 1', $html);

        // Before the module list, not buried inside it. Matched on the markup
        // rather than the class name — the layout's stylesheet mentions
        // .accordion-mod long before the first <details> does.
        $this->assertLessThan(
            strpos($html, '<details class="accordion-mod"'),
            strpos($html, '<button type="button" class="cur-free"')
        );
    }

    public function test_a_module_says_it_holds_the_free_lesson_while_still_closed(): void
    {
        $course = $this->courseWithLessons();
        $this->artisan('courses:open-first-lessons');

        $this->get(route('courses.show', $course))->assertOk()
            ->assertSee('Free lesson');
    }

    public function test_the_accordions_show_that_they_open(): void
    {
        $course = $this->courseWithLessons();

        $html = (string) $this->get(route('courses.show', $course))->assertOk()->getContent();

        // A <summary> with its marker suppressed and nothing put back is a
        // heading that happens to be clickable, which nobody discovers.
        $this->assertStringContainsString('mod-caret', $html);
        $this->assertStringContainsString('fa-chevron-right', $html);
    }

    public function test_a_locked_lesson_is_not_reachable_by_guessing_the_url(): void
    {
        $course = $this->courseWithLessons();
        $this->artisan('courses:open-first-lessons');

        $locked = Lesson::where('is_free_preview', false)->orderBy('sort_order')->firstOrFail();

        $this->get(route('courses.preview', [$course, $locked]))->assertNotFound();
    }

    public function test_a_hard_wrapped_description_reads_as_one_paragraph(): void
    {
        // The import wrapped descriptions at ~85 characters and left a blank
        // line at each wrap, so markdown read one sentence as three paragraphs
        // and printed the author's *emphasis* as literal asterisks.
        $course = Course::factory()->create([
            'description' => "Every real system lives on a database. This course teaches\n\n"
                ."you to *think* in tables first.\n\nA genuinely new paragraph starts here.",
        ]);

        $html = $course->descriptionHtml();

        $this->assertSame(2, substr_count($html, '<p>'), 'the wrap should not become a paragraph');
        $this->assertStringContainsString('<em>think</em>', $html);
        $this->assertStringNotContainsString('*think*', $html);
        $this->assertStringContainsString('teaches you to', strip_tags($html));
    }

    public function test_every_lesson_in_the_real_catalogue_resolves_its_video(): void
    {
        // The whole imported catalogue is written in youtube-nocookie URLs,
        // which the id extractor used to reject — so the IFrame API never
        // loaded and no watch progress was ever recorded.
        $lesson = Lesson::create([
            'course_module_id' => CourseModule::create([
                'course_id' => Course::factory()->create()->id, 'title' => 'M', 'sort_order' => 1,
            ])->id,
            'title' => 'L',
            'video_url' => 'https://www.youtube-nocookie.com/embed/'.Str::random(11),
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->assertNotNull($lesson->youtubeVideoId());
    }
}
