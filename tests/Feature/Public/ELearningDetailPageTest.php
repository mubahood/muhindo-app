<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w2 — §2.3 of PUBLIC_SITE_PLAN.md: the course detail sales page. */
class ELearningDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithCurriculum(array $attrs = []): Course
    {
        $course = Course::factory()->create(array_merge([
            'is_published' => true,
            'category' => 'Web Development',
            'tagline' => 'Learn to build real things.',
            'outcomes' => ['Build a real app', 'Write tests'],
            'requirements' => ['A computer'],
        ], $attrs));

        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Getting Started', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Intro Lesson', 'sort_order' => 0, 'is_published' => true, 'duration_minutes' => 12, 'is_free_preview' => true]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'Second Lesson', 'sort_order' => 1, 'is_published' => true, 'duration_minutes' => 20]);

        return $course;
    }

    public function test_it_shows_outcomes_requirements_and_curriculum(): void
    {
        $course = $this->courseWithCurriculum();

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('What you\'ll learn', false);
        $response->assertSee('Build a real app');
        $response->assertSee('Requirements');
        $response->assertSee('A computer');
        $response->assertSee('Getting Started');
        $response->assertSee('Intro Lesson');
        $response->assertSee('Second Lesson');
        $response->assertSee('12 min');
        $response->assertSee('20 min');
    }

    public function test_the_outcomes_section_is_hidden_when_there_are_none(): void
    {
        $course = $this->courseWithCurriculum(['outcomes' => null, 'requirements' => null]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertDontSee('What you\'ll learn', false);
        $response->assertDontSee('Requirements');
    }

    public function test_the_buy_box_shows_free_for_a_free_course(): void
    {
        $course = $this->courseWithCurriculum(['price' => 0]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk()->assertSeeInOrder(['Free', 'Enrol now']);
    }

    public function test_the_buy_box_shows_price_and_payment_icons_for_a_paid_course(): void
    {
        $course = $this->courseWithCurriculum(['price' => 150000, 'currency' => 'UGX']);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('UGX 150,000');
        $response->assertSee('MTN MoMo');
        $response->assertSee('Airtel Money');
        $response->assertSee('Secure payment via Flutterwave');
    }

    public function test_a_free_course_buy_box_never_shows_payment_icons(): void
    {
        $course = $this->courseWithCurriculum(['price' => 0]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk()->assertDontSee('Secure payment via Flutterwave');
    }

    public function test_the_faq_section_renders_from_settings(): void
    {
        $this->seed(\Database\Seeders\PortfolioContentSeeder::class);
        $course = $this->courseWithCurriculum();

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Frequently asked questions');
        $response->assertSee('How do I pay?');
    }

    public function test_the_breadcrumb_category_link_filters_the_listing(): void
    {
        $course = $this->courseWithCurriculum(['category' => 'Databases']);

        $show = $this->get(route('courses.show', $course));
        $show->assertOk()->assertSee(route('courses.index', ['category' => 'Databases']), false);

        $filtered = $this->get(route('courses.index', ['category' => 'Databases']));
        $filtered->assertOk()->assertSee($course->title);
    }
}
