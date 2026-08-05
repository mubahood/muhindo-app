<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Learning\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The certificate, inside the course.
 *
 * It is reachable whether or not it has been earned, which is the point: a
 * student who has not finished needs somewhere that says what is left far more
 * than a finished student needs somewhere to download from. Hiding the page
 * until it is earned answers "where is my certificate?" with silence.
 */
class CertificatePageTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private CourseModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->create(['is_published' => true, 'price' => 0, 'progression' => 'free']);
        $this->module = CourseModule::create(['course_id' => $this->course->id, 'title' => 'Module 1', 'sort_order' => 1]);
    }

    private function lesson(string $title, int $order): Lesson
    {
        return Lesson::create([
            'course_module_id' => $this->module->id,
            'title' => $title,
            'content' => 'Read this.',
            'sort_order' => $order,
            'is_published' => true,
        ]);
    }

    private function enrol(): Enrollment
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    private function complete(Enrollment $enrollment, Lesson ...$lessons): void
    {
        foreach ($lessons as $lesson) {
            $enrollment->progressRecords()->create([
                'lesson_id' => $lesson->id,
                'started_at' => now()->subHour(),
                'completed_at' => now(),
            ]);
        }
    }

    // The tab

    public function test_the_course_menu_offers_the_certificate_instead_of_news(): void
    {
        $enrollment = $this->enrol();
        $lesson = $this->lesson('A topic', 1);

        $this->actingAs($enrollment->user)
            ->get(route('learn.lesson', [$this->course, $lesson]))->assertOk()
            ->assertSee(route('learn.certificate', $this->course), false)
            ->assertDontSee('>News<', false);
    }

    // Before it is earned

    public function test_an_unfinished_student_is_told_what_is_left(): void
    {
        $enrollment = $this->enrol();
        $done = $this->lesson('Finished topic', 1);
        $this->lesson('Outstanding topic', 2);
        $this->complete($enrollment, $done);

        $this->actingAs($enrollment->user)
            ->get(route('learn.certificate', $this->course))->assertOk()
            ->assertSee('Not yet')
            ->assertSee('Outstanding topic')
            ->assertSee('50% complete')
            // Every lesson appears in the sidebar curriculum regardless, so
            // the count is what proves the finished one is not being asked for.
            ->assertSee('1 topic to go');
    }

    public function test_an_unfinished_student_is_not_told_they_have_earned_it(): void
    {
        $enrollment = $this->enrol();
        $this->lesson('A topic', 1);

        $this->actingAs($enrollment->user)
            ->get(route('learn.certificate', $this->course))->assertOk()
            ->assertDontSee('Download the PDF')
            ->assertDontSee('Certificate no.');
    }

    public function test_a_graded_quiz_requirement_is_only_mentioned_when_the_course_has_one(): void
    {
        $enrollment = $this->enrol();
        $this->lesson('A topic', 1);

        // No graded quizzes: a student should not be shown a rule that does
        // not apply to their course.
        $this->actingAs($enrollment->user)
            ->get(route('learn.certificate', $this->course))->assertOk()
            ->assertDontSee('Pass the graded');

        Quiz::create([
            'course_id' => $this->course->id,
            'title' => 'Final assessment',
            'is_published' => true,
            'counts_toward_certificate' => true,
            'pass_percent' => 60,
        ]);

        $this->actingAs($enrollment->user)
            ->get(route('learn.certificate', $this->course))->assertOk()
            ->assertSee('Pass the graded')
            ->assertSee('Final assessment');
    }

    // Once it is earned

    public function test_a_finished_student_gets_their_certificate_with_a_way_to_prove_it(): void
    {
        $enrollment = $this->enrol();
        $lesson = $this->lesson('The only topic', 1);
        $this->complete($enrollment, $lesson);

        $certificate = app(CertificateService::class)->issue($enrollment);

        $this->actingAs($enrollment->user)
            ->get(route('learn.certificate', $this->course))->assertOk()
            ->assertSee($certificate->certificate_no)
            ->assertSee($enrollment->user->name)
            ->assertSee(route('learn.certificate.download', $certificate), false)
            // The public check is the thing an employer uses, so it is offered
            // to be copied rather than only printed on the PDF.
            ->assertSee(route('certificates.verify', $certificate), false)
            ->assertDontSee('Not yet');
    }

    public function test_the_download_still_works_under_its_new_name(): void
    {
        $enrollment = $this->enrol();
        $lesson = $this->lesson('The only topic', 1);
        $this->complete($enrollment, $lesson);
        $certificate = app(CertificateService::class)->issue($enrollment);

        // learn.certificate now names the page; the stream moved to
        // learn.certificate.download and every old caller had to follow.
        $this->actingAs($enrollment->user)
            ->get(route('learn.certificate.download', $certificate))->assertOk();
    }

    public function test_the_report_agrees_with_what_issuance_would_decide(): void
    {
        $enrollment = $this->enrol();
        $lesson = $this->lesson('The only topic', 1);

        $service = app(CertificateService::class);

        // A page that says "you are finished" while issuance says otherwise is
        // worse than no page, so both read the same two checks.
        $this->assertFalse($service->progressReport($enrollment)['eligible']);
        $this->assertNull($service->issueIfEligible($enrollment));

        $this->complete($enrollment, $lesson);

        $this->assertTrue($service->progressReport($enrollment->fresh())['eligible']);
        $this->assertNotNull($service->issueIfEligible($enrollment->fresh()));
    }

    // Who may see it

    public function test_somebody_not_enrolled_cannot_open_it(): void
    {
        $this->lesson('A topic', 1);
        $stranger = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($stranger)
            ->get(route('learn.certificate', $this->course))->assertNotFound();
    }

    public function test_one_student_cannot_download_anothers_certificate(): void
    {
        $enrollment = $this->enrol();
        $lesson = $this->lesson('The only topic', 1);
        $this->complete($enrollment, $lesson);
        $certificate = app(CertificateService::class)->issue($enrollment);

        $other = $this->enrol();

        $this->actingAs($other->user)
            ->get(route('learn.certificate.download', $certificate))->assertForbidden();
    }
}
