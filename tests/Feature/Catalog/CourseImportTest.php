<?php

namespace Tests\Feature\Catalog;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Services\Catalog\CourseFileParser;
use App\Services\Catalog\CourseImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parsing the authored course files and turning them into a catalogue.
 *
 * The parser is tested against a fixture holding every shape that appears
 * across the 21 real files — including the ones the brief did not document
 * (## Project, ## Bonus module) and the ones that only turn up once.
 */
class CourseImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = storage_path('framework/testing/07-fixture-course.md');
        @mkdir(dirname($this->fixture), 0775, true);
        file_put_contents($this->fixture, $this->fixtureBody());
    }

    protected function tearDown(): void
    {
        @unlink($this->fixture);
        parent::tearDown();
    }

    private function fixtureBody(): string
    {
        return <<<'MD'
        # Course 07 ⭐ — A Fixture Course

        **Tier 2 · Frameworks · Level: Intermediate · Prerequisites: Courses 01, 03 · TOP FEATURED**

        A course that exercises every shape in the authored files.

        **What you will learn**

        - The first outcome
        - The second outcome

        **System features:** one, two, three.

        ▶ Full playlist: https://www.youtube.com/playlist?list=PLFIXTURE123

        ---

        ## Module 1 — The long form

        1. **A lesson with its link below** — a description sentence.
           ▶ https://www.youtube.com/watch?v=aaaaaaaaaaa
        2. **A lesson with no video at all** — read this and do the practice.

        ```php
        <?php echo "belongs to lesson 2"; ?>
        ```

        ## Phase B — The shorthand

        3. A shorthand lesson — https://www.youtube.com/watch?v=bbbbbbbbbbb
        4. Another shorthand — https://youtu.be/ccccccccccc

        ## Project 1 — An undocumented heading that is still a module

        5. **Credited to somebody else** — worth watching. ▶ https://www.youtube.com/watch?v=ddddddddddd *(freeCodeCamp)*

        ## Final project

        Build the thing. Submit a link and a short write-up.

        **Quiz ideas per module:** one idea · another idea.
        MD;
    }

    private function parse(): array
    {
        return app(CourseFileParser::class)->parse($this->fixture);
    }

    // Parsing

    public function test_it_reads_the_courses_own_metadata(): void
    {
        $parsed = $this->parse();

        $this->assertSame(7, $parsed['course_number']);
        $this->assertTrue($parsed['is_featured']);
        $this->assertSame(2, $parsed['tier']);
        $this->assertSame('intermediate', $parsed['level']);
        $this->assertSame('Courses 01, 03', $parsed['prerequisites_note']);
        $this->assertSame(['The first outcome', 'The second outcome'], $parsed['outcomes']);
        $this->assertStringContainsString('exercises every shape', $parsed['description']);
        $this->assertStringContainsString('System features', $parsed['description']);
        $this->assertStringContainsString('PLFIXTURE123', (string) $parsed['playlist_url']);
    }

    public function test_module_phase_and_project_headings_all_make_modules(): void
    {
        // The brief documented Module and Phase. Project and Bonus module are
        // in the real files too, and were found by scanning rather than reading.
        $titles = array_column($this->parse()['modules'], 'title');

        $this->assertSame(
            ['The long form', 'The shorthand', 'An undocumented heading that is still a module'],
            $titles
        );
    }

    public function test_it_reads_both_lesson_shapes_and_the_text_lesson(): void
    {
        $modules = $this->parse()['modules'];

        $first = $modules[0]['lessons'][0];
        $this->assertSame('A lesson with its link below', $first['title']);
        $this->assertSame('aaaaaaaaaaa', $first['video_id']);
        $this->assertStringContainsString('a description sentence', implode(' ', $first['body']));
        // The ▶ is a three-byte character; a byte-wise strip would leave a
        // fragment here and MySQL would reject the row.
        $this->assertTrue(mb_check_encoding(implode('', $first['body']), 'UTF-8'));
        $this->assertStringNotContainsString('▶', implode('', $first['body']));

        $text = $modules[0]['lessons'][1];
        $this->assertNull($text['video_id'], 'a lesson with no link is a text lesson');
        $this->assertStringContainsString('belongs to lesson 2', implode("\n", $text['body']));

        $this->assertSame('bbbbbbbbbbb', $modules[1]['lessons'][0]['video_id']);
        $this->assertSame('ccccccccccc', $modules[1]['lessons'][1]['video_id'], 'youtu.be shape');
    }

    public function test_an_external_video_keeps_its_credit(): void
    {
        $lesson = $this->parse()['modules'][2]['lessons'][0];

        $this->assertTrue($lesson['is_external']);
        $this->assertSame('freeCodeCamp', $lesson['attribution']);
        $this->assertSame('Credited to somebody else', $lesson['title']);
    }

    public function test_the_playlist_line_never_becomes_a_lesson(): void
    {
        $parsed = $this->parse();
        $allTitles = collect($parsed['modules'])->flatMap(fn ($m) => array_column($m['lessons'], 'title'));

        $this->assertFalse($allTitles->contains(fn ($t) => str_contains($t, 'playlist')));
        $this->assertCount(5, $allTitles);
    }

    public function test_the_final_project_becomes_an_assignment_brief(): void
    {
        $parsed = $this->parse();

        $this->assertSame('Final project', $parsed['assignment']['title']);
        $this->assertStringContainsString('Build the thing', $parsed['assignment']['body']);
        $this->assertNotNull($parsed['quiz_brief']);
    }

    // Importing

    public function test_it_creates_the_course_its_modules_and_its_lessons(): void
    {
        $result = app(CourseImporter::class)->import($this->parse());

        $this->assertTrue($result['created']);
        $this->assertSame(3, $result['modules']);
        $this->assertSame(5, $result['lessons']);
        $this->assertSame(4, $result['videos']);
        $this->assertSame(1, $result['text']);

        $course = $result['course'];
        $this->assertSame('a-fixture-course', $course->slug);
        $this->assertTrue($course->is_featured);
        $this->assertSame(7, $course->course_number);

        // A freshly imported course is never published or priced by the import.
        $this->assertFalse($course->is_published);
        $this->assertSame('a-fixture-course', $course->slug);
        $this->assertSame(1, Assignment::where('course_id', $course->id)->count());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $importer = app(CourseImporter::class);

        $importer->import($this->parse());
        $first = [Course::count(), CourseModule::count(), Lesson::count(), Assignment::count()];

        $second = $importer->import($this->parse());

        $this->assertFalse($second['created'], 'the second run must update, not create');
        $this->assertSame($first, [Course::count(), CourseModule::count(), Lesson::count(), Assignment::count()]);
    }

    public function test_it_does_not_republish_or_reprice_a_course_the_owner_has_set(): void
    {
        $importer = app(CourseImporter::class);
        $course = $importer->import($this->parse())['course'];

        $course->update(['is_published' => true, 'price' => '150000.00']);

        $importer->import($this->parse());

        $course->refresh();
        $this->assertTrue($course->is_published, 'an import must not unpublish a live course');
        $this->assertSame('150000.00', (string) $course->price, 'an import must not reset a price');
    }

    public function test_a_lesson_removed_from_the_file_is_removed_from_the_course(): void
    {
        $importer = app(CourseImporter::class);
        $importer->import($this->parse());

        $trimmed = str_replace(
            "4. Another shorthand — https://youtu.be/ccccccccccc\n",
            '',
            $this->fixtureBody()
        );
        file_put_contents($this->fixture, $trimmed);

        $result = $importer->import($this->parse());

        $this->assertSame(4, $result['lessons']);
        $this->assertSame(0, Lesson::where('title', 'Another shorthand')->count());
    }

    // The embeddability the link report settled

    public function test_a_video_that_cannot_be_embedded_is_marked_and_keeps_its_watch_url(): void
    {
        // aaaaaaaaaaa is live but its owner disabled embedding.
        $result = app(CourseImporter::class)->import($this->parse(), ['aaaaaaaaaaa' => false]);

        $lesson = Lesson::where('title', 'A lesson with its link below')->firstOrFail();

        $this->assertFalse($lesson->is_embeddable);
        $this->assertSame('https://www.youtube.com/watch?v=aaaaaaaaaaa', $lesson->resource_url);
        // Still stored, so switching embedding back on needs no re-import.
        $this->assertStringContainsString('youtube-nocookie.com/embed/aaaaaaaaaaa', (string) $lesson->video_url);

        $embeddable = Lesson::where('title', 'A shorthand lesson')->firstOrFail();
        $this->assertTrue($embeddable->is_embeddable);
    }

    public function test_the_player_links_out_instead_of_showing_a_dead_frame(): void
    {
        $course = app(CourseImporter::class)->import($this->parse(), ['aaaaaaaaaaa' => false])['course'];
        $course->update(['is_published' => true]);

        $lesson = Lesson::where('title', 'A lesson with its link below')->firstOrFail();
        $student = \App\Models\User::factory()->create(['role' => 'student', 'is_student' => true]);

        \App\Models\Enrollment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertOk()
            ->assertSee('This lesson plays on YouTube')
            ->assertSee('https://www.youtube.com/watch?v=aaaaaaaaaaa', false)
            ->assertDontSee('youtube-nocookie.com/embed/aaaaaaaaaaa', false);
    }

    // The real files

    public function test_every_authored_course_parses_into_something_importable(): void
    {
        $parser = app(CourseFileParser::class);
        $scanner = app(\App\Services\Catalog\CourseFileScanner::class);

        foreach ($scanner->files(base_path('course-content')) as $path) {
            $parsed = $parser->parse($path);
            $name = basename($path);

            $this->assertNotSame('', $parsed['title'], "{$name} has no title");
            $this->assertNotNull($parsed['course_number'], "{$name} has no course number");
            $this->assertNotEmpty($parsed['modules'], "{$name} produced no modules");
            $this->assertNotNull($parsed['assignment'], "{$name} has no final project");

            foreach ($parsed['modules'] as $module) {
                $this->assertNotEmpty($module['lessons'], "{$name}: module '{$module['title']}' has no lessons");

                foreach ($module['lessons'] as $lesson) {
                    $this->assertNotSame('', $lesson['title'], "{$name} has a lesson with no title");
                    $this->assertTrue(
                        mb_check_encoding($lesson['title'].implode('', $lesson['body']), 'UTF-8'),
                        "{$name}: lesson '{$lesson['title']}' carries broken bytes"
                    );
                }
            }
        }
    }
}
