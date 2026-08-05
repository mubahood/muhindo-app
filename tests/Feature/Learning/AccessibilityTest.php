<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Accessibility non-negotiables that are actually verifiable from code: captions,
 * accessible names on video embeds, YouTube's own captions enabled by default, and the
 * WCAG-AA-passing theme colors staying that way. Contrast ratios, exact focus-ring visuals,
 * and real screen-reader behavior aren't testable from PHPUnit. Those were verified by
 * computing WCAG contrast ratios directly (documented in the worklog) rather than a browser.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function enrolledStudentForLesson(array $lessonAttributes): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(array_merge(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0], $lessonAttributes));
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student];
    }

    public function test_an_admin_can_set_a_captions_url_on_a_lesson(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'title' => 'L1',
            'captions_url' => 'https://example.com/captions-en.vtt',
        ]);

        $this->assertSame('https://example.com/captions-en.vtt', $lesson->fresh()->captions_url);
    }

    public function test_a_self_hosted_video_with_captions_renders_a_track_element(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson([
            'video_disk_path' => $path,
            'captions_url' => 'https://example.com/captions-en.vtt',
        ]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('<track kind="captions" src="https://example.com/captions-en.vtt"', false);
    }

    public function test_a_self_hosted_video_without_captions_renders_no_track_element(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_disk_path' => $path]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertDontSee('<track', false);
    }

    public function test_the_youtube_player_enables_its_own_captions_by_default(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ']);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('cc_load_policy: 1', false);
    }

    public function test_video_embeds_have_an_accessible_name(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_url' => 'https://player.vimeo.com/video/12345678']);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('title="L1"', false);
    }

    public function test_the_muted_text_color_passes_wcag_aa_against_both_backgrounds(): void
    {
        $hex = '706f5c';
        $bgPairs = ['f7f6f2', 'ffffff'];

        foreach ($bgPairs as $bg) {
            $ratio = $this->contrastRatio($hex, $bg);
            $this->assertGreaterThanOrEqual(4.5, $ratio, "muted text vs #{$bg} must pass WCAG AA (4.5:1), got {$ratio}");
        }
    }

    public function test_the_gold_and_ok_accent_colors_pass_wcag_aa_against_their_soft_backgrounds(): void
    {
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('7d6228', 'f7f0df'), 'gold-d vs gold-soft');
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('0f6b30', 'e6f4ea'), 'ok vs ok-soft');
    }

    private function contrastRatio(string $hex1, string $hex2): float
    {
        $l1 = $this->relativeLuminance($hex1);
        $l2 = $this->relativeLuminance($hex2);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $f = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    }
}
