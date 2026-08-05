<?php

namespace Tests\Feature\Catalog;

use App\Services\Catalog\CourseFileScanner;
use App\Services\Catalog\YouTubeLinkChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifying the authored course files before any of it becomes a lesson.
 *
 * The distinction this defends is the whole point of the stage: oEmbed answers
 * 401 both for a video that is gone and for a live video whose owner disabled
 * embedding, and those two demand opposite responses. Measured on the real
 * catalogue, 67 of 68 initial failures were the second kind — treating them as
 * dead would have replaced 67 of Muhindo's own lessons with written text.
 */
class CourseLinkVerificationTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = storage_path('framework/testing/link-cache-'.uniqid().'.json');
    }

    protected function tearDown(): void
    {
        if (is_file($this->cachePath)) {
            unlink($this->cachePath);
        }
        parent::tearDown();
    }

    private function checker(): YouTubeLinkChecker
    {
        // No delay: the rate limit protects YouTube, not the test suite.
        return new YouTubeLinkChecker($this->cachePath, delayMs: 0);
    }

    private function watchPage(string $status = 'OK', string $title = 'A real lesson'): string
    {
        return '<html><head><meta name="title" content="'.$title.'"></head>'
            .'<body>{"playabilityStatus":{"status":"'.$status.'"}}</body></html>';
    }

    // The three verdicts

    public function test_an_embeddable_video_is_reported_as_playable_here(): void
    {
        Http::fake(['*oembed*' => Http::response(['title' => 'Bootstrap grid'], 200)]);

        $result = $this->checker()->check('video', 'abcdefghijk');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['embeddable']);
        $this->assertSame('Bootstrap grid', $result['title']);
    }

    public function test_a_live_video_with_embedding_off_is_not_treated_as_dead(): void
    {
        Http::fake([
            '*oembed*' => Http::response('', 401),
            '*youtube.com/watch*' => Http::response($this->watchPage('OK', 'PART 16. SQL Insert'), 200),
        ]);

        $result = $this->checker()->check('video', 'abcdefghijk');

        // Real teaching. It must survive the import and link out, not be
        // replaced or rewritten.
        $this->assertTrue($result['ok'], 'a live video must never be reported as gone');
        $this->assertFalse($result['embeddable']);
        $this->assertSame('PART 16. SQL Insert', $result['title']);
        $this->assertStringContainsString('embedding disabled', (string) $result['reason']);
    }

    public function test_a_private_video_is_reported_as_gone(): void
    {
        Http::fake([
            '*oembed*' => Http::response('', 401),
            '*youtube.com/watch*' => Http::response($this->watchPage('LOGIN_REQUIRED'), 200),
        ]);

        $result = $this->checker()->check('video', 'abcdefghijk');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('private', (string) $result['reason']);
    }

    public function test_a_removed_video_is_reported_as_gone(): void
    {
        Http::fake([
            '*oembed*' => Http::response('', 404),
            '*youtube.com/watch*' => Http::response('', 404),
        ]);

        $result = $this->checker()->check('video', 'abcdefghijk');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('removed', (string) $result['reason']);
    }

    // Caching

    public function test_a_verdict_is_cached_so_a_re_run_costs_nothing(): void
    {
        Http::fake(['*oembed*' => Http::response(['title' => 'Cached'], 200)]);

        $checker = $this->checker();
        $this->assertFalse($checker->check('video', 'abcdefghijk')['cached']);
        $this->assertTrue($checker->check('video', 'abcdefghijk')['cached']);

        Http::assertSentCount(1);
    }

    public function test_a_network_failure_is_never_written_down_as_a_verdict(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $result = $this->checker()->check('video', 'abcdefghijk');

        $this->assertSame('unreachable', $result['reason']);
        // Caching this would let one flaky minute condemn a good video for good.
        $this->assertFileDoesNotExist($this->cachePath);
    }

    // Reading the authored files

    public function test_the_scanner_reads_both_file_shapes(): void
    {
        $scanner = app(CourseFileScanner::class);
        $dir = base_path('course-content');

        $this->assertCount(21, $scanner->files($dir), 'all 21 course files must be found, index excluded');

        // Tier 1: "N. **Title** — text" with the link on its own indented line.
        $tier1 = $scanner->references($dir.'/01-introduction-to-web-development.md');
        $first = collect($tier1)->firstWhere('id', 'y7mC6h1wPL4');
        $this->assertNotNull($first);
        $this->assertSame('Setting up your tools & your first HTML page', $first['label']);

        // Capstone: "N. Title — https://…" plus a course-level playlist.
        $capstone = $scanner->references($dir.'/16-invetotrack-inventory-system.md');
        $this->assertNotNull(collect($capstone)->firstWhere('type', 'playlist'));
        $this->assertSame('Laravel setup', collect($capstone)->firstWhere('id', '2mAmNPEQMos')['label']);
    }

    public function test_the_featured_star_and_course_number_are_read_from_the_heading(): void
    {
        $scanner = app(CourseFileScanner::class);
        $dir = base_path('course-content');

        $plain = $scanner->heading($dir.'/01-introduction-to-web-development.md');
        $this->assertSame(1, $plain['number']);
        $this->assertFalse($plain['featured']);

        $starred = $scanner->heading($dir.'/16-invetotrack-inventory-system.md');
        $this->assertSame(16, $starred['number']);
        $this->assertTrue($starred['featured']);
        $this->assertStringContainsString('InvetoTrack', $starred['title']);
    }

    public function test_the_repaired_lesson_no_longer_points_at_the_private_video(): void
    {
        $file = (string) file_get_contents(base_path('course-content/11-flutter-mobile-development.md'));

        // pUhkQmC2PyE returns LOGIN_REQUIRED. It is now a written lesson.
        $this->assertStringNotContainsString('pUhkQmC2PyE', $file);
        $this->assertStringContainsString('What an HTTP request actually is', $file);
    }
}
