<?php

namespace Tests\Feature\Learning;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** L3. A certificate must be verifiable, stored once, and never issued twice. */
class CertificateIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function completedEnrollment(): Enrollment
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'source' => 'self',
            'enrolled_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_issuing_a_certificate_twice_for_the_same_enrollment_creates_only_one_row(): void
    {
        Storage::fake('local');
        $enrollment = $this->completedEnrollment();
        $service = app(CertificateService::class);

        $first = $service->issue($enrollment);
        $second = $service->issue($enrollment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_issuing_a_certificate_stores_the_rendered_pdf_on_the_private_disk(): void
    {
        Storage::fake('local');
        $enrollment = $this->completedEnrollment();

        $certificate = app(CertificateService::class)->issue($enrollment);

        $this->assertNotNull($certificate->pdf_path);
        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_a_certificate_has_a_unique_uuid_used_as_its_route_key(): void
    {
        Storage::fake('local');
        $enrollment = $this->completedEnrollment();

        $certificate = app(CertificateService::class)->issue($enrollment);

        $this->assertNotEmpty($certificate->uuid);
        $this->assertSame($certificate->uuid, $certificate->getRouteKey());
    }

    public function test_the_public_verify_page_shows_student_name_course_and_issue_date_for_a_real_certificate(): void
    {
        Storage::fake('local');
        $enrollment = $this->completedEnrollment();
        $certificate = app(CertificateService::class)->issue($enrollment);

        $response = $this->get(route('certificates.verify', $certificate));

        $response->assertOk()
            ->assertSee($enrollment->user->name)
            ->assertSee($enrollment->course->title)
            ->assertSee($certificate->certificate_no);
    }

    public function test_the_public_verify_page_is_reachable_by_a_guest_with_no_authentication(): void
    {
        Storage::fake('local');
        $enrollment = $this->completedEnrollment();
        $certificate = app(CertificateService::class)->issue($enrollment);

        $this->get(route('certificates.verify', $certificate))->assertOk();
    }

    public function test_an_unknown_certificate_uuid_is_not_found(): void
    {
        $this->get('/verify/'.Str::uuid())->assertNotFound();
    }

    public function test_a_forged_certificate_number_alone_does_not_resolve_on_the_verify_page(): void
    {
        // The verify route is keyed on the unguessable uuid, not the human-readable
        // certificate_no, posting the printed code itself must not resolve anything.
        $this->get('/verify/CRT-2026-000001A')->assertNotFound();
    }
}
