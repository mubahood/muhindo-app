<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w6 — §6.3 of PUBLIC_SITE_PLAN.md: a real sitemap + robots.txt pointing at this app's own domain. */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sitemap_lists_static_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $response->assertSee(route('home'), false);
        $response->assertSee(route('courses.index'), false);
    }

    public function test_the_sitemap_lists_only_published_courses(): void
    {
        $visible = Course::factory()->create(['is_published' => true]);
        $hidden = Course::factory()->create(['is_published' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(route('courses.show', $visible), false);
        $response->assertDontSee(route('courses.show', $hidden), false);
    }

    public function test_robots_txt_points_at_this_apps_own_sitemap_not_a_different_domain(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee(route('sitemap'), false);
        $response->assertDontSee('true-doctor.online');
    }

    public function test_robots_txt_disallows_private_areas(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /admin');
        $response->assertSee('Disallow: /learn');
        $response->assertSee('Disallow: /portal');
        $response->assertSee('Disallow: /dashboard');
    }
}
