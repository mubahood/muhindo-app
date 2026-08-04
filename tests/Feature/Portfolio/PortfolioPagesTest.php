<?php

namespace Tests\Feature\Portfolio;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The portfolio site redesign: every former home-page section is now its own page. */
class PortfolioPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'work' => ['/work'],
            'about' => ['/about'],
            'services' => ['/services'],
            'skills' => ['/skills'],
            'experience' => ['/experience'],
            'education' => ['/education'],
            'research' => ['/research'],
            'products' => ['/products'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_renders(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    public function test_the_header_nav_links_all_resolve_to_real_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('portfolio.work'), false)
            ->assertSee(route('portfolio.about'), false)
            ->assertSee(route('portfolio.skills'), false)
            ->assertSee(route('hire'), false);
    }

    public function test_the_about_rail_cross_links_every_chapter(): void
    {
        $response = $this->get('/about');

        // The rail, not a subnav strip: /experience and /products are reached
        // through their chapters rather than being listed here.
        $response->assertOk()
            ->assertSee(route('portfolio.services'), false)
            ->assertSee(route('portfolio.work'), false)
            ->assertSee(route('portfolio.cv'), false)
            ->assertSee(route('portfolio.education'), false)
            ->assertSee(route('portfolio.skills'), false)
            ->assertSee(route('portfolio.research'), false)
            ->assertSee(route('gallery.index'), false);
    }

    /**
     * The generic contact form is gone: "get in touch" produced messages
     * nobody could act on, and hiring goes through an account now. Its URL
     * still resolves, onto the journey that replaced it, because it was
     * linked from every page for months.
     */
    public function test_the_old_contact_url_leads_to_hiring(): void
    {
        $this->get('/contact')->assertRedirect(route('hire'));
    }
}
