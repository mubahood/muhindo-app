<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Support\Catalog\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which currency a visitor is shown, and how the catalogue is paged.
 *
 * The rule the currency logic exists to protect: an explicit choice always
 * beats a guess about where somebody is sitting. A Ugandan on a VPN and a
 * Kenyan on holiday in Kampala both get to decide for themselves.
 */
class CatalogCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $overrides = []): Course
    {
        return Course::factory()->create($overrides + [
            'is_published' => true,
            'price' => '140000.00',
            'price_usd' => '35.00',
            'currency' => 'UGX',
        ]);
    }

    public function test_shillings_are_the_default_when_we_cannot_tell(): void
    {
        $this->course();

        $this->get(route('courses.index'))->assertOk()
            ->assertSee('UGX 140,000')->assertDontSee('$35');
    }

    public function test_a_visitor_outside_uganda_is_shown_dollars(): void
    {
        $this->course();

        $this->withHeader('CF-IPCountry', 'US')
            ->get(route('courses.index'))->assertOk()
            ->assertSee('$35')->assertDontSee('UGX 140,000');
    }

    public function test_a_visitor_inside_uganda_is_shown_shillings(): void
    {
        $this->course();

        $this->withHeader('CF-IPCountry', 'UG')
            ->get(route('courses.index'))->assertOk()->assertSee('UGX 140,000');
    }

    public function test_an_anonymised_country_is_not_treated_as_a_country(): void
    {
        $this->course();

        // Cloudflare sends XX for anonymised traffic and T1 for Tor. Reading
        // either as a country would put those visitors on the wrong currency.
        foreach (['XX', 'T1'] as $header) {
            $this->withHeader('CF-IPCountry', $header)
                ->get(route('courses.index'))->assertOk()->assertSee('UGX 140,000');
        }
    }

    public function test_choosing_a_currency_beats_the_country_guess(): void
    {
        $this->course();

        $this->post(route('currency.switch'), ['currency' => 'USD'])->assertRedirect();

        // Still says US-dollars even though the edge says Uganda.
        $this->withHeader('CF-IPCountry', 'UG')
            ->get(route('courses.index'))->assertOk()->assertSee('$35');
    }

    public function test_an_unknown_currency_is_refused(): void
    {
        $this->course();
        $this->post(route('currency.switch'), ['currency' => 'BTC']);

        $this->get(route('courses.index'))->assertOk()->assertSee('UGX 140,000');
    }

    public function test_a_free_course_reads_free_in_either_currency(): void
    {
        $this->course(['price' => '0.00', 'price_usd' => '0.00', 'title' => 'The free one']);

        $this->get(route('courses.index'))->assertOk()->assertSee('Free');
        $this->withHeader('CF-IPCountry', 'US')
            ->get(route('courses.index'))->assertOk()->assertSee('Free');
    }

    public function test_a_missing_usd_price_never_reads_as_free(): void
    {
        // A missing number is not a claim that something costs nothing.
        $course = $this->course(['price_usd' => null]);

        $this->assertFalse(Currency::isFreeIn($course, 'USD'));
        $this->assertSame('140000.00', Currency::priceOf($course, 'USD'));
    }

    // Paging

    public function test_the_catalogue_shows_six_at_a_time_and_says_where_you_are(): void
    {
        Course::factory()->count(14)->create(['is_published' => true]);

        $page1 = $this->get(route('courses.index'))->assertOk();
        $this->assertCount(6, $page1->viewData('courses'));

        // Named, not just numbered: a row of bare arrows never tells somebody
        // how much catalogue is left.
        $page1->assertSee('of <b>14</b> courses', false)
            ->assertSee('page 1 of 3');

        $this->assertCount(2, $this->get(route('courses.index', ['page' => 3]))->viewData('courses'));
    }

    public function test_the_pager_offers_real_numbered_pages(): void
    {
        Course::factory()->count(14)->create(['is_published' => true]);

        // The app's global paginator is Previous/Next only, which is fine for
        // a feed and wrong for a syllabus somebody wants to jump around.
        $this->get(route('courses.index'))->assertOk()
            ->assertSee('aria-label="Page 2"', false)
            ->assertSee('aria-current="page"', false);
    }
}
