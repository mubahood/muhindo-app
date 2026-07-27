<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L8 — a double-click on Enrol must never 500; at most one enrollment row ever exists. */
class EnrollIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_enroll_twice_in_a_row_never_errors_and_creates_only_one_row(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $student = User::factory()->create(['role' => 'student']);

        $first = $this->actingAs($student)->post(route('courses.enroll', $course));
        $second = $this->actingAs($student)->post(route('courses.enroll', $course));

        $first->assertRedirect(route('learn.course', $course));
        $second->assertRedirect(route('learn.course', $course));
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
    }

    public function test_first_or_create_racing_an_existing_row_does_not_throw_and_stays_a_single_row(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        // Simulate the true race: a row is inserted "underneath" the controller's
        // initial existence check, then firstOrCreate is attempted anyway — it must
        // resolve to the existing row rather than throwing a unique-constraint 500.
        Enrollment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        $enrollment = Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'active', 'source' => 'self', 'enrolled_at' => now()],
        );

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
        $this->assertTrue($enrollment->exists);
    }

    public function test_the_api_enroll_endpoint_is_also_double_click_safe(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/courses/{$course->slug}/enroll")->assertCreated();
        $this->withToken($token)->postJson("/api/v1/courses/{$course->slug}/enroll")->assertOk();

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
    }

    public function test_enroll_route_is_throttled_against_rapid_repeated_requests(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $student = User::factory()->create(['role' => 'student']);

        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->actingAs($student)->post(route('courses.enroll', $course));
        }

        $last->assertStatus(429);
    }
}
