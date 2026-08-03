<?php

namespace Tests\Feature\Public;

use App\Models\PortfolioProject;
use Database\Seeders\PortfolioContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every system has a picture of itself.
 *
 * The screenshots are drawn rather than captured — the real screens hold
 * livestock registries, patient records and human-rights case files, none of
 * which can be published — so nothing stops a new project being added with no
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
                "{$project->slug} has no screenshot — draw one into "
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
