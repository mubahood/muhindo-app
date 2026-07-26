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

/** §7.4 — images uploaded into markdown content, stored privately, served back only to an enrolled (and unlocked) student. */
class LessonContentImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function courseWithLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        return [$course, $lesson];
    }

    public function test_an_admin_can_upload_a_content_image_and_gets_a_url_back(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        [$course, $lesson] = $this->courseWithLesson();

        $response = $this->actingAs($admin)->postJson(route('admin.lessons.content-images.store', $lesson), [
            'image' => UploadedFile::fake()->image('diagram.png'),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotNull($response->json('url'));
        Storage::disk('local')->assertExists('lesson-content-images/'.$lesson->id.'/'.basename(parse_url($response->json('url'), PHP_URL_PATH)));
    }

    public function test_an_enrolled_student_can_view_an_uploaded_content_image(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        [$course, $lesson] = $this->courseWithLesson();
        $uploadResponse = $this->actingAs($admin)->postJson(route('admin.lessons.content-images.store', $lesson), [
            'image' => UploadedFile::fake()->image('diagram.png'),
        ]);

        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($student)->get($uploadResponse->json('url'))->assertOk();
    }

    public function test_a_non_enrolled_user_cannot_view_a_content_image(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        [$course, $lesson] = $this->courseWithLesson();
        $uploadResponse = $this->actingAs($admin)->postJson(route('admin.lessons.content-images.store', $lesson), [
            'image' => UploadedFile::fake()->image('diagram.png'),
        ]);

        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($stranger)->get($uploadResponse->json('url'))->assertNotFound();
    }

    public function test_a_path_traversal_attempt_in_the_filename_is_neutralized(): void
    {
        Storage::fake('local');
        [$course, $lesson] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('learn.content-images.show', [$course, $lesson, '..%2F..%2F..%2Fetc%2Fpasswd']))
            ->assertNotFound();
    }
}
