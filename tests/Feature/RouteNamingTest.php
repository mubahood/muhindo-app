<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: `routes/api.php`'s `Route::apiResource('courses', ...)` (and
 * `invoices`) had no name override, so Laravel defaulted them to the bare
 * `courses.index`/`courses.show` (`invoices.index`/`invoices.show`) — the
 * exact same names the public web catalogue (and the admin invoice list)
 * already use. Because both routes shared a name, `route('courses.show', ...)`
 * resolved to whichever was registered last, silently sending every
 * `route('courses.show')`/`route('courses.index')` call site in the app (site
 * nav "Courses" link, course cards, the admin "View public page" link, the
 * free-preview page) to the JSON API endpoint instead of the HTML page.
 * Found via a brand-new test, not a code review — pinned here so it can't
 * regress silently again.
 */
class RouteNamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_show_resolves_to_the_public_html_page_not_the_json_api(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('/api/v1/', route('courses.show', $course));
    }

    public function test_courses_index_resolves_to_the_public_html_page_not_the_json_api(): void
    {
        $response = $this->get(route('courses.index'));

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('/api/v1/', route('courses.index'));
    }

    public function test_the_api_course_routes_are_named_with_an_api_prefix(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('api.courses.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('api.courses.show'));
    }
}
