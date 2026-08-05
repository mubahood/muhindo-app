<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Search Console verification.
 *
 * Worth a test because of how this fails: the tag disappears, nothing breaks,
 * no page looks wrong, and the first anyone knows is a Search Console property
 * quietly reverting to unverified weeks later and taking the search data with
 * it.
 */
class SiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_google_token_is_in_the_head_of_a_public_page(): void
    {
        $token = config('seo.verifications.google-site-verification');

        $this->assertNotEmpty($token, 'the committed token is what survives a deploy');

        $this->get('/')->assertOk()
            ->assertSee('<meta name="google-site-verification" content="'.$token.'">', false);
    }

    public function test_it_is_on_every_public_page_not_only_the_home_page(): void
    {
        // A property verified against /e-learning fails if the tag lives at
        // the root alone, and which URL was submitted is easy to forget.
        foreach (['/', '/about', '/e-learning', '/source-code', '/work'] as $path) {
            $this->get($path)->assertOk()->assertSee('google-site-verification', false);
        }
    }

    public function test_a_provider_with_no_token_renders_nothing(): void
    {
        $this->get('/')->assertOk()->assertDontSee('name="msvalidate.01"', false);
    }

    public function test_a_token_added_later_needs_no_code_change(): void
    {
        // Set as a whole array: two of these provider names contain a dot, and
        // config() would read that as nesting.
        config(['seo.verifications' => ['msvalidate.01' => 'BING123']]);

        $this->get('/')->assertOk()
            ->assertSee('<meta name="msvalidate.01" content="BING123">', false);
    }

    public function test_a_malformed_token_cannot_take_the_site_down(): void
    {
        // What a dotted config() write actually produces. Skipped, not printed.
        config(['seo.verifications' => ['msvalidate' => ['01' => 'BING123'], 'yandex-verification' => null]]);

        $this->get('/')->assertOk()->assertDontSee('name="msvalidate"', false);
    }
}
