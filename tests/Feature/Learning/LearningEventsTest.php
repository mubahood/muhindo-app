<?php

namespace Tests\Feature\Learning;

use App\Events\Learning\CourseCompleted;
use App\Events\Learning\EnrollmentCreated;
use App\Events\Learning\LessonCompleted;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\CourseCompletedNotification;
use App\Notifications\EnrolledInCourseNotification;
use App\Services\Learning\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * §4.5 — LessonCompleted/CourseCompleted/EnrollmentCreated decouple
 * certificate issuance and notifications from ProgressService/enroll().
 */
class LearningEventsTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithOneLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        return [$course, $lesson];
    }

    public function test_completing_a_lesson_dispatches_lesson_completed(): void
    {
        Event::fake([LessonCompleted::class]);
        Storage::fake('local');
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);

        app(ProgressService::class)->completeLesson($enrollment, $lesson);

        Event::assertDispatched(LessonCompleted::class, fn ($e) => $e->enrollment->is($enrollment) && $e->lesson->is($lesson));
    }

    public function test_completing_the_final_lesson_dispatches_course_completed(): void
    {
        Event::fake([CourseCompleted::class]);
        Storage::fake('local');
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);

        app(ProgressService::class)->completeLesson($enrollment, $lesson);

        Event::assertDispatched(CourseCompleted::class, fn ($e) => $e->enrollment->is($enrollment));
    }

    public function test_course_completion_issues_exactly_one_certificate_and_sends_exactly_one_notification(): void
    {
        Storage::fake('local');
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);

        app(ProgressService::class)->completeLesson($enrollment, $lesson);

        $this->assertSame(1, \App\Models\Certificate::where('enrollment_id', $enrollment->id)->count());
        // Regression: auto-discovery + an explicit Event::listen() registration
        // for the same listener silently doubled every side-effect (the
        // student got two "course completed" emails). Pin the count at 1.
        $this->assertSame(1, DatabaseNotification::where('notifiable_id', $student->id)
            ->where('type', CourseCompletedNotification::class)->count());
    }

    public function test_the_course_completed_notification_links_to_the_already_issued_certificate(): void
    {
        Notification::fake();
        Storage::fake('local');
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);

        app(ProgressService::class)->completeLesson($enrollment, $lesson);

        Notification::assertSentTo($student, CourseCompletedNotification::class, function ($notification) {
            return $notification->enrollment->certificate !== null;
        });
    }

    public function test_a_genuinely_new_enrollment_dispatches_enrollment_created(): void
    {
        Event::fake([EnrollmentCreated::class]);
        [$course] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->post(route('courses.enroll', $course));

        Event::assertDispatched(EnrollmentCreated::class, fn ($e) => $e->enrollment->course_id === $course->id);
    }

    public function test_a_double_click_enroll_does_not_dispatch_enrollment_created_twice(): void
    {
        Event::fake([EnrollmentCreated::class]);
        [$course] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->post(route('courses.enroll', $course));
        $this->actingAs($student)->post(route('courses.enroll', $course));

        Event::assertDispatchedTimes(EnrollmentCreated::class, 1);
    }

    public function test_enrolling_sends_a_welcome_notification(): void
    {
        [$course] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->post(route('courses.enroll', $course));

        $this->assertSame(1, DatabaseNotification::where('notifiable_id', $student->id)
            ->where('type', EnrolledInCourseNotification::class)->count());
    }
}
