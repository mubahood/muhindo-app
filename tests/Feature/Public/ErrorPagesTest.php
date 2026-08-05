<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w6, of PUBLIC_SITE_PLAN.md: branded 404/500 pages, never Laravel's bare default. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wrong_course_slug_shows_the_branded_404_page_not_laravels_default(): void
    {
        $response = $this->get('/e-learning/this-course-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('That page wandered off');
        $response->assertSee(route('courses.index'), false);
        $response->assertDontSee('NOT FOUND', false);
    }

    public function test_the_404_page_offers_a_way_back_to_browsing_courses_and_home(): void
    {
        $response = $this->get('/this-path-does-not-exist-at-all');

        $response->assertNotFound();
        $response->assertSee('Browse courses');
        $response->assertSee('Back to home');
    }

    public function test_the_500_error_view_renders_a_branded_human_message(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('Something went wrong on my end', $html);
        $this->assertStringContainsString('Back to home', $html);
    }
}
