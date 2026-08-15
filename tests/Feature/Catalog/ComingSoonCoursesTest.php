<?php

namespace Tests\Feature\Catalog;

use App\Models\Course;
use App\Models\CourseNotifyRequest;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\User;
use App\Services\Shop\Cart;
use App\Support\Spam\FormShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A course behind "coming soon" is visible, indexed and collecting names, and
 * cannot be bought by any route.
 *
 * The catalogue is the whole shopfront, so the failure that matters is not the
 * button still showing: it is money changing hands for something that does not
 * open yet. That is checked at all three points it could happen.
 */
class ComingSoonCoursesTest extends TestCase
{
    use RefreshDatabase;

    private function course(bool $comingSoon = true, float $price = 150000): Course
    {
        return Course::factory()->create([
            'title' => 'Laravel Essentials',
            'is_published' => true,
            'is_coming_soon' => $comingSoon,
            'price' => $price,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $this->shielded(array_merge([
            'name' => 'Aisha Nakalema',
            'email' => 'aisha@example.com',
            'whatsapp' => '0783204665',
        ], $overrides));
    }

    /* The page still works, and still sells ------------------------------- */

    public function test_the_sales_page_stays_public(): void
    {
        $course = $this->course();

        // Hiding it would waste the only audience the page has. It is the
        // advertisement; the waitlist is what it sells.
        $this->get(route('courses.show', $course))->assertOk()->assertSee('Coming soon');
    }

    public function test_it_offers_no_way_to_pay_while_it_is_closed(): void
    {
        $course = $this->course(price: 150000);

        // Four payment logos under a form that only collects a name is an
        // offer the page cannot keep.
        $this->get(route('courses.show', $course))->assertOk()
            ->assertDontSee('Secure payment via Flutterwave')
            ->assertDontSee('MTN MoMo')
            ->assertSee('Notify me when it opens');
    }

    public function test_the_catalogue_marks_it_rather_than_pricing_it(): void
    {
        $this->course(true, 150000);

        $response = $this->get(route('courses.index'))->assertOk();

        $response->assertSee('Coming soon');
        $response->assertDontSee('150,000');
    }

    public function test_an_open_course_still_shows_its_price(): void
    {
        $this->course(comingSoon: false, price: 150000);

        $this->get(route('courses.index'))->assertOk()->assertSee('150,000');
    }

    /**
     * Markup that contradicts the page is a manual-action offence, not a
     * cosmetic slip: InStock on a course whose every buying control has been
     * replaced by a waitlist form tells a search engine something the page
     * does not.
     */
    public function test_the_structured_data_agrees_with_the_page(): void
    {
        $course = $this->course();

        $this->get(route('courses.show', $course))->assertOk()
            ->assertSee('schema.org/PreOrder', false)
            ->assertDontSee('schema.org/InStock', false);
    }

    public function test_an_open_course_is_marked_in_stock(): void
    {
        $course = $this->course(comingSoon: false);

        $this->get(route('courses.show', $course))->assertOk()
            ->assertSee('schema.org/InStock', false);
    }

    /* The three points money could move -------------------------------- */

    public function test_it_cannot_be_added_to_a_basket(): void
    {
        $course = $this->course();

        $this->post(route('cart.add'), ['type' => 'course', 'id' => $course->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    public function test_a_basket_filled_before_it_closed_quietly_drops_it(): void
    {
        $course = $this->course(comingSoon: false);
        $this->post(route('cart.add'), ['type' => 'course', 'id' => $course->id]);
        $this->assertCount(1, app(Cart::class)->contents());

        // Closed again after it was added, exactly as a withdrawn product is.
        $course->update(['is_coming_soon' => true]);

        $this->assertCount(0, app(Cart::class)->contents());
    }

    public function test_enrolling_is_refused_even_when_posted_directly(): void
    {
        $course = $this->course(price: 0);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('courses.enroll', $course))
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHas('error');

        $this->assertSame(0, Enrollment::count());
    }

    public function test_checkout_is_refused(): void
    {
        $course = $this->course();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('courses.checkout', $course))
            ->assertRedirect(route('courses.show', $course));
    }

    /** Closing the catalogue must never take a course from somebody who paid. */
    public function test_an_existing_student_keeps_their_access(): void
    {
        $course = $this->course();
        $user = User::factory()->create();
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->get(route('learn.course', $course))->assertOk();
        $this->actingAs($user)->get(route('courses.show', $course))->assertOk()->assertSee('Continue learning');
    }

    public function test_a_product_is_unaffected(): void
    {
        // The block is about courses. The shop must keep working.
        $product = Product::factory()->create(['is_published' => true, 'external_url' => 'https://example.com/demo']);

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id])
            ->assertSessionMissing('error');
    }

    /* The waitlist -------------------------------------------------------- */

    public function test_a_stranger_can_join_the_waitlist(): void
    {
        $course = $this->course();

        $this->post(route('courses.notify', $course), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = CourseNotifyRequest::sole();
        $this->assertSame('Aisha Nakalema', $row->name);
        $this->assertSame('aisha@example.com', $row->email);
        $this->assertSame($course->id, $row->course_id);
        $this->assertNull($row->notified_at);
        $this->assertNull($row->user_id, 'no account is required, that is the point');
    }

    /**
     * 0783204665, +256 783 204 665 and 256783204665 are one person typing one
     * number three ways. A list that treats them as three cannot be messaged.
     */
    public function test_a_number_is_stored_in_the_shape_whatsapp_wants(): void
    {
        $this->assertSame('256783204665', CourseNotifyRequest::normaliseWhatsApp('0783204665'));
        $this->assertSame('256783204665', CourseNotifyRequest::normaliseWhatsApp('+256 783 204 665'));
        $this->assertSame('256783204665', CourseNotifyRequest::normaliseWhatsApp('256-783-204-665'));
        $this->assertSame('256783204665', CourseNotifyRequest::normaliseWhatsApp('783204665'));
        // A foreign number keeps its own country code.
        $this->assertSame('447700900123', CourseNotifyRequest::normaliseWhatsApp('+44 7700 900123'));
    }

    public function test_asking_twice_updates_rather_than_duplicating(): void
    {
        $course = $this->course();

        $this->post(route('courses.notify', $course), $this->payload());
        $this->post(route('courses.notify', $course), $this->payload(['name' => 'Aisha N.', 'whatsapp' => '0700000000']));

        // A double-tap is not more interest, and it would inflate the only
        // number on this list anybody would act on.
        $this->assertSame(1, CourseNotifyRequest::count());
        $this->assertSame('Aisha N.', CourseNotifyRequest::sole()->name);
        $this->assertSame('256700000000', CourseNotifyRequest::sole()->whatsapp);
    }

    public function test_the_same_person_can_wait_for_two_courses(): void
    {
        $this->post(route('courses.notify', $this->course()), $this->payload());
        $this->post(route('courses.notify', $this->course()), $this->payload());

        $this->assertSame(2, CourseNotifyRequest::count());
    }

    public function test_a_signed_in_visitor_is_linked_to_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('courses.notify', $this->course()), $this->payload());

        $this->assertSame($user->id, CourseNotifyRequest::sole()->user_id);
    }

