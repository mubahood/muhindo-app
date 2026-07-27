<?php

namespace Tests\Feature\Api;

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

/** P5.4 — API v1 parity: lesson detail, heartbeat, free self-enroll expiry stamping. */
class LessonApiTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithLesson(array $lessonAttributes = []): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(array_merge(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0], $lessonAttributes));

        return [$course, $lesson];
    }

    private function enrolledStudent(Course $course): User
    {
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return $student;
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        [$course, $lesson] = $this->courseWithLesson();

        $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}")->assertUnauthorized();
    }

    public function test_the_lesson_endpoint_returns_content_and_resume_position(): void
    {
        [$course, $lesson] = $this->courseWithLesson(['content' => 'Hello world']);
        $student = $this->enrolledStudent($course);
        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $enrollment->progressRecords()->create(['lesson_id' => $lesson->id, 'last_position_seconds' => 42]);

        $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}")
            ->assertOk()
            ->assertJsonPath('data.resume_position_seconds', 42)
            ->assertJsonPath('data.completed', false);
    }

    public function test_the_lesson_endpoint_includes_a_signed_stream_url_for_a_self_hosted_video(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        [$course, $lesson] = $this->courseWithLesson(['video_disk_path' => $path]);
        $student = $this->enrolledStudent($course);

        $response = $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}")
            ->assertOk();

        $this->assertNotNull($response->json('data.video_stream_url'));
        $this->assertStringContainsString('signature=', $response->json('data.video_stream_url'));
    }

    public function test_a_pending_enrollment_cannot_view_the_lesson(): void
    {
        [$course, $lesson] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'pending', 'source' => 'self',
        ]);

        $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}")
            ->assertForbidden();
    }

    public function test_a_heartbeat_records_watch_time_and_the_same_completion_rule_as_the_web_player(): void
    {
        [$course, $lesson] = $this->courseWithLesson(['completion_rule' => 'min_watch', 'completion_threshold' => 50, 'duration_minutes' => 1]);
        $student = $this->enrolledStudent($course);

        $response = $this->withToken($this->token($student))
            ->postJson("/api/v1/lessons/{$lesson->id}/heartbeat", ['seconds_delta' => 30, 'position_seconds' => 30])
            ->assertOk();

        $this->assertSame(30, $response->json('data.watch_seconds'));
        $this->assertTrue($response->json('data.completed'));
    }

    public function test_free_self_enroll_via_the_api_stamps_the_courses_access_window(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0, 'access_duration_days' => 30]);
        $student = User::factory()->create(['role' => 'student']);

        $this->withToken($this->token($student))
            ->postJson("/api/v1/courses/{$course->slug}/enroll")
            ->assertCreated();

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $this->assertNotNull($enrollment->expires_at);
    }
}
