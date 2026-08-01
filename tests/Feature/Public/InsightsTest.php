<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Insights: published work is readable, unpublished work is not. */
class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_the_index_lists_published_articles_newest_first(): void
    {
        Post::factory()->create(['title' => 'Older piece', 'published_at' => now()->subMonth()]);
        Post::factory()->create(['title' => 'Newer piece', 'published_at' => now()->subDay()]);

        $response = $this->get(route('insights.index'))->assertOk();
        $html = (string) $response->getContent();

        $this->assertLessThan(
            strpos($html, 'Older piece'),
            strpos($html, 'Newer piece'),
            'the most recent article must lead the listing'
        );
    }

    public function test_a_draft_is_invisible_to_the_public(): void
    {
        $draft = Post::factory()->draft()->create(['title' => 'Not finished yet']);

        $this->get(route('insights.index'))->assertOk()->assertDontSee('Not finished yet');
        // 404, not 403: a different status would confirm the URL exists.
        $this->get(route('insights.show', $draft))->assertNotFound();
    }

    public function test_an_article_scheduled_for_the_future_stays_hidden_until_then(): void
    {
        $scheduled = Post::factory()->create(['title' => 'Embargoed', 'published_at' => now()->addWeek()]);

        $this->get(route('insights.index'))->assertOk()->assertDontSee('Embargoed');
        $this->get(route('insights.show', $scheduled))->assertNotFound();
    }

    public function test_an_article_renders_its_markdown(): void
    {
        $post = Post::factory()->create([
            'title' => 'On shipping',
            'body' => "## A heading\n\nSome **bold** copy.",
        ]);

        $this->get(route('insights.show', $post))
            ->assertOk()
            ->assertSee('<h2>A heading</h2>', false)
            ->assertSee('<strong>bold</strong>', false);
    }

    public function test_markdown_cannot_inject_script_into_the_page(): void
    {
        // The renderer is configured to escape raw HTML; CommonMark's own
        // default is to pass it straight through.
        $post = Post::factory()->create([
            'body' => '<script>alert(1)</script> and [x](javascript:alert(2))',
        ]);

        $html = (string) $this->get(route('insights.show', $post))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_reading_time_and_excerpt_are_derived_when_left_blank(): void
    {
        $post = Post::factory()->create([
            'body' => str_repeat('word ', 400),
            'excerpt' => null,
        ]);

        $this->assertSame(2, $post->read_minutes, '400 words at 200wpm is two minutes');
        $this->assertNotNull($post->excerpt);
    }

    public function test_a_derived_excerpt_contains_prose_not_markdown_syntax(): void
    {
        // The body is Markdown, so stripping HTML tags alone leaves "##" behind
        // and every card on the listing opens with syntax.
        $post = Post::factory()->create([
            'body' => "## A heading\n\nThe first sentence of the article.",
            'excerpt' => null,
        ]);

        $this->assertStringStartsWith('A heading The first sentence', $post->excerpt);
        $this->assertStringNotContainsString('#', $post->excerpt);
    }

    public function test_publishing_always_stamps_a_date(): void
    {
        // "Published" and "has a publish date" must never disagree, or the
        // listing's ordering silently drops the post.
        $post = Post::factory()->draft()->create();
        $this->assertNull($post->published_at);

        $post->update(['is_published' => true]);

        $this->assertNotNull($post->fresh()->published_at);
    }

    public function test_slugs_never_collide(): void
    {
        $a = Post::create(['title' => 'Same Title', 'body' => 'one']);
        $b = Post::create(['title' => 'Same Title', 'body' => 'two']);

        $this->assertNotSame($a->slug, $b->slug);
        $this->assertSame('same-title', $a->slug);
    }

    public function test_published_articles_appear_in_the_sitemap(): void
    {
        $post = Post::factory()->create();
        $draft = Post::factory()->draft()->create();

        $xml = (string) $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString(route('insights.show', $post), $xml);
        $this->assertStringNotContainsString(route('insights.show', $draft), $xml);
    }

    public function test_an_admin_can_write_and_publish_a_post(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.posts.store'), [
            'title' => 'How I structure a Laravel service layer',
            'body' => 'Some genuinely useful words about services.',
            'category' => 'Engineering',
            'tags' => 'laravel, architecture',
            'is_published' => '1',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 1000),
        ])->assertRedirect(route('admin.posts.index'));

        $post = Post::firstOrFail();
        $this->assertSame('how-i-structure-a-laravel-service-layer', $post->slug);
        $this->assertSame(['laravel', 'architecture'], $post->tags);
        $this->assertTrue($post->is_published);
        Storage::disk('public')->assertExists($post->cover_image);

        $this->get(route('insights.show', $post))->assertOk()->assertSee('genuinely useful words');
    }

    public function test_the_public_cannot_reach_the_authoring_screens(): void
    {
        $this->get(route('admin.posts.index'))->assertRedirect();
        $this->get(route('admin.posts.create'))->assertRedirect();
    }
}
