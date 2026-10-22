<?php

namespace Tests\Feature\Catalog;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A course that is not open for enrolment is not shown to the public anywhere.
 *
 * The shopfront was twenty "Coming soon" cards around two real ones, which
 * reads as an empty shop and costs the two finished courses the impression they
 * paid for. So "published" stopped being enough to appear: a course has to be
 * buyable. One scope, Course::publiclyVisible(), decides it, and the point of
 * this class is that every public surface actually asks it, because the one
 * that forgets is the leak.
 *
 * ComingSoonCoursesTest covers the other setting, where the waitlist pages come
 * back.
 */
class HiddenUnopenedCoursesTest extends TestCase
{
    use RefreshDatabase;

    private function open(string $title = 'Bootstrap, end to end'): Course
    {
        return Course::factory()->create([
            'title' => $title,
            'is_published' => true,
            'is_coming_soon' => false,
            'price' => 130000,
            'category' => 'Frontend development',
        ]);
    }

    private function unopened(string $title = 'Flutter, some day'): Course
    {
        return Course::factory()->create([
            'title' => $title,
            'is_published' => true,
            'is_coming_soon' => true,
            'price' => 140000,
            'category' => 'Mobile development',
        ]);
    }

    /* The catalogue ----------------------------------------------------- */

    public function test_the_catalogue_lists_only_what_can_be_bought(): void
    {
        $open = $this->open();
        $hidden = $this->unopened();

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee($open->title)
            ->assertDontSee($hidden->title);
    }

    public function test_the_catalogue_count_does_not_include_hidden_courses(): void
    {
        $this->open();
        $this->unopened();
        $this->unopened('Another one, some day');

        // "22 courses" over a page showing two was the visible symptom.
        $this->get(route('courses.index'))->assertOk()->assertSee('1 course');
    }

    public function test_a_hidden_course_contributes_no_categories_to_the_filter(): void
    {
        $this->open();
        $this->unopened();

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Frontend development')
            ->assertDontSee('Mobile development');
    }

    /* The sales page ---------------------------------------------------- */

    public function test_a_hidden_courses_sales_page_is_gone(): void
    {
        $this->get(route('courses.show', $this->unopened()))->assertNotFound();
    }

    public function test_an_open_courses_sales_page_is_fine(): void
    {
        $this->get(route('courses.show', $this->open()))->assertOk();
    }

    public function test_the_owner_can_still_read_a_hidden_courses_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('courses.show', $this->unopened()))->assertOk();
    }

    public function test_a_student_already_enrolled_never_meets_a_404(): void
    {
        // Somebody paid for this before it was hidden. Whatever the catalogue
        // advertises this month, their course does not disappear.
        $course = $this->unopened();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student)->get(route('courses.show', $course))->assertOk();
    }

    public function test_a_stranger_still_cannot_read_it(): void
    {
        $course = $this->unopened();
        $other = User::factory()->create(['role' => 'student']);

        $this->actingAs($other)->get(route('courses.show', $course))->assertNotFound();
    }

    /* Everywhere else --------------------------------------------------- */

    public function test_the_sitemap_offers_no_hidden_course(): void
    {
        $open = $this->open();
        $hidden = $this->unopened();

        $response = $this->get(route('sitemap'))->assertOk();

        $this->assertStringContainsString(route('courses.show', $open), $response->getContent());
        $this->assertStringNotContainsString(route('courses.show', $hidden), $response->getContent());
    }

    public function test_the_public_api_offers_no_hidden_course(): void
    {
        $open = $this->open();
        $hidden = $this->unopened();

        $response = $this->getJson('/api/v1/courses')->assertOk();

        $this->assertStringContainsString($open->title, $response->getContent());
        $this->assertStringNotContainsString($hidden->title, $response->getContent());
    }

    public function test_a_hidden_course_cannot_be_put_in_a_cart(): void
    {
        $hidden = $this->unopened();

        $this->from(route('courses.index'))
            ->post(route('cart.add'), ['type' => 'course', 'id' => $hidden->id])
            ->assertRedirect(route('courses.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, app(\App\Services\Shop\Cart::class)->count());
    }

    /* The switch -------------------------------------------------------- */

    public function test_turning_the_setting_on_brings_the_waitlist_pages_back(): void
    {
        config()->set('catalog.show_unopened', true);
        $hidden = $this->unopened();

        $this->get(route('courses.index'))->assertOk()->assertSee($hidden->title);
        $this->get(route('courses.show', $hidden))->assertOk();
    }
}
