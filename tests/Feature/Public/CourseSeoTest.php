<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structured data on a course page.
 *
 * Two things worth defending: it describes the course fully and truthfully, and
 * it never claims a rating for a course nobody has reviewed — fabricated
 * ratings are the one piece of structured data Google actively penalises.
 */
class CourseSeoTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $overrides = []): Course
    {
        // $overrides FIRST. PHP's array union keeps the left-hand key on a
        // clash, so defaults-plus-overrides silently discards every override —
        // which made the paid-course case quietly test a free one.
        return Course::factory()->create($overrides + [
            'is_published' => true,
            'title' => 'Web Application Security Essentials',
            'description' => 'You can build it — now learn to **protect** it, with *real* code.',
            'tagline' => null,
            'outcomes' => ['Spot SQL injection', 'Write PHP that resists it'],
            'level' => 'intermediate',
            'course_number' => 8,
            'price' => 0,
            'cover_image' => 'https://example.test/images/courses/x.png',
        ]);
    }

    /** @return array<string,mixed> The Course node from the page. */
    private function courseNode(Course $course): array
    {
        $html = (string) $this->get(route('courses.show', $course))->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        foreach ($m[1] as $json) {
            $node = json_decode($json, true);
            if (($node['@type'] ?? null) === 'Course') {
                return $node;
            }
        }

        $this->fail('no Course JSON-LD node on the page');
    }

    public function test_the_course_node_describes_the_course_properly(): void
    {
        $node = $this->courseNode($this->course());

        $this->assertSame('Web Application Security Essentials', $node['name']);
        $this->assertSame('08', $node['courseCode']);
        $this->assertSame('intermediate', $node['educationalLevel']);
        $this->assertSame('en', $node['inLanguage']);
        $this->assertTrue($node['isAccessibleForFree']);
        $this->assertSame(['Spot SQL injection', 'Write PHP that resists it'], $node['teaches']);
        $this->assertStringContainsString('images/courses/x.png', $node['image']);
        $this->assertSame('Muhindo Mubaraka', $node['provider']['name']);
    }

    public function test_the_indexed_description_is_complete_and_carries_no_markdown(): void
    {
        $node = $this->courseNode($this->course());

        // cardTagline() truncates at 110 characters for a card. A schema
        // description ending in "..." is what a search engine would show.
        $this->assertStringNotContainsString('...', $node['description']);
        $this->assertStringContainsString('with real code', $node['description']);
        $this->assertStringNotContainsString('**', $node['description']);
        $this->assertStringNotContainsString('*real*', $node['description']);
    }

    public function test_no_rating_is_claimed_for_a_course_nobody_has_reviewed(): void
    {
        $node = $this->courseNode($this->course());

        // A rating node with no reviews behind it is fabricated structured
        // data, and a lie about a course nobody has taken.
        $this->assertArrayNotHasKey('aggregateRating', $node);
    }

    public function test_the_node_carries_no_null_values(): void
    {
        // A key set to null is not an absent key — it is invalid JSON-LD and
        // validators flag it.
        $node = $this->courseNode($this->course(['cover_image' => null, 'outcomes' => []]));

        foreach ($node as $key => $value) {
            $this->assertNotNull($value, "{$key} was emitted as null");
        }
    }

    public function test_a_paid_course_is_not_advertised_as_free(): void
    {
        $node = $this->courseNode($this->course(['price' => '250000.00', 'currency' => 'UGX']));

        $this->assertFalse($node['isAccessibleForFree']);
        $this->assertSame('250000.00', $node['offers']['price']);
        $this->assertSame('UGX', $node['offers']['priceCurrency']);
    }

    public function test_the_sitemap_lists_every_published_course(): void
    {
        $this->course();
        Course::factory()->create(['is_published' => false, 'title' => 'A hidden draft']);

        $xml = (string) $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString(route('courses.show', Course::firstWhere('course_number', 8)), $xml);
        $this->assertStringNotContainsString('a-hidden-draft', $xml);
    }

    // ── The card, after the owner asked for it to be quieter ────────────────

    public function test_the_card_shows_only_its_lesson_count_and_price(): void
    {
        $this->course(['category' => 'Security', 'level' => 'intermediate']);

        $html = (string) $this->get(route('courses.index'))->assertOk()->getContent();

        // The level and category badges floated over the artwork; the owner
        // asked for them gone, leaving the two facts people scan by.
        $this->assertStringNotContainsString('c-badge', $html);
        $this->assertStringContainsString('c-facts', $html);
        $this->assertStringContainsString('c-price', $html);
    }

    public function test_the_cover_is_not_desaturated_at_rest(): void
    {
        // These are commissioned two-ink covers in the brand's own colours.
        // Greying them out until hover hid the thing they were made to show.
        $css = (string) $this->get(route('courses.index'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/\.c-media img\{[^}]*grayscale/',
            $css,
            'the cover must render in its own colours without hovering'
        );
    }
}
