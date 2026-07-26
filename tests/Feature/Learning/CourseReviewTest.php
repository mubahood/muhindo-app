<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §7.3 — reviews: gated at ≥50% progress, moderated (unpublished until an admin approves), one per enrollment. */
class CourseReviewTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(int $completedOfTwo = 1): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        if ($completedOfTwo >= 1) {
            $enrollment->progressRecords()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);
        }

        return [$course, $student, $enrollment];
    }

    public function test_a_student_at_50_percent_can_submit_a_review_and_it_starts_unpublished(): void
    {
        [$course, $student] = $this->enrolledStudent(completedOfTwo: 1);

        $this->actingAs($student)->post(route('learn.review.store', $course), [
            'rating' => 5, 'body' => 'Loved it!',
        ])->assertRedirect(route('learn.index'));

        $review = CourseReview::first();
        $this->assertSame(5, $review->rating);
        $this->assertFalse($review->is_published);
    }

    public function test_a_student_below_50_percent_cannot_submit_a_review(): void
    {
        [$course, $student] = $this->enrolledStudent(completedOfTwo: 0);

        $this->actingAs($student)->post(route('learn.review.store', $course), ['rating' => 5])
            ->assertForbidden();

        $this->assertSame(0, CourseReview::count());
    }

    public function test_resubmitting_updates_the_same_review_not_a_new_row(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $this->actingAs($student);

        $this->post(route('learn.review.store', $course), ['rating' => 3, 'body' => 'It was okay']);
        $this->post(route('learn.review.store', $course), ['rating' => 5, 'body' => 'Actually great']);

        $this->assertSame(1, CourseReview::count());
        $this->assertSame(5, CourseReview::first()->rating);
    }

    public function test_editing_an_already_published_review_resets_it_to_unpublished(): void
    {
        [$course, $student, $enrollment] = $this->enrolledStudent();
        $review = CourseReview::create([
            'enrollment_id' => $enrollment->id, 'course_id' => $course->id,
            'rating' => 4, 'body' => 'Good', 'is_published' => true,
        ]);

        $this->actingAs($student)->post(route('learn.review.store', $course), ['rating' => 2, 'body' => 'Changed my mind']);

        $this->assertFalse($review->fresh()->is_published);
    }

    public function test_resubmitting_the_exact_same_content_does_not_reset_an_already_published_review(): void
    {
        [$course, $student, $enrollment] = $this->enrolledStudent();
        $review = CourseReview::create([
            'enrollment_id' => $enrollment->id, 'course_id' => $course->id,
            'rating' => 4, 'body' => 'Good', 'is_published' => true,
        ]);

        $this->actingAs($student)->post(route('learn.review.store', $course), ['rating' => 4, 'body' => 'Good']);

        $this->assertTrue($review->fresh()->is_published);
    }

    public function test_the_catalogue_shows_the_average_of_published_reviews_only(): void
    {
        [$course, $student, $enrollment] = $this->enrolledStudent();
        $enrollment->review()->create(['course_id' => $course->id, 'rating' => 5, 'is_published' => true]);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherEnrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $otherStudent->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $otherEnrollment->review()->create(['course_id' => $course->id, 'rating' => 1, 'is_published' => false]);

        $this->get(route('courses.show', $course))->assertOk()->assertSee('5.0');
    }
}
