<?php

namespace Tests\Feature\Public;

use App\Models\ContactMessage;
use App\Support\Spam\FormShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The company section.
 *
 * Written for one reader: somebody at a payment provider or a bank confirming
 * that a registered Ugandan company stands behind the payments. Every
 * assertion below is something that reader checks, and every one of them fails
 * silently, leaving a page that still renders and still looks fine.
 */
class SolaviaCompanyTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'solavia.home', 'solavia.products', 'solavia.terms',
        'solavia.privacy', 'solavia.refund-policy', 'solavia.contact',
    ];

    public function test_every_company_page_loads(): void
    {
        foreach (self::PAGES as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    /** The brief's rule: the title must carry the legal name on every one. */
    public function test_every_title_and_description_names_the_company(): void
    {
        foreach (self::PAGES as $name) {
            $html = (string) $this->get(route($name))->assertOk()->getContent();

            preg_match('#<title>([^<]*)</title>#', $html, $title);
            preg_match('#<meta name="description" content="([^"]*)"#', $html, $desc);

            $this->assertStringContainsString('SOLAVIA GROUP LIMITED',
                html_entity_decode($title[1] ?? '', ENT_QUOTES, 'UTF-8'), "{$name} title");
            $this->assertStringContainsString('SOLAVIA GROUP LIMITED',
                html_entity_decode($desc[1] ?? '', ENT_QUOTES, 'UTF-8'), "{$name} description");
        }
    }

    public function test_the_registered_facts_are_all_on_the_company_page(): void
    {
        $this->get(route('solavia.home'))->assertOk()
            ->assertSee('SOLAVIA GROUP LIMITED')
            ->assertSee('80048169153974')
            ->assertSee('Uganda Registration Services Bureau')
            ->assertSee('22 July 2026')
            ->assertSee('Plot 2335, Buwambo-Katadde-Najjo Road')
            ->assertSee('Buwambo Trading Center')
            ->assertSee('Gombe Division')
            ->assertSee('Nansana Municipality')
            ->assertSee('Wakiso District')
            ->assertSee('P.O. Box 214231, Kampala GPO')
            ->assertSee('+256 783 204 665')
            ->assertSee('solaviaug@gmail.com')
            ->assertSee('Muhindo Mubaraka')
            ->assertSee('Katushabe Aminah')
            ->assertSee('Ndugwa Adam');
    }

    /**
     * The registration number has to be selectable text.
     *
     * A reviewer's next move is to copy it into a form. Set in an image it
     * cannot be copied, cannot be searched, and cannot be read by anybody
     * using a screen reader.
     */
    public function test_the_registration_number_is_text_and_not_an_image(): void
    {
        $html = (string) $this->get(route('solavia.home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<span class="sv-copyable">\s*80048169153974\s*</span>#',
            $html,
        );
    }

    public function test_the_hero_says_what_the_company_does(): void
    {
        $this->get(route('solavia.home'))->assertOk()
            ->assertSee('A Ugandan software company building and operating digital products for learners, schools and consumers across East Africa.')
            ->assertSee('Our products')
            ->assertSee('Contact us');
    }

    public function test_it_states_how_customers_pay_and_that_nothing_is_shipped(): void
    {
        $this->get(route('solavia.home'))->assertOk()
            ->assertSee('MTN Mobile Money, Airtel Money, Visa and Mastercard')
            ->assertSee('We ship no physical goods.');
    }

    public function test_the_scale_figures_are_stated(): void
    {
        $this->get(route('solavia.home'))->assertOk()
            ->assertSee('118,000')
            ->assertSee('18,000');
    }

    /* Products ------------------------------------------------------------ */

    public function test_every_product_appears_with_a_price_line(): void
    {
        $page = $this->get(route('solavia.products'))->assertOk();

        foreach (['e-Learning courses', 'Source code and templates', 'School Dynamics',
            'Hospital Management System', 'ULITS', 'LugaFlix', 'MunoApp',
            'VJ Junior Movies', 'UGNEWS24', 'Musenene Family App'] as $product) {
            $page->assertSee($product, false);
        }

        $page->assertSee('UGX 40,000 to UGX 60,000 per course')
            ->assertSee('UGX 280,000 to UGX 550,000')
            ->assertSee('Subscription, quoted per school')
            ->assertSee('Quoted per facility')
            ->assertSee('Subscription UGX 2,500 to UGX 50,000')
            ->assertSee('All products listed here are owned and operated by SOLAVIA GROUP LIMITED.');
    }

    /**
     * No card may be empty, and no card may link nowhere without saying why.
     *
     * The Play Store listings are down while the developer account is restored,
     * and a dead link on the page a provider is using to verify the business
     * reads as a product that does not exist.
     */
    public function test_a_product_with_no_live_link_explains_itself_instead(): void
    {
        foreach (config('products') as $group) {
            foreach ($group['items'] as $item) {
                $this->assertNotEmpty($item['description'], $item['name'].' has no description');
                $this->assertNotEmpty($item['platforms'], $item['name'].' has no platforms');
                $this->assertNotEmpty($item['price'] ?? $item['price_note'] ?? null,
                    $item['name'].' has neither a price nor a price note');

                if ($item['url'] === null) {
                    $this->assertNotEmpty($item['link_note'] ?? null,
                        $item['name'].' links nowhere and does not say why');
                }
            }
        }
    }

    public function test_no_product_image_is_missing_from_disk(): void
    {
        foreach (config('products') as $group) {
            foreach ($group['items'] as $item) {
                if ($item['image'] ?? null) {
                    $this->assertFileExists(public_path($item['image']), $item['name']);
                }
            }
        }
    }

    /* Policies ------------------------------------------------------------- */

    public function test_the_refund_policy_carries_the_wording_sent_to_providers(): void
    {
        $this->get(route('solavia.refund-policy'))->assertOk()
            ->assertSee('All our products are digital', false)
            ->assertSee('Delivery is instant.', false)
            ->assertSee('within 7 days of payment', false)
            ->assertSee('within 2 business days', false)
            ->assertSee('within 7 business days', false)
            ->assertSee('30 days before the next billing term', false);
    }

    public function test_the_terms_cover_what_a_reviewer_looks_for(): void
    {
        $this->get(route('solavia.terms'))->assertOk()
            ->assertSee('Ugandan Shillings (UGX)', false)
            ->assertSee('laws of the Republic of Uganda', false)
            ->assertSee('We ship no physical goods.', false)
            ->assertSee('belongs to SOLAVIA GROUP LIMITED', false);
    }

    public function test_the_privacy_policy_names_the_controller_and_excludes_card_numbers(): void
    {
        $this->get(route('solavia.privacy'))->assertOk()
            ->assertSee('The data controller is SOLAVIA GROUP LIMITED', false)
            ->assertSee('We never collect or store card numbers', false);
    }

    /* The contact form ----------------------------------------------------- */

    public function test_a_stranger_can_send_a_message(): void
    {
        Mail::fake();

        $this->post(route('solavia.contact.submit'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = ContactMessage::sole();
        $this->assertSame('Aisha Nakalema', $row->name);
        $this->assertSame('aisha@example.com', $row->email);
        $this->assertStringContainsString('order', $row->message);
        // The optional phone travels with the message rather than being lost.
        $this->assertStringContainsString('0783204665', $row->message);
    }

    public function test_bad_details_are_refused(): void
    {
        $this->post(route('solavia.contact.submit'), $this->payload(['email' => 'nope']))
            ->assertSessionHasErrors('email');
        $this->post(route('solavia.contact.submit'), $this->payload(['message' => 'hi']))
            ->assertSessionHasErrors('message');
        $this->post(route('solavia.contact.submit'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_form_carries_the_spam_shield(): void
    {
        $this->post(route('solavia.contact.submit'), $this->payload([FormShield::HONEYPOT => 'http://spam.example']))
            ->assertRedirect()->assertSessionHas('success');

        // Answered as success so a bot learns nothing, and written nowhere.
        $this->assertSame(0, ContactMessage::count());
    }

    /** Five an hour per IP, as briefed. */
    public function test_the_form_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('solavia.contact.submit'), $this->payload(['email' => "a{$i}@example.com"]))
                ->assertRedirect();
        }

        $this->post(route('solavia.contact.submit'), $this->payload(['email' => 'sixth@example.com']))
            ->assertStatus(429);

        $this->assertSame(5, ContactMessage::count());
    }

    /** A mail server that is down must not lose the message or show an error. */
    public function test_a_failing_mailer_does_not_lose_the_message(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp is down'));

        $this->post(route('solavia.contact.submit'), $this->payload())
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, ContactMessage::count());
    }

    /* Machine-readable details --------------------------------------------- */

    public function test_the_well_known_company_file_answers(): void
    {
        $json = $this->get('/.well-known/company.json')->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->json();

        $this->assertSame('SOLAVIA GROUP LIMITED', $json['name']);
        $this->assertSame('80048169153974', $json['registrationNumber']);
        $this->assertSame('2026-07-22', $json['incorporatedOn']);
        $this->assertSame('UG', $json['address']['countryCode']);
        $this->assertSame('solaviaug@gmail.com', $json['email']);
        $this->assertCount(3, $json['leadership']);
        $this->assertCount(10, $json['products']);
        $this->assertArrayHasKey('refunds', $json['policies']);
    }

    /**
     * The file and the route must not be able to disagree.
     *
     * Production serves a real file, because the server's .htaccess passes
     * anything under .well-known/ straight to the filesystem for SSL
     * validation and Laravel never sees the request. The command writes that
     * file from the controller so there is still one source of truth.
     */
    public function test_the_generated_manifest_matches_the_route(): void
    {
        $path = public_path('.well-known/company.json');
        @unlink($path);

        $this->artisan('company:manifest')->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertSame(
            $this->get('/.well-known/company.json')->json(),
            json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR),
        );

        @unlink($path);
    }

    /* Site-wide ------------------------------------------------------------ */

    public function test_the_company_is_one_click_away_from_any_page(): void
    {
        foreach (['/', '/about', '/e-learning', '/work', '/source-code', '/blog'] as $path) {
            $this->get($path)->assertOk()->assertSee(route('solavia.home'), false);
        }
    }

    public function test_the_footer_states_who_operates_the_site(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('muhindomubaraka.com is operated by SOLAVIA GROUP LIMITED', false)
            ->assertSee('Reg. No. 80048169153974', false);
    }

    public function test_the_company_pages_are_in_the_sitemap(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (self::PAGES as $name) {
            $this->assertStringContainsString(route($name), $xml, $name);
        }
    }

    /** What a reviewer doing a test purchase looks for, beside the pay button. */
    public function test_the_course_page_names_the_merchant_of_record(): void
    {
        $course = \App\Models\Course::factory()->create(['is_published' => true, 'price' => 60000]);

        $this->get(route('courses.show', $course))->assertOk()
            ->assertSee('Payments are processed for SOLAVIA GROUP LIMITED', false)
            ->assertSee(route('solavia.refund-policy'), false);
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return $this->shielded(array_merge([
            'name' => 'Aisha Nakalema',
            'email' => 'aisha@example.com',
            'phone' => '0783204665',
            'message' => 'I paid for a course this morning and I would like to ask about my order.',
        ], $overrides));
    }
}
