<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The web app manifest.
 *
 * It shipped as another project's file entirely: anybody who added this site
 * to their phone home screen got an app called "Cryptocoinex Trading
 * Simulator" pointing at a /trade page that does not exist here. Nothing
 * renders it, so nothing ever looked wrong.
 */
class WebManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_describes_this_site(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('Muhindo Mubaraka', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path($icon['src']), $icon['src'].' is referenced but missing');
        }
    }

    public function test_it_carries_no_trace_of_another_project(): void
    {
        $raw = strtolower((string) file_get_contents(public_path('manifest.json')));

        foreach (['cryptocoinex', 'trading', 'virtual funds'] as $stray) {
            $this->assertStringNotContainsString($stray, $raw);
        }
    }

    /**
     * A manifest nothing links to is a file the browser never requests.
     *
     * There is no HTTP assertion here on purpose: the file is static and the
     * web server answers it before Laravel is reached, so the router returns
     * 404 in tests while Apache returns 200 in reality. What can go wrong, and
     * did, is the tag being absent.
     */
    public function test_every_public_page_points_a_browser_at_it(): void
    {
        foreach (['/', '/e-learning', '/work'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('rel="manifest"', false)
                ->assertSee('rel="apple-touch-icon"', false);
        }
    }
}
