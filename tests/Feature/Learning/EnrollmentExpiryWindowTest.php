<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GatewayLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Gateway\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * §4.4/§6.4 — enrollment access-window expiry: stamped at every activation site off the
 * course's optional `access_duration_days`, enforced in EnrollmentPolicy, and
 * extendable/removable from the admin drill-down.
 */
class EnrollmentExpiryWindowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_a_course_with_no_access_duration_grants_lifetime_access(): void
    {
        $course = Course::factory()->create();

        $this->assertNull($course->enrollmentExpiresAt());
    }

    public function test_a_course_with_an_access_duration_computes_a_future_expiry(): void
    {
        $course = Course::factory()->create(['access_duration_days' => 30]);

        $expires = $course->enrollmentExpiresAt();

        $this->assertNotNull($expires);
        $this->assertTrue($expires->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_self_enrolling_free_stamps_the_courses_access_window(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => 0, 'access_duration_days' => 30]);

        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $this->assertNotNull($enrollment->expires_at);
        $this->assertFalse($enrollment->isExpired());
    }

    public function test_self_enrolling_free_in_a_lifetime_access_course_leaves_expires_at_null(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);

        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $this->assertNull($enrollment->expires_at);
    }

    public function test_admin_single_enroll_stamps_the_access_window(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create(['access_duration_days' => 14]);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post(route('admin.enrollments.store', $course), ['user_id' => $student->id]);

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $this->assertNotNull($enrollment->expires_at);
    }

    public function test_bulk_enroll_stamps_the_access_window(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create(['access_duration_days' => 14]);

        $this->actingAs($admin)->post(route('admin.courses.bulk-enroll.store', $course), ['emails' => 'bulk-student@example.com']);

        $enrollment = Enrollment::whereHas('user', fn ($q) => $q->where('email', 'bulk-student@example.com'))->first();
        $this->assertNotNull($enrollment);
        $this->assertNotNull($enrollment->expires_at);
    }

    public function test_paid_checkout_activation_stamps_the_access_window(): void
    {
        $fake = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $fake);

        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX', 'access_duration_days' => 90]);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $invoice = Invoice::find($enrollment->invoice_id);
        $this->post(route('portal.invoice.pay', $invoice));

        $txRef = GatewayLog::where('invoice_id', $invoice->id)->value('tx_ref');
        $fake->succeedNext($txRef, '75.00', 'UGX');
        $this->postJson(route('gateway.webhook'), ['data' => ['id' => $txRef]]);

        $this->assertNotNull($enrollment->fresh()->expires_at);
    }

    public function test_the_admin_can_extend_access_from_the_drilldown(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'expires_at' => now()->subDay(),
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\EnrollmentDrilldown::class, ['enrollment' => $enrollment])
            ->set('extendByDays', 30)
            ->call('extendAccess');

        $fresh = $enrollment->fresh();
        $this->assertFalse($fresh->isExpired());
        // Extending a lapsed window starts counting from "now", not the stale past expiry.
        $this->assertTrue($fresh->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_extending_a_still_active_window_adds_on_top_of_the_current_expiry(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'expires_at' => now()->addDays(10),
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\EnrollmentDrilldown::class, ['enrollment' => $enrollment])
            ->set('extendByDays', 30)
            ->call('extendAccess');

        $this->assertTrue($enrollment->fresh()->expires_at->between(now()->addDays(39), now()->addDays(41)));
    }

    public function test_the_admin_can_grant_lifetime_access_removing_the_expiry(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'expires_at' => now()->addDays(10),
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\EnrollmentDrilldown::class, ['enrollment' => $enrollment])
            ->call('removeExpiry');

        $this->assertNull($enrollment->fresh()->expires_at);
    }

    public function test_the_my_courses_list_shows_an_expired_badge_and_hides_the_resume_link(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($student)->get(route('learn.index'))
            ->assertOk()->assertSee('Access expired')->assertDontSee('Resume');
    }
}
