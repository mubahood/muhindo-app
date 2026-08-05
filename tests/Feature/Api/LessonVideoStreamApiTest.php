<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The API's self-hosted video stream route is deliberately `signed`-only (no
 * auth:sanctum), since a native mobile player generally can't attach a bearer token to its
 * own request. Real authorization already happened when Api\V1\LessonController::show()
 * minted the signed URL in the first place.
 */
class LessonVideoStreamApiTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithVideoLesson(): array
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4')->store('lesson-videos', 'local');
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0, 'video_disk_path' => $path]);

        return [$course, $lesson];
    }

    public function test_a_valid_signature_streams_the_video_with_no_bearer_token_needed(): void
    {
        [$course, $lesson] = $this->courseWithVideoLesson();

        $url = URL::temporarySignedRoute('api.lessons.video-stream', now()->addHours(6), ['course' => $course, 'lesson' => $lesson]);

        $this->getJson($url)->assertOk();
    }

    public function test_streaming_without_a_signature_is_rejected(): void
    {
        [$course, $lesson] = $this->courseWithVideoLesson();

        $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}/video-stream")->assertForbidden();
    }

    public function test_an_expired_signature_is_rejected(): void
    {
        [$course, $lesson] = $this->courseWithVideoLesson();

        $url = URL::temporarySignedRoute('api.lessons.video-stream', now()->subHour(), ['course' => $course, 'lesson' => $lesson]);

        $this->getJson($url)->assertForbidden();
    }
}
