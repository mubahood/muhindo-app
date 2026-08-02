<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A call to action's hover label names where it goes, so the same destination
 * must be described the same way everywhere. Three different labels had already
 * grown for the course catalogue alone ("Start Learning", "Browse the courses",
 * "See the full catalogue"), which teaches a visitor that they are three
 * different places.
 */
class CallToActionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** The one label each destination is allowed to use. */
    private const CANONICAL = [
        'courses.index' => 'Start Learning',
        'start-a-project' => 'Hire Muhindo',
        'portfolio.work' => 'See the projects',
        'shop.index' => 'See the source code',
        // 'contact' has no call to action of its own any more — the buttons
        // that used to say "Get in touch" now say "Hire Me" and land on the
        // brief form, because a label promising hiring should not open a
        // generic contact box.

    ];

    public function test_each_destination_uses_a_single_hover_label_across_the_site(): void
    {
        $found = [];

        foreach ($this->bladeFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (! str_contains($source, 'cta-b')) {
                continue;
            }

            // Each anchor is matched whole first. Scanning for a route and then
            // for the next cta-b lets the match run past </a> and pair one
            // link's destination with a different link's label — which is
            // exactly the false positive this replaced.
            preg_match_all('/<a\s+(?:(?!<\/a>).)*?<\/a>/s', $source, $anchors);

            foreach ($anchors[0] as $anchor) {
                if (! str_contains($anchor, 'cta-b')) {
                    continue;
                }
                if (! preg_match('/route\(\'([a-z.\-]+)\'/', $anchor, $r)) {
                    continue;
                }
                if (! isset(self::CANONICAL[$r[1]])) {
                    continue;
                }
                if (preg_match('/<span class="cta-b"[^>]*>(.*?)<\/span>/s', $anchor, $l)) {
                    $found[$r[1]][trim(strip_tags($l[1]))] = basename($file);
                }
            }
        }

        foreach (self::CANONICAL as $route => $expected) {
            $this->assertArrayHasKey($route, $found, "no call to action points at {$route}");
            $this->assertSame(
                [$expected],
                array_keys($found[$route]),
                "{$route} must use exactly one hover label everywhere; found: "
                    .json_encode($found[$route])
            );
        }
    }

    public function test_the_hover_label_is_hidden_from_screen_readers(): void
    {
        // Both labels are in the DOM at once. Without aria-hidden a screen
        // reader announces two names for one control.
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/<span class="cta-b"([^>]*)>/', $html, $matches);

        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $attributes) {
            $this->assertStringContainsString('aria-hidden="true"', $attributes);
        }
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
