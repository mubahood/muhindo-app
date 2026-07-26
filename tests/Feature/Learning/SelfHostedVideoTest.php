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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P5.3 — self-hosted video: signed streaming URL, policy enforcement, and player rendering priority. */
class SelfHostedVideoTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_lesson_page_renders_the_self_hosted_player_with_a_signed_stream_url_when_a_video_file_is_attached(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_disk_path' => $path]);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk()
            ->assertSee('x-data="selfHostedVideoPlayer(', false)
            ->assertSee('video-player-'.$lesson->id, false)
            ->assertSee('signature=', false);
    }

    public function test_a_self_hosted_video_takes_priority_over_a_youtube_url_when_both_are_set(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson([
            'video_disk_path' => $path,
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('x-data="selfHostedVideoPlayer(', false)
            ->assertDontSee('x-data="youtubePlayer(', false);
    }

    public function test_streaming_without_a_valid_signature_is_rejected(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_disk_path' => $path]);

        $this->actingAs($student)->get(route('learn.lesson.video-stream', [$course, $lesson]))
            ->assertForbidden();
    }

    public function test_streaming_with_a_valid_signature_returns_the_video(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_disk_path' => $path]);

        $url = URL::temporarySignedRoute('learn.lesson.video-stream', now()->addHour(), ['course' => $course, 'lesson' => $lesson]);

        $this->actingAs($student)->get($url)->assertOk();
    }

    /** No enrollment row at all -> the same firstOrFail() 404 LessonMaterialController::download() already uses for this exact scenario. */
    public function test_a_non_enrolled_user_cannot_stream_even_with_a_valid_signature(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson] = $this->enrolledStudentForLesson(['video_disk_path' => $path]);
        $outsider = User::factory()->create(['role' => 'student']);

        $url = URL::temporarySignedRoute('learn.lesson.video-stream', now()->addHour(), ['course' => $course, 'lesson' => $lesson]);

        $this->actingAs($outsider)->get($url)->assertNotFound();
    }

    public function test_an_enrolled_but_pending_students_signature_still_cannot_stream(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0, 'video_disk_path' => $path]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'pending', 'source' => 'self',
        ]);

        $url = URL::temporarySignedRoute('learn.lesson.video-stream', now()->addHour(), ['course' => $course, 'lesson' => $lesson]);

        $this->actingAs($student)->get($url)->assertForbidden();
    }

    public function test_an_expired_signature_is_rejected(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_disk_path' => $path]);

        $url = URL::temporarySignedRoute('learn.lesson.video-stream', now()->subHour(), ['course' => $course, 'lesson' => $lesson]);

        $this->actingAs($student)->get($url)->assertForbidden();
    }

    public function test_a_lesson_without_a_self_hosted_video_404s_on_the_stream_route(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson([]);

        $url = URL::temporarySignedRoute('learn.lesson.video-stream', now()->addHour(), ['course' => $course, 'lesson' => $lesson]);

        $this->actingAs($student)->get($url)->assertNotFound();
    }
}
