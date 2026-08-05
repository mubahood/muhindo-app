<?php

namespace Tests\Feature\Public;

use App\Models\PortfolioProject;
use Database\Seeders\PortfolioContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every system has a picture of itself.
 *
 * The screenshots are drawn rather than captured. The real screens hold
 * livestock registries, patient records and human-rights case files, none of
 * which can be published, so nothing stops a new project being added with no
 * picture and quietly falling back to a grey "Screenshot 1600 x 1000px" slot
 * on the home page. This is what notices.
 */
class SystemScreenshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PortfolioContentSeeder::class);
    }

    public function test_every_project_in_the_portfolio_has_one_drawn(): void
    {
        $projects = PortfolioProject::all();

        $this->assertGreaterThan(0, $projects->count(), 'the seeder produced no projects');

        foreach ($projects as $project) {
            $this->assertNotNull(
                $project->screenshotUrl(),
                "{$project->slug} has no screenshot, draw one into "
                    ."public/images/systems/{$project->slug}.svg"
            );
        }
    }

    public function test_each_one_is_the_shape_the_cards_are_cut_for(): void
    {
        foreach (PortfolioProject::pluck('slug') as $slug) {
            $file = public_path("images/systems/{$slug}.svg");

            if (! is_file($file)) {
                continue;   // covered by the test above
            }

            $svg = (string) file_get_contents($file);

            // The cards crop to 16:10. Anything else gets its edges cut off.
            $this->assertStringContainsString('viewBox="0 0 1600 1000"', $svg,
                "{$slug} is not 16:10 and will be cropped in the cards");

            // Self-contained: an <image> or a webfont would be a request the
            // page cannot make from inside an <img> tag anyway.
            $this->assertStringNotContainsString('<image', $svg,
                "{$slug} embeds a raster image; these are drawn, not pasted");
            $this->assertStringNotContainsString('@import', $svg);
        }
    }

    public function test_the_screenshots_appear_where_the_work_is_shown(): void
    {
        $first = PortfolioProject::orderBy('sort_order')->first();

        foreach ([route('home'), route('portfolio.work'),
            route('portfolio.projects.index'), route('portfolio.project', $first)] as $url) {
            $this->assertStringContainsString(
                "images/systems/{$first->slug}.svg",
                (string) $this->get($url)->assertOk()->getContent(),
                "{$url} does not show the system"
            );
        }
    }

    public function test_every_case_study_actually_explains_the_system(): void
    {
        // A case study with a one-line description and a bullet list answers
        // "what is it called" and nothing else. These four fields are what
        // somebody deciding whether to hire is reading for.
        foreach (PortfolioProject::all() as $project) {
            $this->assertNotEmpty($project->problem, "{$project->slug}: no problem statement");
            $this->assertNotEmpty($project->approach, "{$project->slug}: no description of what was built");
            $this->assertGreaterThanOrEqual(4, count($project->mechanics ?? []),
                "{$project->slug}: how it works needs more than a gesture");
            $this->assertNotEmpty($project->constraints, "{$project->slug}: no constraints");
            $this->assertNotEmpty($project->stack, "{$project->slug}: nothing recorded about the build");
        }
    }

    public function test_a_case_study_offers_a_walkthrough_rather_than_a_dead_link(): void
    {
        // Four of these are internal government systems with no public URL,
        // so "visit site" would be a broken promise. Asking for a walkthrough
        // works for all eight, and carries which system into the brief.
        $project = PortfolioProject::where('slug', 'wildlife-offenders')->firstOrFail();

        $this->assertNull($project->external_link);

        $this->get(route('portfolio.project', $project))->assertOk()
            ->assertSee(route('hire'), false)
            ->assertSee('Request a walkthrough');

        // Asking for one goes through the same door as hiring: an account
        // first, so the request has an owner and somewhere to be answered.
        $this->get(route('hire'))
            ->assertRedirect(route('register', ['account_type' => 'client']));
    }

    public function test_the_screenshots_are_eight_products_not_one(): void
    {
        // Every system in one palette read as eight pages of a single product.
        // Each one now carries its own brand colour; if two ever collide, the
        // set has quietly gone back to looking like a template.
        $primaries = [];

        foreach (PortfolioProject::pluck('slug') as $slug) {
            $file = public_path("images/systems/{$slug}.svg");
            if (! is_file($file)) {
                continue;
            }

            // The window bar is painted in the system's darkest brand step.
            preg_match('/<rect x="0.0" y="0.0" width="1600.0" height="44.0" fill="(#[0-9A-F]{6})"/i',
                (string) file_get_contents($file), $m);

            $this->assertNotEmpty($m, "{$slug} has no window bar to read a brand colour from");
            $primaries[$slug] = strtoupper($m[1]);
        }

        $this->assertGreaterThanOrEqual(8, count($primaries));
        $this->assertSame(count($primaries), count(array_unique($primaries)),
            'two systems share a brand colour: '.json_encode($primaries));
    }

    public function test_an_uploaded_cover_beats_the_drawn_one(): void
    {
        // If somebody ever does get clearance to publish a real screengrab,
        // uploading it through the admin has to win without deleting anything.
        $project = PortfolioProject::first();
        $project->update(['cover_image' => 'projects/real-screengrab.png']);

        $this->assertStringContainsString('projects/real-screengrab.png',
            (string) $project->fresh()->screenshotUrl());
    }
}
