<?php

namespace Tests\Feature\Learning;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function publishedCourseWithOneLesson(): Course
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Getting Started', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Lesson 1', 'sort_order' => 0]);

        return $course;
    }

    public function test_guest_cannot_enrol(): void
    {
        $course = $this->publishedCourseWithOneLesson();

        $this->post("/courses/{$course->slug}/enroll")->assertRedirect('/login');
    }

    public function test_student_can_self_enrol_in_a_free_published_course(): void
    {
        $course = $this->publishedCourseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->post("/courses/{$course->slug}/enroll");

        $response->assertRedirect(route('learn.course', $course));
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
    }

    public function test_completing_the_only_lesson_issues_a_certificate(): void
    {
        Storage::fake('local');
        $course = $this->publishedCourseWithOneLesson();
        $lesson = Lesson::first();
        $student = User::factory()->create(['role' => 'student']);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('learn.lesson.complete', [$course, $lesson]))
            ->assertRedirect(route('learn.index'));

        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertDatabaseHas('certificates', ['enrollment_id' => $enrollment->id]);
    }

    public function test_a_student_can_download_their_own_certificate_pdf(): void
    {
        Storage::fake('local');
        $course = $this->publishedCourseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'completed', 'source' => 'self', 'enrolled_at' => now(), 'completed_at' => now(),
        ]);
        $certificate = Certificate::create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id,
            'certificate_no' => 'CRT-TEST-1', 'issued_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('learn.certificate', $certificate));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_different_student_cannot_download_someone_elses_certificate(): void
    {
        $course = $this->publishedCourseWithOneLesson();
        $owner = User::factory()->create(['role' => 'student']);
        $stranger = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'course_id' => $course->id,
            'status' => 'completed', 'source' => 'self', 'enrolled_at' => now(), 'completed_at' => now(),
        ]);
        $certificate = Certificate::create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id,
            'certificate_no' => 'CRT-TEST-2', 'issued_at' => now(),
        ]);

        $this->actingAs($stranger)->get(route('learn.certificate', $certificate))->assertForbidden();
    }
}
