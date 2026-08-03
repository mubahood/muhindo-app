<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The catalogue card: what it shows at a glance, and what stays clickable. */
class CourseCatalogueUxTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        $course = Course::factory()->create(array_merge([
            'title' => 'Laravel From Scratch', 'is_published' => true,
            'level' => 'intermediate', 'category' => 'Web Development',
            'price' => '150000.00', 'currency' => 'UGX',
            'outcomes' => ['Build a database-backed app', 'Deploy it properly'],
        ], $attributes));

        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0, 'duration_minutes' => 30]);

        return $course;
    }

    public function test_the_card_carries_only_its_length_and_price(): void
    {
        $this->course();

        // Level and category badges used to float over the artwork. Once the
        // covers became commissioned two-ink illustrations they were competing
        // with the thing the card exists to show, and the owner asked for them
        // gone. Length and price are what people actually scan by; both stay,
        // and both are still on the detail page.
        $this->get(route('courses.index'))->assertOk()
            ->assertSee('UGX 150,000')
            ->assertSee('c-facts', false)
            ->assertDontSee('c-badge', false);
    }

    public function test_a_free_course_says_free_rather_than_a_price(): void
    {
        $this->course(['price' => '0.00']);

        $this->get(route('courses.index'))->assertOk()->assertSee('c-price free', false)->assertSee('Free');
    }

    public function test_the_hover_panel_offers_outcomes_and_a_real_link(): void
    {
        $course = $this->course();

        $html = (string) $this->get(route('courses.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Build a database-backed app', $html);
        // The call to action must be an anchor. It was a <span> styled as a
        // button — it looked clickable and did nothing, and on touch, where the
        // panel is always open, it is the card\'s main action.
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="'.preg_quote(route('courses.show', $course), '/').'"[^>]*class="btn gold sm c-cta"/',
            $html
        );
    }

    public function test_the_hero_uses_the_same_compact_header_as_every_other_page(): void
    {
        // It was the tall centred home-page hero, which made the catalogue open
        // with a screen of nothing before the first course.
        $html = (string) $this->get(route('courses.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="page-hero tex-glow"', $html);
        $this->assertStringNotContainsString('class="hero tex-grid tex-glow"', $html);
    }
}
