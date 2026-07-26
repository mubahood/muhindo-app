<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\WeeklyInstructorDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.4 — the weekly instructor digest: only fires when something is actually at risk, reaches every admin. */
class SendWeeklyInstructorDigestTest extends TestCase
{
    use RefreshDatabase;

    private function atRiskEnrollment(): Enrollment
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now()->subDays(30),
            'at_risk_reason' => 'inactive',
        ]);
    }

    public function test_no_email_goes_out_when_nothing_is_at_risk(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->artisan('app:send-weekly-instructor-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_every_admin_receives_the_digest_when_students_are_at_risk(): void
    {
        Notification::fake();
        $this->atRiskEnrollment();
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $student = User::factory()->create(['role' => 'student']);

        $this->artisan('app:send-weekly-instructor-digest');

        Notification::assertSentTo($superAdmin, WeeklyInstructorDigestNotification::class);
        Notification::assertSentTo($admin, WeeklyInstructorDigestNotification::class);
        Notification::assertNotSentTo($student, WeeklyInstructorDigestNotification::class);
    }

    public function test_only_active_enrollments_with_a_reason_are_included(): void
    {
        Notification::fake();
        $flagged = $this->atRiskEnrollment();
        $course = Course::factory()->create();
        $healthyStudent = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $healthyStudent->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->artisan('app:send-weekly-instructor-digest');

        Notification::assertSentTo($admin, function (WeeklyInstructorDigestNotification $notification) use ($flagged) {
            return $notification->atRiskEnrollments->count() === 1
                && $notification->atRiskEnrollments->first()->is($flagged);
        });
    }

    public function test_the_mail_content_lists_the_student_and_reason(): void
    {
        Notification::fake();
        $enrollment = $this->atRiskEnrollment();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->artisan('app:send-weekly-instructor-digest');

        Notification::assertSentTo($admin, function (WeeklyInstructorDigestNotification $notification) use ($enrollment, $admin) {
            $mail = $notification->toMail($admin);

            return str_contains($mail->subject, '1 student at risk this week')
                && collect($mail->introLines)->contains(fn ($line) => str_contains($line, $enrollment->user->name) && str_contains($line, 'Inactive'));
        });
    }
}
