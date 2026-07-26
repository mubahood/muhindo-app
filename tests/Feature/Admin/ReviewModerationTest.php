<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ReviewModeration;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** §7.3 — the admin review moderation queue: publish/unpublish/delete. */
class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function pendingReview(): CourseReview
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return CourseReview::create(['enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'rating' => 4, 'body' => 'Solid course']);
    }

    public function test_a_non_admin_cannot_view_the_moderation_queue(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.reviews.index'))->assertRedirect(route('login'));
    }

    public function test_the_queue_lists_a_pending_review(): void
    {
        $admin = $this->admin();
        $review = $this->pendingReview();

        $this->actingAs($admin)->get(route('admin.reviews.index'))
            ->assertOk()->assertSee($review->body);
    }

    public function test_publishing_a_review_makes_it_public(): void
    {
        $admin = $this->admin();
        $review = $this->pendingReview();

        Livewire::actingAs($admin)->test(ReviewModeration::class)->call('publish', $review->id);

        $this->assertTrue($review->fresh()->is_published);
    }

    public function test_unpublishing_hides_it_again(): void
    {
        $admin = $this->admin();
        $review = $this->pendingReview();
        $review->update(['is_published' => true]);

        Livewire::actingAs($admin)->test(ReviewModeration::class)->call('unpublish', $review->id);

        $this->assertFalse($review->fresh()->is_published);
    }

    public function test_deleting_a_review_removes_it(): void
    {
        $admin = $this->admin();
        $review = $this->pendingReview();

        Livewire::actingAs($admin)->test(ReviewModeration::class)->call('delete', $review->id);

        $this->assertDatabaseMissing('course_reviews', ['id' => $review->id]);
    }
}
