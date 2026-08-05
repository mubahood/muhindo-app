<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * public-w1, of PUBLIC_SITE_PLAN.md: /e-learning is the canonical public URL for
 * the course catalogue; /courses/* is kept as a permanent redirect for anyone who
 * already bookmarked or indexed the old URL. Route names stay `courses.*` (zero
 * churn to call sites), only the URI prefix changed.
 */
class ELearningRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_e_learning_index_is_the_canonical_url(): void
    {
        $this->get('/e-learning')->assertOk();
    }

    public function test_e_learning_show_is_the_canonical_url(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $this->get("/e-learning/{$course->slug}")->assertOk();
    }

    public function test_old_courses_index_permanently_redirects_to_e_learning(): void
    {
        $response = $this->get('/courses');

        $response->assertRedirect('/e-learning');
        $response->assertStatus(301);
    }

    public function test_old_courses_show_permanently_redirects_to_e_learning(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $response = $this->get("/courses/{$course->slug}");

        $response->assertRedirect("/e-learning/{$course->slug}");
        $response->assertStatus(301);
    }

    public function test_old_courses_checkout_permanently_redirects_to_e_learning(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $response = $this->get("/courses/{$course->slug}/checkout");

        $response->assertRedirect("/e-learning/{$course->slug}/checkout");
        $response->assertStatus(301);
    }

    public function test_a_nonexistent_old_course_slug_still_redirects_before_404ing(): void
    {
        // Abuse path: a slug that never existed must still redirect (not 404 on the old
        // path). The redirect is a plain URI rewrite, unaware of whether the course
        // resolves; the new canonical route is what 404s, which is correct.
        $response = $this->get('/courses/does-not-exist');

        $response->assertStatus(301);
        $response->assertRedirect('/e-learning/does-not-exist');

        $this->get('/e-learning/does-not-exist')->assertNotFound();
    }

    public function test_home_page_nav_links_to_the_canonical_e_learning_route(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('courses.index'), false);
        $response->assertSee('e&#8209;Learning', false);
    }

    public function test_home_page_shows_published_courses_in_the_e_learning_strip(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'title' => 'Laravel From Scratch']);
        Course::factory()->create(['is_published' => false, 'title' => 'Unpublished Draft Course']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Laravel From Scratch');
        $response->assertDontSee('Unpublished Draft Course');
        $response->assertSee(route('courses.show', $course), false);
    }

    public function test_home_page_e_learning_strip_is_absent_with_no_published_courses(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('I train computer programming');
    }
}
