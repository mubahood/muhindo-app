<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** public-w3, of PUBLIC_SITE_PLAN.md: a dismissible, three-item first-visit checklist. */
class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_student_sees_the_onboarding_checklist(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => null]);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()->assertSee('Getting started');
    }

    public function test_the_checklist_is_hidden_once_dismissed(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => null]);

        $this->actingAs($student)->post(route('dashboard.onboarding.dismiss'))->assertRedirect(route('dashboard'));

        $response = $this->actingAs($student)->get(route('dashboard'));
        $response->assertOk()->assertDontSee('Getting started');
        $this->assertNotNull($student->fresh()->onboarding_dismissed_at);
    }

    public function test_the_checklist_is_hidden_once_every_item_is_genuinely_complete(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now(), 'avatar' => 'avatars/x.jpg']);
        $course = Course::factory()->create(['is_published' => true]);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'progress_percent' => 50,
        ]);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()->assertDontSee('Getting started');
    }

    public function test_only_the_verify_email_item_shows_unchecked_for_an_unverified_student_with_no_courses(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => null, 'avatar' => null]);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Verify your email');
        $response->assertSee('Resend');
    }
}
