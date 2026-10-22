<?php

namespace Tests\Feature\Learning;

use App\Enums\PaymentMethod;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\BillingService;
use App\Support\Catalog\InstalmentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Buying a course a quarter at a time.
 *
 * The rule is one sentence: you can reach the parts you have paid for and no
 * others. Everything here is a way of getting that wrong, because the failure
 * is either a student locked out of what they bought or a student reading what
 * they did not.
 */
class InstalmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('catalog.instalments', ['parts' => 4, 'label' => 'quarter', 'min_modules' => 4]);

        // Eight modules of two lessons: an even split, so the part boundaries
        // are obvious and a failure points at the rule rather than the shape.
        $this->course = Course::factory()->create([
            'is_published' => true,
            'is_coming_soon' => false,
            'price' => 130000,
            'currency' => 'UGX',
            'allows_instalments' => true,
            'progression' => 'free',
        ]);

        foreach (range(1, 8) as $m) {
            $module = CourseModule::create([
                'course_id' => $this->course->id, 'title' => "Module {$m}", 'sort_order' => $m,
            ]);
            foreach (range(1, 2) as $l) {
                Lesson::create([
                    'course_module_id' => $module->id,
                    'title' => "Lesson {$m}.{$l}",
                    'sort_order' => $l,
                    'is_published' => true,
                ]);
            }
        }

        $this->student = User::factory()->create(['role' => 'student']);
    }

    /** Enrol the student against a real invoice, then pay the given amount of it. */
    private function enrolAndPay(string $amount): Enrollment
    {
        $billing = app(BillingService::class);
        $invoice = $billing->generateCourseInvoice($this->student, $this->course);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'invoice_id' => $invoice->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        if (bccomp($amount, '0', 2) > 0) {
            $billing->recordPayment($invoice, PaymentMethod::Cash, $amount);
        }

        return $enrollment->fresh();
    }

    private function lessonIn(int $moduleNumber): Lesson
    {
        return Lesson::whereHas('module', fn ($q) => $q->where('title', "Module {$moduleNumber}"))
            ->orderBy('sort_order')->firstOrFail();
    }

    /* The split ---------------------------------------------------------- */

    public function test_the_syllabus_divides_into_four_parts_none_of_them_empty(): void
    {
        $summary = $this->course->instalmentPlan()->summary();

        $this->assertCount(4, $summary);

        foreach ($summary as $part) {
            $this->assertNotEmpty($part['modules'], "part {$part['part']} has no modules");
        }
    }

    public function test_a_module_is_never_split_across_two_parts(): void
    {
        $plan = $this->course->instalmentPlan();
        $seen = [];

        foreach ($this->course->modules as $module) {
            $seen[$module->id] = $plan->partOfModule($module->id);
            // Every lesson of a module answers with that module's part.
            foreach ($module->lessons as $lesson) {
                $this->assertSame($seen[$module->id], $plan->partOfLesson($lesson));
            }
        }

        // And the parts only ever go forwards through the syllabus.
        $this->assertSame(array_values($seen), collect($seen)->sort()->values()->all());
    }

    public function test_a_course_with_too_few_modules_is_not_sold_in_parts(): void
    {
        $small = Course::factory()->create(['price' => 130000, 'allows_instalments' => true]);
        CourseModule::create(['course_id' => $small->id, 'title' => 'Only one', 'sort_order' => 1]);

        $this->assertFalse($small->allowsInstalments());
    }

    public function test_a_free_course_is_not_sold_in_parts(): void
    {
        $this->assertTrue($this->course->allowsInstalments());

        // Nothing to divide. The flag is left alone so that putting a price
        // back brings the instalments back with it.
        $this->course->update(['price' => 0]);

        $this->assertFalse($this->course->fresh()->allowsInstalments());
    }

    /* Access ------------------------------------------------------------- */

    public function test_one_instalment_opens_the_first_part_and_nothing_further(): void
    {
        $enrollment = $this->enrolAndPay('32500.00');

        $this->assertSame(1, $enrollment->instalmentPartsUnlocked());
        $this->assertFalse($this->course->isLessonLocked($enrollment, $this->lessonIn(1)));
        $this->assertTrue($this->course->isLessonLocked($enrollment, $this->lessonIn(8)));
    }

    public function test_the_lock_says_it_is_about_money_not_about_progress(): void
    {
        $enrollment = $this->enrolAndPay('32500.00');

        // "Finish the previous lesson" in front of somebody who owes money is
        // a dead end. The reason has to survive as far as the view.
        $this->assertSame('instalment', $this->course->lessonLockReason($enrollment, $this->lessonIn(8)));
    }

    public function test_paying_the_next_instalment_opens_the_next_part(): void
    {
        $enrollment = $this->enrolAndPay('32500.00');
        $locked = collect($this->course->modules)
            ->first(fn ($m) => $this->course->instalmentPlan()->partOfModule($m->id) === 2);
        $lesson = $locked->lessons()->first();

        $this->assertTrue($this->course->isLessonLocked($enrollment, $lesson));

        app(BillingService::class)->recordPayment(
            $enrollment->invoice()->first(), PaymentMethod::Cash, '32500.00',
        );

        $enrollment = $enrollment->fresh();
        $this->assertSame(2, $enrollment->instalmentPartsUnlocked());
        $this->assertFalse($this->course->isLessonLocked($enrollment, $lesson));
    }

    public function test_paying_in_full_opens_everything(): void
    {
        $enrollment = $this->enrolAndPay('130000.00');

        $this->assertSame(4, $enrollment->instalmentPartsUnlocked());

        foreach ($this->course->modules as $module) {
            foreach ($module->lessons as $lesson) {
                $this->assertFalse($this->course->isLessonLocked($enrollment, $lesson));
            }
        }
    }

    public function test_paying_nothing_opens_nothing(): void
    {
        $enrollment = $this->enrolAndPay('0.00');

        $this->assertSame(0, $enrollment->instalmentPartsUnlocked());
        $this->assertTrue($this->course->isLessonLocked($enrollment, $this->lessonIn(1)));
    }

    public function test_an_enrollment_granted_without_an_invoice_is_never_gated(): void
    {
        // A teacher adding a student by hand, a bulk enrolment, a free course.
        // There is no ledger to read and locking them out would be wrong.
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'source' => 'teacher',
            'enrolled_at' => now(),
        ]);

        $this->assertSame(4, $enrollment->instalmentPartsUnlocked());
        $this->assertFalse($this->course->isLessonLocked($enrollment, $this->lessonIn(8)));
    }

    public function test_a_course_not_sold_in_parts_ignores_a_part_payment(): void
    {
        $this->course->update(['allows_instalments' => false]);
        $this->course->refresh();
        $enrollment = $this->enrolAndPay('32500.00');

        // Part payment buys nothing on a course sold whole: otherwise a
        // shilling would be a way around the price.
        $this->assertSame(4, $enrollment->instalmentPartsUnlocked());
        $this->assertFalse($this->course->isLessonLocked($enrollment, $this->lessonIn(8)));
    }

    /* Through the front door --------------------------------------------- */

    public function test_a_student_cannot_open_an_unpaid_lesson_by_its_url(): void
    {
        $this->enrolAndPay('32500.00');

        $this->actingAs($this->student)
            ->get(route('learn.lesson', [$this->course, $this->lessonIn(8)]))
            ->assertForbidden();
    }

    public function test_a_student_can_open_a_lesson_they_have_paid_for(): void
    {
        $this->enrolAndPay('32500.00');

        $this->actingAs($this->student)
            ->get(route('learn.lesson', [$this->course, $this->lessonIn(1)]))
            ->assertOk();
    }

    public function test_an_unpaid_lesson_cannot_be_marked_complete(): void
    {
        $this->enrolAndPay('32500.00');

        $this->actingAs($this->student)
            ->post(route('learn.lesson.complete', [$this->course, $this->lessonIn(8)]))
            ->assertForbidden();
    }

    /* Landing somewhere useful -------------------------------------------- */

    public function test_a_student_with_nothing_paid_lands_on_a_page_that_sells_them_the_first_part(): void
    {
        // The course page redirects to the first lesson. With the course sold
        // in parts that lesson is lockable, so it used to redirect into a 403
        // and leave them with no way back and nothing to press.
        $this->enrolAndPay('0.00');

        $this->actingAs($this->student)
            ->get(route('learn.course', $this->course))
            ->assertOk()
            ->assertSee('Pay UGX 32,500')
            ->assertSee('0 of 4 paid');
    }

    public function test_the_course_page_still_resumes_into_a_lesson_they_can_open(): void
    {
        $this->enrolAndPay('32500.00');

        $this->actingAs($this->student)
            ->get(route('learn.course', $this->course))
            ->assertRedirect(route('learn.lesson', [$this->course, $this->lessonIn(1)]));
    }

    public function test_a_fully_paid_student_is_never_asked_for_money_again(): void
    {
        $this->enrolAndPay('130000.00');

        $this->actingAs($this->student)
            ->get(route('learn.lesson', [$this->course, $this->lessonIn(1)]))
            ->assertOk()
            ->assertDontSee('class="unlock-btn"', false)
            ->assertDontSee('<div class="unlock-bar">', false);
    }

    public function test_the_locked_sidebar_offers_the_price_and_a_button(): void
    {
        $this->enrolAndPay('32500.00');

        $this->actingAs($this->student)
            ->get(route('learn.lesson', [$this->course, $this->lessonIn(1)]))
            ->assertOk()
            ->assertSee('class="unlock-btn"', false)
            ->assertSee('<div class="unlock-bar">', false)
            // The styles live in the layout, not a @push: the sidebar is
            // included after @stack('styles') has already been rendered, so a
            // pushed stylesheet never reaches the page at all.
            ->assertSee('.unlock-btn{', false)
            ->assertSee('1 of 4 paid');
    }

    /* The first payment is what opens the course ------------------------- */

    public function test_the_first_instalment_activates_a_pending_enrollment(): void
    {
        $billing = app(BillingService::class);
        $invoice = $billing->generateCourseInvoice($this->student, $this->course);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'invoice_id' => $invoice->id,
            'status' => 'pending',
            'source' => 'self',
        ]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '32500.00');

        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_a_part_payment_does_not_activate_a_course_sold_whole(): void
    {
        $this->course->update(['allows_instalments' => false]);

        $billing = app(BillingService::class);
        $invoice = $billing->generateCourseInvoice($this->student, $this->course);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'invoice_id' => $invoice->id,
            'status' => 'pending',
            'source' => 'self',
        ]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '32500.00');

        $this->assertSame('pending', $enrollment->fresh()->status);
    }

    /* Quizzes and assignments, the hole that was open ------------------- */

    public function test_a_quiz_in_an_unpaid_part_cannot_be_opened(): void
    {
        $this->enrolAndPay('32500.00');
        $quiz = \App\Models\Quiz::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lessonIn(8)->id,
            'title' => 'Final questions',
            'is_published' => true,
        ]);

        // Quizzes hang off the course, not the lesson, so they never passed
        // through the lesson gate: every question was one URL away.
        $this->actingAs($this->student)
            ->get(route('learn.quiz.show', [$this->course, $quiz]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_a_quiz_in_a_paid_part_opens_normally(): void
    {
        $this->enrolAndPay('32500.00');
        $quiz = \App\Models\Quiz::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lessonIn(1)->id,
            'title' => 'First questions',
            'is_published' => true,
        ]);

        $this->actingAs($this->student)
            ->get(route('learn.quiz.show', [$this->course, $quiz]))
            ->assertOk();
    }

    public function test_a_course_wide_quiz_waits_for_the_whole_price(): void
    {
        $this->enrolAndPay('97500.00');
        $quiz = \App\Models\Quiz::create([
            'course_id' => $this->course->id,
            'lesson_id' => null,
            'title' => 'Final exam',
            'is_published' => true,
        ]);

        // Belongs to no lesson, so it belongs to the end of the course.
        $this->actingAs($this->student)
            ->get(route('learn.quiz.show', [$this->course, $quiz]))
            ->assertRedirect();
    }

    public function test_an_assignment_in_an_unpaid_part_cannot_be_opened(): void
    {
        $this->enrolAndPay('32500.00');
        $assignment = \App\Models\Assignment::create([
            'course_id' => $this->course->id,
            'lesson_id' => $this->lessonIn(8)->id,
            'title' => 'Final build',
            'is_published' => true,
        ]);

        $this->actingAs($this->student)
            ->get(route('learn.assignment.show', [$this->course, $assignment]))
            ->assertRedirect();
    }

    public function test_the_payment_screen_offers_the_next_part(): void
    {
        $enrollment = $this->enrolAndPay('0.00');
        $invoice = $enrollment->invoice()->first();

        $this->actingAs($this->student)
            ->get(route('payments.show', $invoice))
            ->assertOk()
            ->assertSee('Quarter 1')
            ->assertSee('32,500');
    }

    public function test_the_invoice_context_tracks_what_is_left(): void
    {
        $enrollment = $this->enrolAndPay('65000.00');
        $context = InstalmentPlan::contextFor($enrollment->invoice()->first()->load('items'));

        $this->assertSame(2, $context['paid']);
        $this->assertSame('Quarter 3', $context['next_name']);
        $this->assertSame('32500.00', $context['next_amount']);
    }
}
