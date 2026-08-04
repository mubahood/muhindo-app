<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w2 — §2.2 of PUBLIC_SITE_PLAN.md: filters/sort/search are server-rendered and URL-driven. */
class ELearningListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_published_courses(): void
    {
        Course::factory()->create(['title' => 'Visible Course', 'is_published' => true]);
        Course::factory()->create(['title' => 'Hidden Draft', 'is_published' => false]);

        $response = $this->get(route('courses.index'));

        $response->assertOk()->assertSee('Visible Course')->assertDontSee('Hidden Draft');
    }

    public function test_it_filters_by_category(): void
    {
        Course::factory()->create(['title' => 'Web Course', 'is_published' => true, 'category' => 'Web Development']);
        Course::factory()->create(['title' => 'Mobile Course', 'is_published' => true, 'category' => 'Mobile Development']);

        $response = $this->get(route('courses.index', ['category' => 'Web Development']));

        $response->assertOk()->assertSee('Web Course')->assertDontSee('Mobile Course');
    }

    public function test_it_filters_by_level(): void
    {
        Course::factory()->create(['title' => 'Beginner Course', 'is_published' => true, 'level' => 'beginner']);
        Course::factory()->create(['title' => 'Advanced Course', 'is_published' => true, 'level' => 'advanced']);

        $response = $this->get(route('courses.index', ['level' => 'advanced']));

        $response->assertOk()->assertSee('Advanced Course')->assertDontSee('Beginner Course');
    }

    public function test_it_filters_by_price(): void
    {
        Course::factory()->create(['title' => 'Free Course', 'is_published' => true, 'price' => 0]);
        Course::factory()->create(['title' => 'Paid Course', 'is_published' => true, 'price' => 50000]);

        $free = $this->get(route('courses.index', ['price' => 'free']));
        $free->assertSee('Free Course')->assertDontSee('Paid Course');

        $paid = $this->get(route('courses.index', ['price' => 'paid']));
        $paid->assertSee('Paid Course')->assertDontSee('Free Course');
    }

    public function test_it_searches_title_and_description(): void
    {
        Course::factory()->create(['title' => 'Laravel From Scratch', 'is_published' => true, 'description' => 'Build web apps.']);
        Course::factory()->create(['title' => 'Flutter Basics', 'is_published' => true, 'description' => 'Mobile apps.']);

        $response = $this->get(route('courses.index', ['q' => 'Laravel']));

        $response->assertOk()->assertSee('Laravel From Scratch')->assertDontSee('Flutter Basics');
    }

    public function test_price_low_to_high_sort_orders_correctly(): void
    {
        Course::factory()->create(['title' => 'Expensive', 'is_published' => true, 'price' => 200000]);
        Course::factory()->create(['title' => 'Cheap', 'is_published' => true, 'price' => 10000]);

        $response = $this->get(route('courses.index', ['sort' => 'price_asc']));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertLessThan(strpos($body, 'Expensive'), strpos($body, 'Cheap'));
    }

    public function test_an_empty_result_shows_a_clear_filters_and_contact_link(): void
    {
        Course::factory()->create(['title' => 'Only Course', 'is_published' => true, 'category' => 'Web Development']);

        $response = $this->get(route('courses.index', ['category' => 'Nonexistent Category']));

        $response->assertOk();
        $response->assertSee('No courses match those filters');
        $response->assertSee(route('courses.index'), false);
        $response->assertSee(route('hire'), false);
    }

    public function test_the_catalogue_is_paged_six_at_a_time(): void
    {
        /*
         * Six, deliberately small. Twenty-one courses on one page is a wall,
         * and a wall is a decision a visitor postpones — the owner asked for
         * the catalogue broken into groups small enough to actually finish
         * looking at.
         */
        Course::factory()->count(21)->create(['is_published' => true]);

        $this->assertCount(6, $this->get(route('courses.index'))->assertOk()->viewData('courses'));
        $this->assertCount(6, $this->get(route('courses.index', ['page' => 2]))->assertOk()->viewData('courses'));
        $this->assertCount(3, $this->get(route('courses.index', ['page' => 4]))->assertOk()->viewData('courses'));
    }

    public function test_trust_chips_reflect_real_counts(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = $course->modules()->create(['title' => 'M1', 'sort_order' => 0]);
        $module->lessons()->create(['title' => 'L1', 'sort_order' => 0, 'is_published' => true]);
        $module->lessons()->create(['title' => 'L2', 'sort_order' => 1, 'is_published' => true]);

        $response = $this->get(route('courses.index'));

        $response->assertOk();
        $response->assertSee('1 course');
        $response->assertSee('2 lessons');
    }
}
