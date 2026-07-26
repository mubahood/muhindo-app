<?php

namespace Tests\Unit;

use App\Services\Learning\MarkdownRenderer;
use Tests\TestCase;

/** §7.4 — sanitized, no raw HTML passthrough (league/commonmark's own defaults are the opposite of this). */
class MarkdownRendererTest extends TestCase
{
    private function renderer(): MarkdownRenderer
    {
        return app(MarkdownRenderer::class);
    }

    public function test_it_renders_headings_and_paragraphs(): void
    {
        $html = $this->renderer()->toHtml("# Title\n\nSome text.");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<p>Some text.</p>', $html);
    }

    public function test_it_renders_fenced_code_blocks(): void
    {
        $html = $this->renderer()->toHtml("```php\necho 'hi';\n```");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
    }

    public function test_it_renders_images(): void
    {
        $html = $this->renderer()->toHtml('![alt text](https://example.com/image.png)');

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="https://example.com/image.png"', $html);
    }

    public function test_raw_html_is_escaped_not_passed_through(): void
    {
        $html = $this->renderer()->toHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_javascript_links_are_rejected(): void
    {
        $html = $this->renderer()->toHtml('[click me](javascript:alert(1))');

        $this->assertStringNotContainsString('href="javascript:alert(1)"', $html);
    }
}
