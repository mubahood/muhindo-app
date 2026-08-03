<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PortfolioProject;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The phone action bar.
 *
 * On a phone every one of these pages is a long scroll and the thing you would
 * act on sits at one end of it, so it is pinned to the bottom of the window
 * instead. What matters is that it never says something different from the
 * control it is standing in for — a bar offering a price, a button or a
 * destination the page itself does not is worse than no bar.
 */
class ActionBarTest extends TestCase
{
    use RefreshDatabase;

    /** The bar's markup, so an assertion cannot accidentally match the page. */
    private function bar(string $url): string
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<div class="act-bar">'),
            "{$url} should have exactly one action bar.");

        return substr($html, (int) strpos($html, '<div class="act-bar">'));
    }

    private function paidCourse(): Course
    {
        return Course::factory()->create([
            'is_published' => true, 'price' => 60000, 'currency' => 'UGX',
        ]);
    }

    public function test_the_home_page_pins_the_two_things_the_site_is_for(): void
    {
        $bar = $this->bar(route('home'));

        $this->assertStringContainsString(route('start-a-project'), $bar);
        $this->assertStringContainsString(route('courses.index'), $bar);
    }

    public function test_a_paid_course_shows_its_price_and_sends_you_to_the_coupon_field(): void
    {
        $course = $this->paidCourse();

        $bar = $this->bar(route('courses.show', $course));

        $this->assertStringContainsString('UGX 60,000', $bar);

        // Deliberately an anchor to the buy box, not a POST. The coupon field
        // lives there, and enrolling straight from the bar would silently
        // charge full price to somebody who had typed a code.
        $this->assertStringContainsString('href="#buy"', $bar);
        $this->assertStringNotContainsString(route('courses.enroll', $course), $bar);
    }

    public function test_a_free_course_enrols_straight_from_the_bar(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'student', 'is_student' => true]));

        $bar = $this->bar(route('courses.show', $course));

        $this->assertStringContainsString(route('courses.enroll', $course), $bar);
        $this->assertStringContainsString('Enrol for free', $bar);
    }

    public function test_a_student_already_enrolled_is_offered_the_lessons_instead(): void
    {
        $course = $this->paidCourse();
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);

        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $bar = $this->actingAs($student)->bar(route('courses.show', $course));

        $this->assertStringContainsString('Continue learning', $bar);
        $this->assertStringNotContainsString('#buy', $bar);
    }

    public function test_a_product_can_be_bought_from_the_bar_because_it_has_no_coupon_field(): void
    {
        $product = Product::factory()->create(['price' => 90000, 'currency' => 'UGX']);

        $bar = $this->bar(route('shop.show', $product));

        $this->assertStringContainsString('UGX 90,000', $bar);
        $this->assertStringContainsString(route('cart.add'), $bar);
        $this->assertStringContainsString('buy_now', $bar);
    }

    public function test_a_basket_carries_its_total_and_an_empty_one_carries_nothing(): void
    {
        $product = Product::factory()->create(['price' => 90000, 'currency' => 'UGX']);

        // An empty basket has nothing to act on; a bar there would be a button
        // to nowhere sitting over the "browse the shop" message.
        $html = (string) $this->get(route('cart.show'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<div class="act-bar">', $html);

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        $bar = $this->bar(route('cart.show'));
        $this->assertStringContainsString('UGX 90,000', $bar);
        $this->assertStringContainsString(route('checkout.review'), $bar);
    }

    public function test_a_case_study_keeps_the_question_it_is_read_to_answer_on_screen(): void
    {
        $project = PortfolioProject::create([
            'title' => 'Wildlife Offenders Database', 'slug' => 'wod',
            'description' => 'Enforcement analytics.', 'sort_order' => 1,
        ]);

        $bar = $this->bar(route('portfolio.project', $project));

        // Hire, and a walkthrough of this system. Most of these are internal,
        // so "request a demo" is the honest offer — and it carries the slug so
        // the brief already knows which system.
        $this->assertStringContainsString(route('start-a-project'), $bar);
        $this->assertStringContainsString('demo='.$project->slug, $bar);
    }

    public function test_a_listing_page_has_no_bar_because_it_has_no_single_action(): void
    {
        Course::factory()->create(['is_published' => true]);
        Product::factory()->create();

        foreach ([route('courses.index'), route('shop.index'), route('portfolio.projects.index')] as $url) {
            $this->assertStringNotContainsString('<div class="act-bar">',
                (string) $this->get($url)->assertOk()->getContent(),
                "{$url} should not have an action bar.");
        }
    }
}