    public function test_bad_details_are_refused(): void
    {
        $course = $this->course();

        $this->post(route('courses.notify', $course), $this->payload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->post(route('courses.notify', $course), $this->payload(['whatsapp' => 'call me']))
            ->assertSessionHasErrors('whatsapp');

        $this->post(route('courses.notify', $course), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, CourseNotifyRequest::count());
    }

    public function test_a_number_that_is_too_short_is_refused(): void
    {
        $this->post(route('courses.notify', $this->course()), $this->payload(['whatsapp' => '1234567']))
            ->assertSessionHasErrors('whatsapp');

        $this->assertSame(0, CourseNotifyRequest::count());
    }

    public function test_the_form_carries_the_spam_shield(): void
    {
        $course = $this->course();

        $this->post(route('courses.notify', $course), $this->payload([FormShield::HONEYPOT => 'http://spam.example']))
            ->assertRedirect()->assertSessionHas('success');

        // Answered as success so a bot learns nothing, and written nowhere.
        $this->assertSame(0, CourseNotifyRequest::count());
    }

    public function test_an_instant_submission_is_refused(): void
    {
        $payload = $this->payload();
        $payload[FormShield::TIMESTAMP] = \Illuminate\Support\Facades\Crypt::encryptString((string) now()->getTimestamp());

        $this->post(route('courses.notify', $this->course()), $payload)
            ->assertSessionHasErrors(FormShield::TIMESTAMP);

        $this->assertSame(0, CourseNotifyRequest::count());
    }

    /* Admin --------------------------------------------------------------- */

    public function test_an_admin_sees_the_waitlist(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        $this->post(route('courses.notify', $this->course()), $this->payload());

        $this->actingAs($admin)->get(route('admin.waitlist'))->assertOk()
            ->assertSee('Aisha Nakalema')
            ->assertSee('256783204665');
    }

    public function test_a_student_cannot_see_the_waitlist(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $student->syncSpatieRole();

        $this->actingAs($student)->get(route('admin.waitlist'))->assertRedirect(route('login'));
    }

    /**
     * The schema default, checked against the schema rather than the factory.
     *
     * The factory deliberately overrides this to false, because a factory
     * builds an ordinary course for tests that mean "a student buys a course".
     * The default that matters is the one a real insert gets: a course arriving
     * from an import or a hand-written row must not be on sale before anybody
     * has looked at it.
     */
    public function test_a_course_inserted_without_the_column_is_coming_soon(): void
    {
        $id = \Illuminate\Support\Facades\DB::table('courses')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'title' => 'Imported overnight',
            'slug' => 'imported-'.Str::random(6),
            'price' => 100000,
            'currency' => 'UGX',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $course = Course::findOrFail($id);

        $this->assertTrue($course->isComingSoon());
        $this->assertFalse($course->isSellable());
    }
}
