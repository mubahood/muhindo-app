<?php

namespace Tests\Feature\Public;

use App\Models\Education;
use App\Models\Experience;
use App\Models\GalleryPhoto;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Skill;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The About story reads as one sequence.
 *
 * Every chapter must (a) stay inside the rail, so the sidebar never vanishes
 * mid-story, and (b) end pointing at the chapter the sidebar puts next. Those
 * two used to disagree — the About page's next button said "Experience" while
 * the rail's second entry was "My work" — so this walks the whole chain rather
 * than checking one page's one link.
 */
class AboutChapterSequenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The order the rail puts them in, and where each hands off to. Skills and
     * experience share one rail entry, so they hand off to each other by hand;
     * everything else derives its next from the nav.
     *
     * @return list<array{0:string,1:string}>
     */
    public static function chapters(): array
    {
        return [
            'about → work' => ['portfolio.about', 'portfolio.work'],
            'work → cv' => ['portfolio.work', 'portfolio.cv'],
            'cv → education' => ['portfolio.cv', 'portfolio.education'],
            'education → skills' => ['portfolio.education', 'portfolio.skills'],
            'skills → experience' => ['portfolio.skills', 'portfolio.experience'],
            'experience → research' => ['portfolio.experience', 'portfolio.research'],
            'research → gallery' => ['portfolio.research', 'gallery.index'],
            'gallery → services' => ['gallery.index', 'portfolio.services'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Every chapter hides itself when it has nothing to show, so each one
        // needs a row before it can be asked where it points next.
        PortfolioProject::create([
            'title' => 'National Livestock Traceability', 'slug' => 'nlits',
            'client' => 'Ministry of Agriculture', 'description' => 'Tagging and tracing cattle nationally.',
            'tags' => ['Laravel', 'Flutter'], 'sort_order' => 1,
        ]);
        Education::create([
            'institution' => 'Islamic University in Uganda', 'degree' => 'BSc Computer Science',
            'start_date' => '2014-08-01', 'end_date' => '2018-07-01', 'description' => 'First class.',
        ]);
        Skill::create(['name' => 'Laravel (Expert)', 'category' => 'Backend', 'sort_order' => 1]);
        Experience::create([
            'company' => 'Eight Tech Consults Ltd', 'role' => 'Full-Stack Developer',
            'start_date' => '2021-01-01', 'end_date' => null, 'description' => 'Enterprise systems.',
        ]);
        Service::create(['title' => 'Enterprise system design', 'description' => 'End to end.', 'icon' => 'fa-cubes']);
        GalleryPhoto::create([
            'title' => 'At the desk', 'caption' => 'Building.', 'alt' => 'Muhindo at his desk',
            'category' => 'work', 'path' => 'gallery/a.jpg', 'thumb_path' => 'gallery/a-thumb.jpg',
            'width' => 1600, 'height' => 1200, 'bytes' => 90000,
            'is_published' => true, 'is_featured' => true, 'sort_order' => 1,
        ]);
        Settings::set('portfolio.research', json_encode([
            'institution' => 'Makerere University', 'title' => 'Distributed systems and ML',
            'supervisor' => 'Dr Someone', 'body' => 'The work.', 'areas' => ['ML'],
        ]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('chapters')]
    public function test_each_chapter_ends_by_pointing_at_the_next_one(string $chapter, string $next): void
    {
        $html = (string) $this->get(route($chapter))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="ch-end">', $html,
            "{$chapter} does not end with the shared chapter ending.");
        $this->assertStringContainsString('Hire Me', $html,
            "{$chapter} does not offer the hire button.");

        // The next link lives inside the ending, not merely somewhere on the
        // page — the rail links to most of these too, so a page-wide search
        // would pass even with the button pointing at the wrong chapter.
        $ending = substr($html, (int) strpos($html, '<div class="ch-end">'));

        $this->assertStringContainsString(route($next), $ending,
            "{$chapter} should hand off to {$next}.");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('chapters')]
    public function test_each_chapter_pins_the_same_two_actions_for_a_phone(string $chapter, string $next): void
    {
        $html = (string) $this->get(route($chapter))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<div class="act-bar">'),
            "{$chapter} should have exactly one action bar.");

        $bar = substr($html, (int) strpos($html, '<div class="act-bar">'));

        // The same destinations as the inline ending, so a reader is never
        // offered a different next chapter depending on their screen width.
        $this->assertStringContainsString(route('hire'), $bar);
        $this->assertStringContainsString(route($next), $bar);
    }

    public function test_the_cv_keeps_its_download_in_the_phone_bar(): void
    {
        $bar = (string) $this->get(route('portfolio.cv'))->assertOk()->getContent();
        $bar = substr($bar, (int) strpos($bar, '<div class="act-bar">'));

        $this->assertStringContainsString('muhindo-mubaraka-cv.pdf', $bar);

        // And only once — it used to have a bar of its own alongside this one.
        $this->assertSame(1, substr_count($bar, '<div class="act-bar">'));
    }

    public function test_the_last_chapter_offers_the_catalogue_rather_than_a_dead_arrow(): void
    {
        $html = (string) $this->get(route('portfolio.services'))->assertOk()->getContent();
        $ending = substr($html, (int) strpos($html, '<div class="ch-end">'));

        $this->assertStringContainsString(route('courses.index'), $ending);
    }

    public function test_every_chapter_keeps_the_sidebar(): void
    {
        $chapters = collect(\App\Support\SiteNav::items())->firstWhere('label', 'About Me')['children'];

        foreach ($chapters as $chapter) {
            $html = (string) $this->get($chapter['url'])->assertOk()->getContent();

            $this->assertStringContainsString('<nav class="rail"', $html,
                "{$chapter['label']} drops out of the About layout.");
        }
    }

    public function test_the_work_chapter_is_a_summary_that_hands_off_to_the_full_listing(): void
    {
        // Six more projects, so the chapter has to choose rather than print
        // the lot: one lead, five brief, and a link to everything.
        for ($i = 2; $i <= 8; $i++) {
            PortfolioProject::create([
                'title' => "System {$i}", 'slug' => "system-{$i}",
                'description' => 'Something delivered.', 'sort_order' => $i,
            ]);
        }

        $this->get(route('portfolio.work'))->assertOk()
            ->assertSee('National Livestock Traceability')
            ->assertSee('Ministry of Agriculture')
            ->assertSee('System 6')
            ->assertDontSee('System 8')                       // beyond the top six
            ->assertSee(route('portfolio.projects.index'), false);

        $this->get(route('portfolio.projects.index'))->assertOk()
            ->assertSee('System 8')
            ->assertSee(route('portfolio.work'), false);
    }
}
