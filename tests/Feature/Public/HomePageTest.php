<?php

namespace Tests\Feature\Public;

use App\Models\PortfolioProject;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The landing page has to stay shippable before any artwork exists, and it must
 * never publish a claim nobody made.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_headline_does_not_scope_the_work_to_a_region(): void
    {
        Settings::set('portfolio.identity', json_encode([
            'name' => 'Muhindo Mubaraka',
            'title' => 'Manager, Information Systems',
            'tagline' => 'I build software that scales, and I teach others to do the same.',
        ]));

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('I build software that scales', false);
        // A headline that names a region caps the audience at it before anyone
        // has read a word about the work.
        $this->assertStringNotContainsStringIgnoringCase(
            'East Africa',
            $this->headline((string) $response->getContent()),
            'the hero headline must not scope the offer to a region'
        );
    }

    public function test_a_missing_image_renders_a_labelled_slot_instead_of_a_broken_page(): void
    {
        // The portrait slot has since been filled, so the mechanism is asserted
        // against the project screenshots, which are still to come.
        $project = PortfolioProject::create([
            'title' => 'A System', 'slug' => 'a-system',
            'description' => 'Delivered end to end.', 'sort_order' => 0,
        ]);

        $this->assertFileDoesNotExist(public_path("images/systems/{$project->slug}.png"));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Screenshot')
            // The slot names the exact file it is waiting for.
            ->assertSee("public/images/systems/{$project->slug}.png");
    }

    public function test_a_supplied_image_replaces_its_slot(): void
    {
        /* The slot resolves against public_path, so proving it picks a real file
           up means writing one there. The name is randomised and namespaced so
           this can never collide with a genuine asset — an earlier version of
           this test used images/portrait.jpg and its cleanup deleted the site's
           actual portrait. A test may not be able to destroy production files. */
        $slug = 'zz-test-'.Str::random(8);
        $path = public_path("images/systems/{$slug}.png");
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'not-a-real-jpeg-but-a-real-file');

        PortfolioProject::create([
            'title' => 'Supplied System', 'slug' => $slug,
            'description' => 'Has a screenshot.', 'sort_order' => 0,
        ]);

        try {
            $this->get(route('home'))
                ->assertOk()
                ->assertSee("images/systems/{$slug}.png")
                ->assertDontSee("public/images/systems/{$slug}.png");
        } finally {
            @unlink($path);
        }

        $this->assertFileDoesNotExist($path, 'the test must leave nothing behind in public/');
    }

    public function test_a_client_without_a_logo_file_still_shows_as_a_wordmark(): void
    {
        Settings::set('portfolio.clients', json_encode(['Uganda Wildlife Authority']));

        // An empty box would read as a broken image; the name is a real mark.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Uganda Wildlife Authority')
            ->assertSee('wordmark', false);
    }

    public function test_the_testimonials_section_is_absent_until_real_quotes_exist(): void
    {
        Settings::set('portfolio.testimonials', json_encode([]));

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('What the people I');
    }

    public function test_a_supplied_testimonial_is_shown_with_its_attribution(): void
    {
        Settings::set('portfolio.testimonials', json_encode([[
            'quote' => 'He delivered the system and trained the team to run it.',
            'name' => 'A Real Person',
            'role' => 'Director',
            'org' => 'Some Organisation',
        ]]));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('He delivered the system and trained the team to run it.')
            ->assertSee('A Real Person')
            ->assertSee('Director');
    }

    public function test_the_about_section_leads_with_a_summary_and_a_way_deeper(): void
    {
        Settings::set('portfolio.about', json_encode([
            'lead' => 'Lead line.',
            'heading' => 'I build the systems, then teach the people who run them',
            'home' => ['First paragraph about the work.', 'Second paragraph about teaching.'],
        ]));

        $this->get(route('home'))->assertOk()
            ->assertSee('I build the systems, then teach the people who run them')
            ->assertSee('First paragraph about the work.')
            ->assertSee('Read more about me')
            ->assertSee(route('portfolio.cv'), false);
    }

    public function test_a_reference_without_a_quote_still_shows_who_they_are_and_where_to_check(): void
    {
        /* Three named academics were supplied with links but no words. Their
           credibility is the name and the verifiable link; inventing what a
           sitting Vice Chancellor said would be putting words in a real,
           findable person's mouth. */
        Settings::set('portfolio.testimonials', json_encode([[
            'quote' => '',
            'name' => 'Prof. Jude T. Lubega',
            'role' => 'Vice Chancellor',
            'org' => 'Nkumba University',
            'link' => 'https://nkumbauniversity.ac.ug/staff/prof-jude-t-lubega/',
        ]]));

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('Prof. Jude T. Lubega');
        $response->assertSee('Vice Chancellor');
        $response->assertSee('https://nkumbauniversity.ac.ug/staff/prof-jude-t-lubega/', false);
        // No empty quotation marks where a quote would be.
        $response->assertDontSee('<blockquote></blockquote>', false);
    }

    public function test_a_reference_shows_its_quote_once_one_is_supplied(): void
    {
        Settings::set('portfolio.testimonials', json_encode([[
            'quote' => 'He delivered the system and trained the team to run it.',
            'name' => 'A Real Person', 'role' => 'Director',
        ]]));

        $this->get(route('home'))->assertOk()
            ->assertSee('He delivered the system and trained the team to run it.');
    }

    public function test_the_stat_row_publishes_the_true_figures(): void
    {
        Settings::set('portfolio.stats', json_encode([
            ['value' => '9+', 'label' => 'Years in systems delivery'],
        ]));

        // These are credentials. The count-up animation reads them from the
        // markup and restores them exactly, so the served HTML is the contract.
        $this->get(route('home'))->assertOk()->assertSee('9+')->assertSee('Years in systems delivery');
    }

    public function test_each_project_links_to_its_own_case_study(): void
    {
        $project = PortfolioProject::create([
            'title' => 'A Shipped System',
            'slug' => 'a-shipped-system',
            'description' => 'An information system delivered end to end.',
            'sort_order' => 0,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('A Shipped System')
            ->assertSee(route('portfolio.project', $project), false);
    }

    /** The text of the first <h1>, which is where the hero claim lives. */
    private function headline(string $html): string
    {
        preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $m);

        return $m[1] ?? '';
    }
}
