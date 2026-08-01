<?php

namespace Tests\Feature\Public;

use App\Support\SiteNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu is the one component every visitor uses, and the one most likely to
 * rot: a page gets renamed and the link that pointed at it quietly 404s.
 */
class SiteNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_destination_in_the_menu_resolves(): void
    {
        foreach (SiteNav::urls() as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_menu_offers_the_four_top_level_sections(): void
    {
        $labels = array_column(SiteNav::items(), 'label');

        $this->assertSame(['Learn', 'About Me', 'Projects', 'Consultancy'], $labels);
    }

    public function test_the_about_panel_carries_every_page_about_him(): void
    {
        $about = collect(SiteNav::items())->firstWhere('label', 'About Me');

        $this->assertSame(
            ['About me', 'My work', 'My CV', 'Qualifications', 'Skills & experience', 'Research'],
            array_column($about['children'], 'label')
        );
    }

    public function test_the_desktop_and_mobile_menus_render_the_same_destinations(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        // Both are rendered from SiteNav, so each destination must appear at
        // least twice — once in the bar or its panel, once in the mobile sheet.
        foreach (SiteNav::urls() as $url) {
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($html, 'href="'.e($url).'"'),
                "{$url} must be reachable from both the desktop bar and the mobile menu"
            );
        }
    }

    public function test_a_child_page_lights_up_its_parent_section(): void
    {
        // Someone deep in /cv still needs to see which section they are in.
        $html = (string) $this->get(route('portfolio.cv'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="nav-link on"[^>]*>\s*About Me/',
            $html,
            'the About Me trigger must show as active while a child page is open'
        );
    }

    public function test_the_action_buttons_name_the_outcome_on_hover(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('>Hire Me<', $html);
        $this->assertStringContainsString('Hire Muhindo', $html);
        $this->assertStringContainsString('Start Learning', $html);
    }

    public function test_the_mega_panel_is_operable_without_a_mouse(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        // A <button> trigger is focusable and CSS opens the panel on
        // :focus-within, so the panel works from the keyboard with no script.
        $this->assertMatchesRegularExpression('/<button[^>]*class="nav-link[^"]*"[^>]*aria-expanded="false"[^>]*aria-controls="mega-about-me"/', $html);
        $this->assertStringContainsString('id="mega-about-me"', $html);
    }

    public function test_the_cv_page_is_assembled_from_live_records(): void
    {
        $response = $this->get(route('portfolio.cv'));

        $response->assertOk()
            ->assertSee('Experience')
            ->assertSee('Qualifications')
            ->assertSee('Skills')
            // Printing is the delivery mechanism, so the control must be there.
            ->assertSee('window.print()', false);
    }
}
