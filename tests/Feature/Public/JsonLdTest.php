<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w6, of PUBLIC_SITE_PLAN.md: structured data on the landing page and course detail pages. */
class JsonLdTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_emits_person_and_organization_json_ld(): void
    {
        $this->seed(\Database\Seeders\PortfolioContentSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('"@type":"Person"', false);
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('Muhindo Mubaraka');
    }

    public function test_a_course_page_emits_course_and_breadcrumb_json_ld(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'title' => 'Laravel From Scratch', 'price' => 150000]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('"@type":"Course"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('"price":"150000.00"', false);
        $response->assertSee('"priceCurrency":"UGX"', false);
    }

    public function test_a_course_page_with_no_faq_content_emits_no_faqpage_node(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertDontSee('"@type":"FAQPage"', false);
    }

    public function test_a_course_page_with_faq_content_emits_a_faqpage_node(): void
    {
        $this->seed(\Database\Seeders\PortfolioContentSeeder::class);
        $course = Course::factory()->create(['is_published' => true]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('"@type":"FAQPage"', false);
        $response->assertSee('How do I pay?');
    }
}
