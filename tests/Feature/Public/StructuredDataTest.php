<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The structured data every page type carries.
 *
 * Worth testing because of how it fails: a malformed or missing node changes
 * nothing a visitor can see. The page renders, the content is right, and the
 * only symptom is a result in Google that stays a plain blue link while a
 * competitor's shows a breadcrumb, a rating and a price.
 *
 * Three page types had nothing at all before this: the case studies, the course
 * catalogue and the blog index. A case study is the page a prospective client
 * is sent, and it was the page a search engine understood least.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int,array<string,mixed>> */
    private function nodes(string $url): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(
            fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            $m[1],
        );
    }

    /** @return array<string,mixed>|null */
    private function node(string $url, string $type): ?array
    {
        foreach ($this->nodes($url) as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }

    private function project(): PortfolioProject
    {
        return PortfolioProject::create([
            'title' => 'ULITS National Livestock Traceability',
            'slug' => 'ulits-'.Str::random(5),
            'description' => 'A national system for identifying and tracing livestock.',
            'is_featured' => true,
            'sort_order' => 1,
        ]);
    }

    /* Every node must be valid JSON with a context and a type ------------- */

    public function test_every_node_on_every_page_type_is_well_formed(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $project = $this->project();

        foreach (['/', '/e-learning', '/work', '/solavia',
            route('courses.show', $course), route('portfolio.project', $project)] as $url) {
            $nodes = $this->nodes($url);

            $this->assertNotEmpty($nodes, "{$url} carries no structured data");

            foreach ($nodes as $node) {
                $this->assertSame('https://schema.org', $node['@context'] ?? null, $url);
                $this->assertNotEmpty($node['@type'] ?? null, $url);
            }
        }
    }

    /* Breadcrumbs ---------------------------------------------------------- */

    /**
     * The breadcrumb is what turns a result's grey URL line into a readable
     * path. Google reads it whether or not the page draws one.
     */
    public function test_deep_pages_carry_a_breadcrumb_that_starts_at_home(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'title' => 'Laravel Essentials']);
        $project = $this->project();

        foreach ([route('courses.show', $course), route('portfolio.project', $project),
            '/e-learning', '/work'] as $url) {
            $crumb = $this->node($url, 'BreadcrumbList');

            $this->assertNotNull($crumb, "{$url} has no BreadcrumbList");

            $items = $crumb['itemListElement'];
            $this->assertSame('Home', $items[0]['name']);
            $this->assertSame(route('home'), $items[0]['item']);

            // Positions must run 1, 2, 3 with no gaps or repeats.
            $this->assertSame(range(1, count($items)), array_column($items, 'position'), $url);
        }
    }

    public function test_the_deepest_crumb_is_the_page_itself(): void
    {
        $project = $this->project();
        $crumb = $this->node(route('portfolio.project', $project), 'BreadcrumbList');

        $last = end($crumb['itemListElement']);

        $this->assertSame($project->title, $last['name']);
        $this->assertSame(route('portfolio.project', $project), $last['item']);
    }

    /* Listing pages -------------------------------------------------------- */

    public function test_listing_pages_declare_what_they_list(): void
    {
        foreach (range(1, 3) as $i) {
            Course::factory()->create(['is_published' => true, 'is_coming_soon' => false]);
        }
        $this->project();

        foreach (['/e-learning' => 'Programming courses', '/work' => 'Systems delivered'] as $url => $name) {
            $list = $this->node($url, 'ItemList');

            $this->assertNotNull($list, "{$url} has no ItemList");
            $this->assertSame($name, $list['name']);
            $this->assertGreaterThan(0, $list['numberOfItems']);
            $this->assertCount($list['numberOfItems'], $list['itemListElement']);

            foreach ($list['itemListElement'] as $item) {
                $this->assertStringStartsWith('http', $item['url']);
            }
        }
    }

    /**
     * A listing page that describes each item in full competes with the item's
     * own page for the same query, and the item's page should win.
     */
    public function test_a_listing_names_its_items_by_url_not_by_description(): void
    {
        Course::factory()->create(['is_published' => true, 'is_coming_soon' => false]);

        foreach ($this->node('/e-learning', 'ItemList')['itemListElement'] as $item) {
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayNotHasKey('description', $item);
        }
    }

    /* Case studies --------------------------------------------------------- */

    /**
     * CreativeWork, not Product: these are systems built for one client and
     * not on sale, and a Product node invites a price and an availability that
     * do not exist.
     */
    public function test_a_case_study_is_described_as_work_not_as_a_product(): void
    {
        $project = $this->project();
        $work = $this->node(route('portfolio.project', $project), 'CreativeWork');

        $this->assertNotNull($work);
        $this->assertSame($project->title, $work['name']);
        $this->assertSame(config('company.name'), $work['creator']['name']);
        $this->assertNull($this->node(route('portfolio.project', $project), 'Product'));
    }

    /* The article page ----------------------------------------------------- */

    public function test_an_article_keeps_its_own_node_and_gains_a_breadcrumb(): void
    {
        $author = User::factory()->create();
        $post = Post::create([
            'title' => 'Teaching programming without the jargon',
            'slug' => 'teaching-'.Str::random(5),
            'excerpt' => 'Why the words get in the way.',
            'body' => 'The body of the article.',
            'category' => 'Teaching',
            'author_id' => $author->id,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $url = route('insights.show', $post);

        $this->assertNotNull($this->node($url, 'BlogPosting'));
        $this->assertNotNull($this->node($url, 'BreadcrumbList'));
    }

    /* The organisation ----------------------------------------------------- */

    public function test_the_company_is_described_consistently_wherever_it_appears(): void
    {
        foreach (['/', '/solavia'] as $url) {
            $org = $this->node($url, 'Organization');

            $this->assertNotNull($org, "{$url} has no Organization node");
            $this->assertSame(config('company.name'), $org['name']);
            $this->assertSame(config('company.registration_number'), $org['identifier']);
            $this->assertSame('UG', $org['address']['addressCountry']);
        }
    }

    /**
     * The trap that shipped broken markup to production.
     *
     * Laravel has an @context Blade directive. A literal '@context' key written
     * inside a template is compiled as that directive, so the page ships a
     * script tag full of raw PHP where its JSON should be. It was live on
     * /solavia and on every article, and nothing on the page looked wrong.
     *
     * Structured data is therefore built in PHP and only printed by views.
     */
    public function test_no_view_writes_the_context_key_inline(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views').'/*/*.blade.php') as $file) {
            if (str_contains((string) file_get_contents($file), "'@context'")) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file);
            }
        }

        $this->assertSame([], $offenders,
            'Build these nodes in PHP: Blade compiles @context as a directive. Offending views: '
            .implode(', ', $offenders));
    }

    /** Declaring a search box the site does not have is a lie Google checks. */
    public function test_no_search_action_is_claimed(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('SearchAction', $html);
    }
}
