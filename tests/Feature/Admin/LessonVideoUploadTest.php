<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P5.3 — admin-side self-hosted video upload: store, replace, and remove. */
class LessonVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function moduleFor(Course $course): CourseModule
    {
        return CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
    }

    public function test_uploading_a_video_file_when_creating_a_lesson_stores_it_and_sets_the_path(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = $this->moduleFor($course);

        $this->actingAs($admin)->post(route('admin.modules.lessons.store', $module), [
            'title' => 'L1',
            'video_file' => UploadedFile::fake()->create('lesson.mp4', 2000, 'video/mp4'),
        ])->assertRedirect(route('admin.courses.show', $course));

        $lesson = Lesson::where('title', 'L1')->first();
        $this->assertTrue($lesson->hasSelfHostedVideo());
        Storage::disk('local')->assertExists($lesson->video_disk_path);
    }

    public function test_uploading_a_new_video_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = $this->moduleFor($course);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $oldPath = UploadedFile::fake()->create('old.mp4', 1000, 'video/mp4')->store('lesson-videos', 'local');
        $lesson->update(['video_disk_path' => $oldPath]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'title' => 'L1',
            'video_file' => UploadedFile::fake()->create('new.mp4', 1500, 'video/mp4'),
        ]);

        $lesson->refresh();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($lesson->video_disk_path);
        $this->assertNotSame($oldPath, $lesson->video_disk_path);
    }

    public function test_the_remove_checkbox_deletes_the_file_and_clears_the_path(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = $this->moduleFor($course);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $path = UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4')->store('lesson-videos', 'local');
        $lesson->update(['video_disk_path' => $path]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'title' => 'L1',
            'remove_video_file' => '1',
        ]);

        $lesson->refresh();
        Storage::disk('local')->assertMissing($path);
        $this->assertFalse($lesson->hasSelfHostedVideo());
    }

    public function test_a_non_video_file_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = $this->moduleFor($course);

        $this->actingAs($admin)->post(route('admin.modules.lessons.store', $module), [
            'title' => 'L1',
            'video_file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('video_file');
    }

    public function test_leaving_the_video_file_untouched_on_update_keeps_the_existing_path(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = $this->moduleFor($course);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $path = UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4')->store('lesson-videos', 'local');
        $lesson->update(['video_disk_path' => $path]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), ['title' => 'L1 updated']);

        $this->assertSame($path, $lesson->fresh()->video_disk_path);
    }
}
