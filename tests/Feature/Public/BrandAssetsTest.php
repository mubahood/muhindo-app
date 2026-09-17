<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The images that represent this site to everybody who has not opened it yet.
 *
 * Every one of them was wrong. The favicon was a law firm's logo, the share
 * card was a medical clinic's, the iPhone home-screen icon was a medical
 * cross, /favicon.ico was zero bytes, and every email the site sent carried
 * the law firm's masthead. All of it left over from other projects in the same
 * workspace, all of it published for months, and none of it visible from
 * inside the site: a favicon is the one asset you stop seeing.
 *
 * So the assertions here are about files existing and being what they claim,
 * not about markup being present.
 */
class BrandAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** Every asset the metadata points at must exist and be a real image. */
    public function test_the_brand_assets_exist_and_are_images(): void
    {
        $expected = [
            'favicon.png' => [48, 48],
            'images/icon-180.png' => [180, 180],
            'images/icon-192.png' => [192, 192],
            'images/icon-512.png' => [512, 512],
            'images/og.png' => [1200, 630],
            'images/og-solavia.png' => [1200, 630],
        ];

        foreach ($expected as $path => [$w, $h]) {
            $file = public_path($path);
            $this->assertFileExists($file);

            $size = @getimagesize($file);
            $this->assertNotFalse($size, "{$path} is not a readable image");
            $this->assertSame([$w, $h], [$size[0], $size[1]], "{$path} is the wrong size");
        }
    }

    /**
     * /favicon.ico is fetched by convention whether anything links it or not.
     * The file that shipped was zero bytes.
     */
    public function test_the_ico_is_not_empty(): void
    {
        $ico = public_path('favicon.ico');

        $this->assertFileExists($ico);
        $this->assertGreaterThan(1000, filesize($ico), 'the .ico is empty or a stub');
        // "\0\0\1\0" is the ICO magic number.
        $this->assertSame("\x00\x00\x01\x00", file_get_contents($ico, false, null, 0, 4));
    }

    /** Nothing may point at the files that belonged to other companies. */
    public function test_no_page_references_another_company_s_artwork(): void
    {
        $gone = ['logo-square.png', 'logo-192.png', 'logo-icon.png', 'images/logo.png', 'images/favicon.png'];

        foreach (['/', '/solavia', '/e-learning', '/about', '/work'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            foreach ($gone as $file) {
                $this->assertStringNotContainsString($file, $html, "{$path} still points at {$file}");
            }
        }

        foreach ($gone as $file) {
            $this->assertFileDoesNotExist(public_path(ltrim($file, '/')));
        }
    }

    /* The metadata ---------------------------------------------------------- */

    public function test_every_public_page_carries_a_share_card(): void
    {
        foreach (['/', '/e-learning', '/about', '/work', '/source-code'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            preg_match('#<meta property="og:image" content="([^"]+)"#', $html, $m);
            $this->assertNotEmpty($m[1] ?? '', "{$path} has no og:image");
            $this->assertStringStartsWith('http', $m[1], 'it has to be absolute, scrapers do not resolve relative paths');
            $this->assertStringContainsString('og.png', $m[1]);
        }
    }

    /** The company pages are about the company, so they share the company card. */
    public function test_the_company_pages_share_the_company_card(): void
    {
        foreach (['/solavia', '/solavia/products', '/solavia/terms',
            '/solavia/privacy', '/solavia/refund-policy', '/solavia/contact'] as $path) {
            $this->get($path)->assertOk()->assertSee('images/og-solavia.png', false);
        }
    }

    /**
     * Facebook and LinkedIn lay the card out before the image finishes
     * downloading, and without these the first share of a URL frequently
     * renders with no picture at all.
     */
    public function test_the_card_declares_its_dimensions_and_a_description(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringContainsString('<meta property="og:image:height" content="630">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="en_UG">', $html);

        preg_match('#<meta property="og:image:alt" content="([^"]+)"#', $html, $m);
        $this->assertNotEmpty($m[1] ?? '');
        $this->assertStringContainsString('SOLAVIA GROUP LIMITED', $m[1]);
    }

    /** A course shares its own cover, not the site default. */
    public function test_a_course_page_shares_its_own_cover(): void
    {
        $course = \App\Models\Course::factory()->create([
            'title' => 'Flutter Mobile App Development',
            'is_published' => true,
            'cover_image' => 'images/courses/mastering-flutter-ui.png',
        ]);

        $html = (string) $this->get(route('courses.show', $course))->assertOk()->getContent();
        preg_match('#<meta property="og:image" content="([^"]+)"#', $html, $m);

        $this->assertNotEmpty($m[1] ?? '');
        $this->assertStringContainsString('mastering-flutter-ui.png', $m[1],
            'the course shared the site default instead of its own cover');
    }

    /** The masthead on every email the site sends. */
    public function test_the_email_masthead_is_ours(): void
    {
        $this->assertFileExists(public_path('images/logo-horizontal.png'));

        $size = getimagesize(public_path('images/logo-horizontal.png'));
        $this->assertSame(840, $size[0]);
        $this->assertGreaterThan(2.5, $size[0] / $size[1], 'a masthead should be wide, not square');
    }

    public function test_the_installable_icons_are_ours(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertStringStartsWith('images/icon-', $icon['src']);
            $this->assertFileExists(public_path($icon['src']));
        }
    }
}
