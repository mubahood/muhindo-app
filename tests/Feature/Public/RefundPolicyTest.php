<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The refund policy.
 *
 * Worth testing because of who reads it. A payment provider reviewing the
 * account opens this URL, checks that the trading entity, its registration
 * number and a working contact route are all on it, and that the site does not
 * contradict itself elsewhere. Every one of those fails silently: the page
 * still renders, nothing looks broken, and the first anyone knows is a
 * withdrawn merchant account.
 */
class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_published_at_the_agreed_url(): void
    {
        $this->get('/refund-policy')->assertOk();
    }

    public function test_it_names_the_company_that_is_bound_by_it(): void
    {
        $this->get(route('refund-policy'))->assertOk()
            ->assertSee('SOLAVIA GROUP LIMITED')
            ->assertSee('80048169153974')
            ->assertSee('Nansana Municipality, Wakiso District')
            ->assertSee('P.O. Box 214231');
    }

    /**
     * The source document gave two different addresses, one in the letterhead
     * and another in the refund-request section. A customer writing to the
     * wrong inbox is a refund that never gets processed.
     */
    public function test_there_is_exactly_one_contact_address_on_the_page(): void
    {
        $html = (string) $this->get(route('refund-policy'))->assertOk()->getContent();

        preg_match_all('/[\w.+-]+@[\w-]+\.[\w.]+/', $html, $matches);
        $addresses = array_values(array_unique($matches[0]));

        $this->assertSame(['solaviaug@gmail.com'], $addresses);
        $this->assertStringContainsString('mailto:solaviaug@gmail.com', $html, 'it has to be clickable');
        $this->assertStringContainsString('tel:+256783204665', $html);
    }

    public function test_every_refund_situation_is_on_the_page(): void
    {
        $page = $this->get(route('refund-policy'))->assertOk();

        foreach ([
            'no access within 24 hours',
            'charged twice',
            'materially different from its description',
            'no order was created',
            'changed your mind',
            'subscription period that has already started',
        ] as $situation) {
            $page->assertSee($situation, false);
        }

        // Four refunded, two not. A table that lost a row still looks fine.
        $html = (string) $page->getContent();
        $this->assertSame(4, substr_count($html, 'r-tag r-yes'));
        $this->assertSame(2, substr_count($html, 'r-tag r-no'));
    }

    public function test_it_states_the_windows_a_customer_is_held_to(): void
    {
        $this->get(route('refund-policy'))->assertOk()
            ->assertSee('within 7 days of payment')
            ->assertSee('within 2 business days')
            ->assertSee('within 7 business days')
            ->assertSee('30 days before the next billing term');
    }

    /**
     * A fixed date, not date('F Y').
     *
     * The other legal pages print the current month, so their "last updated"
     * line moves on its own while the document does not. On a policy a payment
     * provider relies on, that is a false statement.
     */
    public function test_the_effective_date_does_not_move_on_its_own(): void
    {
        $this->travelTo(now()->addMonths(7));

        $this->get(route('refund-policy'))->assertOk()->assertSee('Effective 8 September 2026');
    }

    /** "Published on our website" means reachable without knowing the URL. */
    public function test_it_is_linked_from_every_public_page(): void
    {
        foreach (['/', '/about', '/e-learning', '/work'] as $path) {
            $this->get($path)->assertOk()->assertSee(route('refund-policy'), false);
        }
    }

    /**
     * On a phone, and that is the case that mattered: the mobile sheet carried
     * no legal links at all, so the policy could only be reached by somebody
     * who already knew the URL.
     */
    public function test_it_is_in_the_mobile_menu_and_the_footer_rule(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('#<div class="mm-legal">(.*?)</div>#s', $html, $mobile);
        $this->assertNotEmpty($mobile[1] ?? '', 'the mobile sheet has no legal links');
        $this->assertStringContainsString(route('refund-policy'), $mobile[1]);

        preg_match('#<span class="foot-legal">(.*?)</span>#s', $html, $footer);
        $this->assertNotEmpty($footer[1] ?? '', 'the footer rule has no legal links');
        $this->assertStringContainsString(route('refund-policy'), $footer[1]);
    }

    /** Defined once, so the footer and the mobile sheet cannot drift apart. */
    public function test_the_three_legal_pages_are_all_reachable(): void
    {
        foreach (\App\Support\SiteNav::legal() as $item) {
            $this->get($item['url'])->assertOk();
        }

        $this->assertContains(route('refund-policy'), \App\Support\SiteNav::urls());
    }

    /** A provider that cannot find the page treats it as not published. */
    public function test_it_is_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('refund-policy'), false);
    }

    /** Terms used to say refunds were case by case, which this page contradicts. */
    public function test_the_terms_page_no_longer_contradicts_it(): void
    {
        $this->get(route('terms'))->assertOk()
            ->assertDontSee('case-by-case')
            ->assertSee(route('refund-policy'), false);
    }

    public function test_it_is_indexable_and_carries_its_own_description(): void
    {
        $html = (string) $this->get(route('refund-policy'))->assertOk()->getContent();

        // A policy a provider has to find must not be excluded from search.
        $this->assertStringNotContainsString('noindex', $html);

        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);
        $this->assertNotEmpty($m[1] ?? '', 'no meta description');
        $this->assertStringContainsString('efund', $m[1]);
    }

    /** The bug that swallowed a page into its own meta description once before. */
    public function test_it_leaves_no_output_buffer_open(): void
    {
        $level = ob_get_level();

        $this->get(route('refund-policy'))->assertOk();

        $this->assertSame($level, ob_get_level());
    }
}
