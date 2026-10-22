<?php

namespace Tests\Feature\Catalog;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The command that turns the authored practice pages into the Bootstrap course.
 *
 * The rule that matters: a lesson goes live only when the video manifest names
 * a video for it. Everything else stays a draft, so a re-sync can never put an
 * empty lesson in front of a student.
 */
class BootstrapPracticeGroundSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/practice-ground-'.uniqid());
        File::makeDirectory($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function page(string $module, string $file, string $title): void
    {
        File::ensureDirectoryExists("{$this->root}/{$module}");
        File::put("{$this->root}/{$module}/{$file}", "<h1>{$title}</h1><p>Body.</p>");
    }

    private function manifest(array $videos): void
    {
        File::put("{$this->root}/_videos.json", json_encode($videos));
    }

    private function sync(): void
    {
        $this->artisan('courses:sync-bootstrap-practice-ground', ['--path' => $this->root])
            ->assertSuccessful();
    }

    private function course(): Course
    {
        return Course::where('slug', 'ai-powered-frontend-development-with-bootstrap')->firstOrFail();
    }

    public function test_a_lesson_with_a_video_goes_live_and_one_without_stays_a_draft(): void
    {
        $this->page('01. First module', '1.1 - BOOTSTRAP - Recorded lesson.html', 'Recorded lesson');
        $this->page('01. First module', '1.2 - BOOTSTRAP - Not recorded yet.html', 'Not recorded yet');
        $this->manifest(['1.1' => ['youtube' => 'abc123XYZ_1', 'seconds' => 415]]);

        $this->sync();

        $lessons = $this->course()->modules()->first()->lessons()->orderBy('sort_order')->get();

        $this->assertTrue($lessons[0]->is_published);
        $this->assertSame('https://www.youtube-nocookie.com/embed/abc123XYZ_1', $lessons[0]->video_url);
        $this->assertSame(7, $lessons[0]->duration_minutes); // 415s rounds to 7 minutes

        $this->assertFalse($lessons[1]->is_published);
        $this->assertNull($lessons[1]->video_url);
        $this->assertNull($lessons[1]->duration_minutes);
    }

    public function test_a_bonus_lesson_number_is_matched_and_sorted_after_its_parent(): void
    {
        $this->page('04. Fourth module', '4.2 - BOOTSTRAP - Parent lesson.html', 'Parent lesson');
        $this->page('04. Fourth module', '4.2.1 - BOOTSTRAP - Bonus lesson.html', 'Bonus lesson');
        $this->page('04. Fourth module', '4.3 - BOOTSTRAP - Next lesson.html', 'Next lesson');
        $this->manifest(['4.2.1' => ['youtube' => 'bonusVid123', 'seconds' => 574]]);

        $this->sync();

        $titles = $this->course()->modules()->first()->lessons()
            ->orderBy('sort_order')->pluck('title')->all();

        $this->assertSame(['Parent lesson', 'Bonus lesson', 'Next lesson'], $titles);
    }

    public function test_folders_without_lesson_pages_are_not_modules(): void
    {
        $this->page('01. Real module', '1.1 - BOOTSTRAP - A lesson.html', 'A lesson');
        File::ensureDirectoryExists("{$this->root}/css");
        File::put("{$this->root}/css/practice.css", 'body{}');
        $this->manifest([]);

        $this->sync();

        $this->assertSame(['01. Real module'], $this->course()->modules()->pluck('title')->all());
    }

    public function test_a_lesson_the_source_has_dropped_is_removed(): void
    {
        $this->page('01. Module', '1.1 - BOOTSTRAP - Keeps.html', 'Keeps');
        $this->page('01. Module', '1.2 - BOOTSTRAP - Goes away.html', 'Goes away');
        $this->manifest([]);
        $this->sync();
        $this->assertSame(2, $this->course()->modules()->first()->lessons()->count());

        File::delete("{$this->root}/01. Module/1.2 - BOOTSTRAP - Goes away.html");
        $this->sync();

        $this->assertSame(['Keeps'], $this->course()->modules()->first()->lessons()->pluck('title')->all());
    }

    public function test_a_missing_manifest_leaves_every_lesson_a_draft(): void
    {
        $this->page('01. Module', '1.1 - BOOTSTRAP - A lesson.html', 'A lesson');

        $this->sync();

        $this->assertFalse($this->course()->modules()->first()->lessons()->first()->is_published);
    }
}
