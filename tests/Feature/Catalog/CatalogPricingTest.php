<?php

namespace Tests\Feature\Catalog;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * config/catalog.php is the one place a price or an open course is decided.
 *
 * The enrolment half is applied in both directions on purpose, so these tests
 * pin both: a course named in 'open' is opened, and a course that is not is
 * closed again. The failure being guarded against is a course left taking
 * money for lessons that are not recorded, which no amount of care on a
 * production database reliably prevents.
 */
class CatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    private function course(int $number, array $attributes = []): Course
    {
        return Course::factory()->create(array_merge([
            'course_number' => $number,
            'tier' => 1,
            'is_published' => true,
            'is_coming_soon' => true,
            'price' => 60000,
        ], $attributes));
    }

    private function catalogue(array $open, array $overrides = []): void
    {
        config()->set('catalog.tiers', [
            1 => ['label' => 'Foundations', 'price' => 60_000, 'price_usd' => 15],
        ]);
        config()->set('catalog.overrides', $overrides);
        config()->set('catalog.open', $open);
    }

    public function test_a_course_named_in_the_open_list_is_opened_for_enrolment(): void
    {
        $course = $this->course(2);
        $this->catalogue(open: [2]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $this->assertFalse($course->refresh()->is_coming_soon);
        $this->assertTrue($course->isSellable());
    }

    public function test_a_course_left_out_of_the_open_list_is_closed_again(): void
    {
        $course = $this->course(7, ['is_coming_soon' => false]);
        $this->catalogue(open: [2]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $this->assertTrue($course->refresh()->is_coming_soon);
        $this->assertFalse($course->isSellable());
    }

    public function test_a_course_with_no_tier_is_still_held_shut(): void
    {
        // The tier check used to skip these rows before anything was written,
        // so a course with no price band could never be closed.
        $course = $this->course(99, ['tier' => null, 'is_coming_soon' => false]);
        $this->catalogue(open: [2]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $this->assertTrue($course->refresh()->is_coming_soon);
    }

    public function test_a_per_course_override_beats_its_tier_price(): void
    {
        $course = $this->course(2);
        $this->catalogue(open: [2], overrides: [2 => ['price' => 130_000, 'price_usd' => 33]]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $course->refresh();
        $this->assertSame('130000.00', (string) $course->price);
        $this->assertSame('33.00', (string) $course->price_usd);
        $this->assertSame('UGX', $course->currency);
    }

    public function test_an_open_course_is_published_so_it_can_be_seen(): void
    {
        $course = $this->course(2, ['is_published' => false]);
        $this->catalogue(open: [2]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $this->assertTrue($course->refresh()->is_published);
    }

    public function test_a_closed_draft_is_left_as_a_draft(): void
    {
        // A pricing run used to publish every course in a tier, so changing
        // one price put unfinished courses in the catalogue as a side effect.
        $course = $this->course(22, ['is_published' => false]);
        $this->catalogue(open: [2]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $this->assertFalse($course->refresh()->is_published);
    }

    public function test_a_published_course_is_never_unpublished(): void
    {
        $course = $this->course(7, ['is_published' => true]);
        $this->catalogue(open: [2]);

        $this->artisan('courses:apply-pricing')->assertSuccessful();

        $this->assertTrue($course->refresh()->is_published);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $course = $this->course(2);
        $this->catalogue(open: [2], overrides: [2 => ['price' => 130_000, 'price_usd' => 33]]);

        $this->artisan('courses:apply-pricing', ['--dry-run' => true])->assertSuccessful();

        $course->refresh();
        $this->assertTrue($course->is_coming_soon);
        $this->assertSame('60000.00', (string) $course->price);
    }
}
