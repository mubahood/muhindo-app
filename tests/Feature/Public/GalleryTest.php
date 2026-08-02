<?php

namespace Tests\Feature\Public;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    private function photo(array $attributes = []): GalleryPhoto
    {
        return GalleryPhoto::create(array_merge([
            'title' => 'Deep work',
            'caption' => 'Most of a system gets built in hours like this one.',
            'alt' => 'Working at a desk with a monitor of code',
            'category' => 'Workspace',
            'path' => 'gallery/deep-work.jpg',
            'webp_path' => 'gallery/deep-work.webp',
            'thumb_path' => 'gallery/thumbs/deep-work.jpg',
            'width' => 1600, 'height' => 1201, 'bytes' => 190000,
            'is_published' => true, 'sort_order' => 1,
        ], $attributes));
    }

    public function test_the_gallery_shows_published_photographs_in_order(): void
    {
        $this->photo(['title' => 'Second', 'path' => 'gallery/b.jpg', 'sort_order' => 2]);
        $this->photo(['title' => 'First', 'path' => 'gallery/a.jpg', 'sort_order' => 1]);

        $html = (string) $this->get(route('gallery.index'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Second'), strpos($html, 'First'));
    }

    public function test_a_hidden_photograph_is_not_shown(): void
    {
        $this->photo(['title' => 'Alternate take', 'path' => 'gallery/alt.jpg', 'is_published' => false]);

        $this->get(route('gallery.index'))->assertOk()->assertDontSee('Alternate take');
    }

    public function test_every_tile_declares_its_own_aspect_ratio(): void
    {
        // Without this the grid has no shape until the images land and the
        // whole page reflows as each one arrives.
        $this->photo(['width' => 1302, 'height' => 1736]);

        $this->get(route('gallery.index'))->assertOk()->assertSee('aspect-ratio:1302 / 1736', false);
    }

    public function test_a_photograph_without_recorded_dimensions_still_gets_a_box(): void
    {
        $photo = $this->photo(['width' => null, 'height' => null]);

        $this->assertSame('4 / 3', $photo->ratio());
    }

    public function test_every_photograph_carries_alt_text(): void
    {
        $this->photo(['alt' => null, 'title' => 'A titled photo']);

        // Alt must never be empty: falling back to the title is worse than a
        // description but far better than an unlabelled image.
        $this->get(route('gallery.index'))->assertOk()->assertSee('alt="A titled photo"', false);
    }

    public function test_a_modern_format_is_offered_with_a_fallback(): void
    {
        $this->photo();

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('type="image/webp"', false)
            ->assertSee('gallery/thumbs/deep-work.jpg', false);
    }

    public function test_the_gallery_can_be_filtered_by_category(): void
    {
        $this->photo(['title' => 'At the desk', 'path' => 'gallery/desk.jpg', 'category' => 'Workspace']);
        $this->photo(['title' => 'On campus', 'path' => 'gallery/campus.jpg', 'category' => 'Study']);

        $this->get(route('gallery.index', ['category' => 'Study']))
            ->assertOk()->assertSee('On campus')->assertDontSee('At the desk');
    }

    public function test_the_gallery_is_reachable_from_the_menu(): void
    {
        $this->assertContains(route('gallery.index'), \App\Support\SiteNav::urls());
    }

    public function test_an_admin_can_manage_photographs(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();
        $photo = $this->photo();

        $this->actingAs($admin)->get(route('admin.gallery.index'))->assertOk()->assertSee('Deep work');
        $this->actingAs($admin)->get(route('admin.gallery.edit', $photo))->assertOk();
    }

    public function test_the_public_cannot_manage_photographs(): void
    {
        $this->get(route('admin.gallery.index'))->assertRedirect();
    }
}
