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
            'contact' => ['/contact'],
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
            ->assertSee(route('contact'), false);
    }

    public function test_the_about_family_subnav_cross_links_every_page(): void
    {
        $response = $this->get('/about');

        $response->assertOk()
            ->assertSee(route('portfolio.services'), false)
            ->assertSee(route('portfolio.experience'), false)
            ->assertSee(route('portfolio.education'), false)
            ->assertSee(route('portfolio.research'), false)
            ->assertSee(route('portfolio.products'), false);
    }

    public function test_submitting_the_contact_form_redirects_back_to_the_dedicated_contact_page(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jane Client',
            'email' => 'jane@example.com',
            'message' => 'Hello.',
        ]);

        $response->assertRedirect(route('contact'));
    }
}
