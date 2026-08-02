<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a signed-in student can see and reach.
 *
 * A student's whole menu used to be two groups. Their profile, their settings
 * and their notifications were only in the top-right dropdown and the bell —
 * fine once you know they are there, invisible if you do not.
 */
class StudentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'is_student' => true]);
    }

    public function test_the_sidebar_reaches_every_page_a_student_owns(): void
    {
        $student = $this->student();

        $html = (string) $this->actingAs($student)->get(route('learn.index'))->assertOk()->getContent();

        foreach ([
            'dashboard' => route('dashboard'),
            'my courses' => route('learn.index'),
            'browse courses' => route('courses.index'),
            'my orders' => route('payments.index'),
            'my downloads' => route('shop.downloads'),
            'profile & settings' => route('account.edit'),
            'notifications' => route('notifications.index'),
        ] as $label => $url) {
            $this->assertStringContainsString($url, $html, "the sidebar has no link to {$label}");
        }
    }

    public function test_every_one_of_those_links_actually_opens(): void
    {
        $student = $this->student();

        // A menu entry that 404s or 403s is worse than no menu entry.
        foreach ([
            route('dashboard'),
            route('learn.index'),
            route('courses.index'),
            route('payments.index'),
            route('shop.downloads'),
            route('account.edit'),
            route('notifications.index'),
        ] as $url) {
            $this->actingAs($student)->get($url)->assertOk();
        }
    }

    public function test_the_profile_name_in_the_top_bar_is_not_invisible(): void
    {
        /* The trigger is a <button>, which does not inherit colour. With none
           declared it fell through to the UA's `buttontext` — white under a
           dark system appearance, on a near-white bar. Measured in a browser
           as rgb(255,255,255) on rgba(245,247,250,.9). */
        $css = (string) file_get_contents(public_path('css/td-admin.css'));

        $this->assertMatchesRegularExpression(
            '/\.tb-user-trigger\{[^}]*color:\s*var\(--tx\)/s',
            $css,
            'the user menu trigger must state its own text colour'
        );
    }

    // ── The pending-course card ─────────────────────────────────────────────

    /** @return array{0:User,1:Course,2:Invoice} */
    private function pendingPurchase(): array
    {
        $student = $this->student();
        $course = Course::factory()->create([
            'is_published' => true, 'price' => '130000.00', 'currency' => 'UGX',
        ]);

        $this->actingAs($student)->post(route('courses.enroll', $course));

        return [$student, $course, Invoice::firstOrFail()];
    }

    public function test_a_pending_course_says_what_is_owed_and_leads_to_paying_it(): void
    {
        [$student, , $invoice] = $this->pendingPurchase();

        // "Payment pending" never said how much, and pointed at the old
        // course-only checkout instead of the one payment screen.
        $this->actingAs($student)->get(route('learn.index'))->assertOk()
            ->assertSee('UGX 130,000.00')
            ->assertSee(route('payments.show', $invoice), false);
    }

    public function test_a_direct_payment_arrangement_is_named_on_the_card(): void
    {
        [$student, , $invoice] = $this->pendingPurchase();

        $this->actingAs($student)->post(route('payments.direct', $invoice));

        // Somebody who arranged to pay Muhindo and somebody who has done
        // nothing at all were shown exactly the same three words.
        $this->actingAs($student)->get(route('learn.index'))->assertOk()
            ->assertSee('Awaiting confirmation')
            ->assertSee('paying Muhindo directly', false);
    }

    public function test_the_card_does_not_query_an_invoice_per_row(): void
    {
        $student = $this->student();

        for ($i = 0; $i < 4; $i++) {
            $course = Course::factory()->create(['is_published' => true, 'price' => '50000.00']);
            $this->actingAs($student)->post(route('courses.enroll', $course));
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($student)->get(route('learn.index'))->assertOk();
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $invoiceQueries = collect($queries)
            ->filter(fn ($q) => str_contains($q['query'], 'from "invoices"') || str_contains($q['query'], 'from `invoices`'))
            ->count();

        // One eager load, not one per enrollment.
        $this->assertLessThanOrEqual(1, $invoiceQueries,
            "the invoice should be eager loaded; saw {$invoiceQueries} invoice queries for 4 courses");
    }

    public function test_a_free_active_course_shows_no_payment_wording(): void
    {
        $student = $this->student();
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $this->assertSame('active', Enrollment::firstOrFail()->status);

        $this->actingAs($student)->get(route('learn.index'))->assertOk()
            ->assertDontSee('Payment pending')
            ->assertDontSee('Awaiting confirmation');
    }
}
