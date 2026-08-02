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

    public function test_the_menu_offers_the_top_level_sections_in_order(): void
    {
        $labels = array_column(SiteNav::items(), 'label');

        // Order is the message: learning first, then who he is, then what can
        // be bought, then the writing.
        $this->assertSame(['Learn', 'About Me', 'Projects for sale', 'Blog'], $labels);
    }

    public function test_the_about_panel_carries_every_page_about_him(): void
    {
        $about = collect(SiteNav::items())->firstWhere('label', 'About Me');

        $this->assertSame(
            ['About me', 'My work', 'My CV', 'Qualifications', 'Skills & experience', 'Research', 'Gallery', 'Consultancy'],
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

    public function test_the_action_buttons_survive_signing_in(): void
    {
        // Signing in used to replace them with "My Projects" and "Sign out".
        // The actions are what the header is for, and they still apply: a
        // student can hire, a client can enrol.
        $user = \App\Models\User::factory()->create(['role' => 'client', 'is_client' => true]);

        $header = $this->headerOf($this->actingAs($user)->get(route('home')));

        $this->assertStringContainsString('>Hire Me<', $header);
        $this->assertStringContainsString('>Learn<', $header);
    }

    public function test_account_navigation_sits_behind_the_avatar_not_beside_the_actions(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'client', 'is_client' => true]);

        $header = $this->headerOf($this->actingAs($user)->get(route('home')));
        $beforeMenu = substr($header, 0, strpos($header, 'class="acct') ?: strlen($header));

        $this->assertStringContainsString('class="acct desk"', $header);
        $this->assertStringNotContainsString('My Projects', $beforeMenu);
        $this->assertStringNotContainsString('Sign out', $beforeMenu);
    }

    public function test_a_guest_is_offered_the_actions_and_a_quiet_way_in(): void
    {
        $header = $this->headerOf($this->get(route('home')));

        $this->assertStringContainsString('>Hire Me<', $header);
        // Sign in is a link, not a button — it must not compete with the actions.
        $this->assertStringContainsString('class="signin desk"', $header);
    }

    public function test_no_destination_appears_twice_in_one_menu(): void
    {
        // Two links to one page in a single menu is a wayfinding smell — it was
        // why the top-level "Projects" was folded into the About panel, where
        // "My work" already points at the same page.
        /* Only destinations that are actually rendered as links count. An item
           with children renders as a <button> that opens its panel, so its own
           url is never a link and sharing one with a child is not a duplicate. */
        $urls = [];
        foreach (SiteNav::items() as $item) {
            if (empty($item['children'])) {
                $urls[] = $item['url'];

                continue;
            }
            foreach ($item['children'] as $child) {
                $urls[] = $child['url'];
            }
        }

        $this->assertSame(array_unique($urls), $urls, 'a destination is listed more than once in the menu');
    }

    public function test_the_old_section_urls_still_resolve(): void
    {
        // Anything already linked or indexed must not start 404ing.
        $this->get('/shop')->assertRedirect('/projects-for-sale');
        $this->get('/insights')->assertRedirect('/blog');
    }

    private function headerOf(\Illuminate\Testing\TestResponse $response): string
    {
        $html = (string) $response->assertOk()->getContent();

        return substr($html, strpos($html, '<div class="hd-r">') ?: 0,
            (strpos($html, '</header>') ?: strlen($html)) - (strpos($html, '<div class="hd-r">') ?: 0));
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
