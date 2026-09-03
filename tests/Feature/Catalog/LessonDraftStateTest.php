<?php

namespace Tests\Feature\Catalog;

use App\Enums\LessonStage;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Catalog\CourseImporter;
use App\Services\Catalog\CourseReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft state for topics, and the loop that takes one from written to live.
 *
 * The failure this guards against is not cosmetic. A topic that goes live
 * without its video is a blank page for somebody who paid, and until this was
 * fixed every single import republished every draft in the catalogue.
 */
class LessonDraftStateTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = 'https://youtu.be/dQw4w9WgXcQ';

    private function lesson(array $attributes = []): Lesson
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module one', 'sort_order' => 1]);

        return Lesson::create($attributes + [
            'course_module_id' => $module->id,
            'title' => 'A topic',
            'sort_order' => 1,
        ]);
    }

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    /* The four stages ----------------------------------------------------- */

    public function test_a_draft_with_no_video_is_waiting_to_be_recorded(): void
    {
        $lesson = $this->lesson(['is_published' => false]);

        $this->assertSame(LessonStage::Planned, $lesson->stage());
        $this->assertFalse($lesson->isReadyToPublish());
    }

    public function test_a_draft_with_a_video_is_ready_to_publish(): void
    {
        $lesson = $this->lesson(['is_published' => false, 'video_url' => self::VIDEO]);

        $this->assertSame(LessonStage::Ready, $lesson->stage());
        $this->assertTrue($lesson->isReadyToPublish());
    }

    public function test_a_published_topic_with_a_video_is_live(): void
    {
        $lesson = $this->lesson(['is_published' => true, 'video_url' => self::VIDEO]);

        $this->assertSame(LessonStage::Live, $lesson->stage());
    }

    public function test_a_published_topic_with_no_video_is_flagged(): void
    {
        $lesson = $this->lesson(['is_published' => true]);

        $this->assertSame(LessonStage::Unrecorded, $lesson->stage());
        $this->assertTrue($lesson->stage()->isVisibleToStudents(), 'that is exactly why it matters');
        $this->assertFalse($lesson->stage()->canBePublishedInBulk());
    }

    /**
     * The reason a one-line description does not count as content.
     *
     * The import writes the course file's lesson description into `content`,
     * so every unrecorded topic in the catalogue carries a sentence. Reading
     * that as "has something to show" marked 140 unrecorded topics finished.
     */
    public function test_a_one_line_description_does_not_make_a_topic_recorded(): void
    {
        $lesson = $this->lesson([
            'is_published' => true,
            'content' => 'How a spreadsheet is laid out, and how to move around a large one.',
        ]);

        $this->assertSame(LessonStage::Unrecorded, $lesson->stage());
        $this->assertFalse($lesson->isBlank(), 'it has text, it just has no recording');
    }

    public function test_a_topic_with_neither_video_nor_text_is_blank(): void
    {
        $this->assertTrue($this->lesson(['content' => '   '])->isBlank());
        $this->assertFalse($this->lesson(['video_url' => self::VIDEO])->isBlank());
    }

    /** A self-hosted upload is a recording too. */
    public function test_an_uploaded_file_counts_as_a_video(): void
    {
        $lesson = $this->lesson(['is_published' => false, 'video_disk_path' => 'lesson-videos/x.mp4']);

        $this->assertSame(LessonStage::Ready, $lesson->stage());
    }

    /** Derived, so clearing the video puts the topic back where it belongs. */
    public function test_removing_the_video_moves_the_stage_back_on_its_own(): void
    {
        $lesson = $this->lesson(['is_published' => false, 'video_url' => self::VIDEO]);
        $this->assertSame(LessonStage::Ready, $lesson->stage());

        $lesson->detachVideo();

        $this->assertSame(LessonStage::Planned, $lesson->stage());
    }

    /* Attaching a recording ------------------------------------------------ */

    public function test_a_pasted_link_becomes_the_privacy_preserving_embed(): void
    {
        $lesson = $this->lesson();
        $lesson->attachVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        // Not decoration: extractYoutubeId() and the whole imported catalogue
        // are written in nocookie, and a plain youtube.com embed leaves the
        // IFrame API unloaded, so no watch progress is ever recorded.
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->video_url);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $lesson->resource_url);
    }

    public function test_every_shape_of_youtube_link_is_accepted(): void
    {
        foreach ([
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?list=PL1&v=dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        ] as $url) {
            $lesson = $this->lesson();
            $lesson->attachVideo($url);
            $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->video_url, $url);
        }
    }

    public function test_a_link_that_is_not_youtube_is_kept_as_pasted(): void
    {
        $lesson = $this->lesson();
        $lesson->attachVideo('https://vimeo.com/123456789');

        $this->assertSame('https://vimeo.com/123456789', $lesson->video_url);
        $this->assertNull($lesson->resource_url, 'there is no YouTube watch page to send anybody to');
    }

    /* The import ----------------------------------------------------------- */

    /**
     * The bug this whole feature is built around.
     *
     * `upsertLesson()` ended with a flat `is_published => true`, so any import
     * at all republished every topic anybody had deliberately taken down.
     */
    public function test_an_import_never_republishes_a_topic_the_owner_took_down(): void
    {
        $file = $this->courseFile("1. **Opening the editor** — where to start.\n   ▶ https://youtu.be/dQw4w9WgXcQ");

        $importer = app(CourseImporter::class);
        $parser = app(\App\Services\Catalog\CourseFileParser::class);

        $importer->import($parser->parse($file));
        $lesson = Lesson::sole();
        $this->assertTrue($lesson->is_published, 'it shipped with a video, so it arrives live');

        $lesson->unpublish();

        $importer->import($parser->parse($file));

        $this->assertFalse($lesson->fresh()->is_published);
    }

    public function test_an_import_never_unpublishes_one_the_owner_put_up(): void
    {
        $file = $this->courseFile('1. **A written topic** — no video on this one.');
        $parser = app(\App\Services\Catalog\CourseFileParser::class);

        app(CourseImporter::class)->import($parser->parse($file));
        $lesson = Lesson::sole();
        $this->assertFalse($lesson->is_published, 'no video in the file, so it arrives as a draft');

        $lesson->publish();
        app(CourseImporter::class)->import($parser->parse($file));

        $this->assertTrue($lesson->fresh()->is_published);
    }

    /**
     * What lets a 55-topic course with nothing recorded import safely, while
     * the 21 courses that do have their videos import exactly as before.
     */
    public function test_a_new_topic_arrives_live_with_a_video_and_draft_without(): void
    {
        $file = $this->courseFile(
            "1. **Recorded already** — this one has a video.\n"
            ."   ▶ https://youtu.be/dQw4w9WgXcQ\n"
            .'2. **Not yet recorded** — this one does not.'
        );

        app(CourseImporter::class)->import(app(\App\Services\Catalog\CourseFileParser::class)->parse($file));

        $this->assertTrue(Lesson::where('title', 'Recorded already')->sole()->is_published);
        $this->assertFalse(Lesson::where('title', 'Not yet recorded')->sole()->is_published);
    }

    /**
     * Restructuring a course used to leave every lesson of its old shape
     * behind: the module soft-deleted, its lessons did not, and no student
     * query returns them while every direct Lesson query counts them.
     */
    public function test_restructuring_a_course_takes_the_old_lessons_with_it(): void
    {
        $parser = app(\App\Services\Catalog\CourseFileParser::class);

        app(CourseImporter::class)->import($parser->parse(
            $this->courseFile('1. **An early topic** — from the first draft.', 'The old module name')
        ));
        $this->assertSame(1, Lesson::count());

        app(CourseImporter::class)->import($parser->parse(
            $this->courseFile('1. **A later topic** — from the rewrite.', 'A completely new module name')
        ));

        $this->assertSame(1, Lesson::count(), 'the old lesson is gone, not orphaned');
        $this->assertSame('A later topic', Lesson::sole()->title);
    }

    public function test_deleting_a_module_deletes_its_lessons(): void
    {
        $lesson = $this->lesson();
        $module = $lesson->module;

        $module->delete();

        $this->assertSame(0, Lesson::count());
        $this->assertSame(1, Lesson::withTrashed()->count(), 'soft-deleted, so it can come back');
    }

    /** Restoring a module must not resurrect a lesson deleted before it. */
    public function test_restoring_a_module_brings_back_only_what_went_down_with_it(): void
    {
        $lesson = $this->lesson();
        $module = $lesson->module;
        $kept = Lesson::create(['course_module_id' => $module->id, 'title' => 'Also here', 'sort_order' => 2]);

        $lesson->delete();
        $this->travel(1)->seconds();
        $module->delete();
        $module->restore();

        // Not fresh(): it queries without scopes, so it returns soft-deleted
        // rows too and would pass whatever happened here.
        $this->assertNull(Lesson::find($lesson->id), 'deleted on its own beforehand, and stays deleted');
        $this->assertNotNull(Lesson::find($kept->id), 'went down with the module, so it comes back');
    }

    /* Counting ------------------------------------------------------------- */

    public function test_the_tally_counts_every_stage_and_ignores_unrecorded_as_progress(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);

        $make = fn (string $title, bool $published, bool $video) => Lesson::create([
            'course_module_id' => $module->id, 'title' => $title, 'sort_order' => 1,
            'is_published' => $published, 'video_url' => $video ? self::VIDEO : null,
        ]);

        $make('live', true, true);
        $make('ready', false, true);
        $make('planned', false, false);
        $make('unrecorded', true, false);

        $tally = CourseReadiness::forCourse($course);

        $this->assertSame(1, $tally['live']);
        $this->assertSame(1, $tally['ready']);
        $this->assertSame(1, $tally['planned']);
        $this->assertSame(1, $tally['unrecorded']);
        $this->assertSame(4, $tally['total']);
        // A published topic with no video is not progress toward finishing.
        $this->assertSame(2, $tally['recorded']);
        $this->assertSame(50, $tally['percent']);
    }

    public function test_a_course_with_no_topics_reports_zero_rather_than_dividing_by_it(): void
    {
        $tally = CourseReadiness::forCourse(Course::factory()->create());

        $this->assertSame(0, $tally['total']);
        $this->assertSame(0, $tally['percent']);
    }

    /** The tally must see drafts, which is the whole point of it. */
    public function test_the_tally_counts_drafts_that_the_student_facing_relation_hides(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Draft', 'sort_order' => 1, 'is_published' => false]);

        $this->assertSame(0, $course->lessonCount(), 'students see nothing');
        $this->assertSame(1, CourseReadiness::forCourse($course)['total'], 'the author sees it');
    }

    /* Bulk publishing ------------------------------------------------------ */

    public function test_bulk_publish_moves_only_the_topics_that_have_a_video(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);

        $ready = Lesson::create(['course_module_id' => $module->id, 'title' => 'Recorded', 'sort_order' => 1,
            'is_published' => false, 'video_url' => self::VIDEO]);
        $planned = Lesson::create(['course_module_id' => $module->id, 'title' => 'Not recorded', 'sort_order' => 2,
            'is_published' => false]);

        $result = CourseReadiness::publishReady($course);

        $this->assertSame(['published' => 1, 'skipped' => 1], $result);
        $this->assertTrue($ready->fresh()->is_published);
        // The one thing this button must never do.
        $this->assertFalse($planned->fresh()->is_published);
    }

    public function test_the_admin_button_publishes_the_recorded_topics(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Recorded', 'sort_order' => 1,
            'is_published' => false, 'video_url' => self::VIDEO]);

        $this->actingAs($this->admin())
            ->post(route('admin.courses.publish-ready', $course))
            ->assertRedirect(route('admin.courses.show', $course))
            ->assertSessionHas('success');

        $this->assertTrue($lesson->fresh()->is_published);
    }

    public function test_publishing_ready_topics_in_one_module_leaves_the_others_alone(): void
    {
        $course = Course::factory()->create();
        $one = CourseModule::create(['course_id' => $course->id, 'title' => 'One', 'sort_order' => 1]);
        $two = CourseModule::create(['course_id' => $course->id, 'title' => 'Two', 'sort_order' => 2]);

        $mine = Lesson::create(['course_module_id' => $one->id, 'title' => 'Mine', 'sort_order' => 1,
            'is_published' => false, 'video_url' => self::VIDEO]);
        $theirs = Lesson::create(['course_module_id' => $two->id, 'title' => 'Theirs', 'sort_order' => 1,
            'is_published' => false, 'video_url' => self::VIDEO]);

        $this->actingAs($this->admin())->post(route('admin.modules.publish-ready', $one));

        $this->assertTrue($mine->fresh()->is_published);
        $this->assertFalse($theirs->fresh()->is_published);
    }

    /* Record and publish in one submit ------------------------------------- */

    public function test_pasting_a_link_and_pressing_publish_does_both(): void
    {
        $lesson = $this->lesson(['is_published' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.lessons.record', $lesson), ['video_url' => self::VIDEO, 'publish' => 1])
            ->assertRedirect(route('admin.courses.show', $lesson->module->course))
            ->assertSessionHas('success');

        $lesson->refresh();

        $this->assertTrue($lesson->is_published);
        $this->assertSame(LessonStage::Live, $lesson->stage());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->video_url);
    }

    public function test_pasting_a_link_without_publishing_leaves_it_ready(): void
    {
        $lesson = $this->lesson(['is_published' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.lessons.record', $lesson), ['video_url' => self::VIDEO]);

        $this->assertSame(LessonStage::Ready, $lesson->fresh()->stage());
    }

    public function test_a_pasted_line_that_is_not_a_link_is_refused(): void
    {
        $lesson = $this->lesson(['is_published' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.lessons.record', $lesson), ['video_url' => 'I will record it later'])
            ->assertSessionHasErrors('video_url');

        $this->assertNull($lesson->fresh()->video_url);
    }

    public function test_a_stranger_cannot_publish_anything(): void
    {
        $lesson = $this->lesson(['is_published' => false, 'video_url' => self::VIDEO]);

        $this->post(route('admin.lessons.record', $lesson), ['video_url' => self::VIDEO, 'publish' => 1])
            ->assertRedirect(route('login'));
        $this->post(route('admin.courses.publish-ready', $lesson->module->course))
            ->assertRedirect(route('login'));

        $this->assertFalse($lesson->fresh()->is_published);
    }

    /* Publishing an unrecorded topic is allowed, but never silent ---------- */

    public function test_publishing_a_topic_with_no_video_says_so(): void
    {
        $lesson = $this->lesson(['is_published' => false, 'content' => 'A written checklist.']);

        $this->actingAs($this->admin())
            ->post(route('admin.lessons.toggle-publish', $lesson))
            ->assertSessionHas('error');

        $this->assertTrue($lesson->fresh()->is_published, 'allowed, just not quietly');
    }

    public function test_publishing_a_topic_with_a_video_says_nothing_alarming(): void
    {
        $lesson = $this->lesson(['is_published' => false, 'video_url' => self::VIDEO]);

        $this->actingAs($this->admin())
            ->post(route('admin.lessons.toggle-publish', $lesson))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');
    }

    /* The curriculum tree -------------------------------------------------- */

    public function test_the_curriculum_tree_shows_the_stage_and_the_record_box(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Not recorded', 'sort_order' => 1,
            'is_published' => false]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Recorded', 'sort_order' => 2,
            'is_published' => false, 'video_url' => self::VIDEO]);

        $this->actingAs($this->admin())->get(route('admin.courses.show', $course))->assertOk()
            ->assertSee('Needs recording')
            ->assertSee('Ready to publish')
            ->assertSee('Recording progress')
            ->assertSee('1 of 2 topics recorded')
            ->assertSee(route('admin.lessons.record', Lesson::where('title', 'Not recorded')->sole()), false);
    }

    public function test_the_page_warns_about_topics_that_are_live_with_no_video(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Live and empty', 'sort_order' => 1,
            'is_published' => true]);

        $this->actingAs($this->admin())->get(route('admin.courses.show', $course))->assertOk()
            ->assertSee('Live, no video')
            ->assertSee('there is nothing to watch');
    }

    /** Writes a minimal course file in the shape the parser expects. */
    private function courseFile(string $lessons, string $moduleTitle = 'The first module'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'course').'.md';

        file_put_contents($path, <<<MD
        # Course 99 — A Test Course

        **Tier 1 · Foundations · Level: Beginner · Prerequisites: none**

        A course that exists only in this test.

        ## Module 1 — {$moduleTitle}

        {$lessons}
        MD);

        return $path;
    }
}
