<?php

namespace Tests\Feature\Public;

use App\Models\GalleryPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The About page: one column, and photographs that open where they are.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private function photos(int $count = 5): void
    {
        // Created directly: GalleryPhoto has no factory, and the real rows are
        // imported from disk rather than generated.
        for ($i = 1; $i <= $count; $i++) {
            GalleryPhoto::create([
                'title' => "Photograph {$i}",
                'caption' => "What was happening in photograph {$i}.",
                'alt' => "Muhindo at work, {$i}",
                'category' => 'work',
                'path' => "gallery/photo-{$i}.jpg",
                'thumb_path' => "gallery/thumb-{$i}.jpg",
                'width' => 1600, 'height' => 1200, 'bytes' => 100000,
                'is_published' => true, 'is_featured' => true, 'sort_order' => $i,
            ]);
        }
    }

    public function test_a_photograph_opens_in_place_rather_than_navigating_away(): void
    {
        $this->photos();

        $html = (string) $this->get(route('portfolio.about'))->assertOk()->getContent();

        // Buttons, not links. Clicking a photograph should show that
        // photograph; being sent to another page is a different action nobody
        // asked for.
        $this->assertStringContainsString('class="ab-shot"', $html);
        $this->assertStringContainsString('id="lightbox"', $html);
        $this->assertStringContainsString('lb-caption', $html);

        // The strip itself must contain no anchors, the sidebar rail links to
        // the gallery legitimately, so counting the route across the whole page
        // proves nothing. What matters is that a thumbnail is not a link.
        preg_match('#<div class="ab-shots"[^>]*>(.*?)</div>#s', $html, $strip);

        $this->assertNotEmpty($strip, 'the photo strip is missing');
        $this->assertStringNotContainsString('<a ', $strip[1], 'a thumbnail must not be a link');
        $this->assertSame(4, substr_count($strip[1], '<button'));
    }

    public function test_the_page_holds_its_rail_layout_all_the_way_down(): void
    {
        $this->photos();

        $html = (string) $this->get(route('portfolio.about'))->assertOk()->getContent();

        // It used to break out of the rail halfway, so the sidebar stopped and
        // the rest became unrelated full-width bands.
        $this->assertSame(1, substr_count($html, 'class="rail-layout"'));

        foreach (['What I actually do', 'In pictures'] as $section) {
            $this->assertStringContainsString($section, $html);
        }

        // The generic trailing block is gone; the page ends on the two buttons.
        $this->assertStringNotContainsString('Want the details?', $html);
    }

    public function test_the_organisations_section_appears_once_there_are_any(): void
    {
        // Conditional on real content. An empty "Trusted by" band claiming
        // nothing would be worse than no band.
        $this->get(route('portfolio.about'))->assertOk()
            ->assertDontSee("Organisations I've delivered for", false);

        \App\Support\Settings::set('portfolio.clients', json_encode(['Ministry of Agriculture', 'Uganda Wildlife Authority']));

        $this->get(route('portfolio.about'))->assertOk()
            ->assertSee("Organisations I've delivered for", false)
            ->assertSee('Uganda Wildlife Authority');
    }

    public function test_the_chapter_ends_with_hire_and_the_next_one(): void
    {
        // Where it points is AboutChapterSequenceTest's job; that it ends at
        // all, and inside the column rather than as a full-width band, is this
        // page's.
        $this->get(route('portfolio.about'))->assertOk()
            ->assertSee('Hire Me')
            ->assertSee('ch-end', false);
    }

    public function test_the_gallery_still_uses_the_same_viewer(): void
    {
        $this->photos();

        // The viewer was extracted from the gallery; the gallery must not have
        // lost it in the move.
        $this->get(route('gallery.index'))->assertOk()
            ->assertSee('id="lightbox"', false)
            ->assertSee('gal-item', false);
    }
}
