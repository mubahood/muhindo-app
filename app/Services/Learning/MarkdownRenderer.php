<?php

namespace App\Services\Learning;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Util\HtmlFilter;

/**
 * §7.4 — server-side rendering for `content_format=markdown` lessons.
 * league/commonmark defaults to *allowing* raw HTML passthrough and unsafe
 * (`javascript:`) links — the opposite of what "sanitized, no raw HTML
 * passthrough" requires, so both are explicitly overridden here rather than
 * trusted as safe defaults.
 */
class MarkdownRenderer
{
    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => HtmlFilter::ESCAPE,
            'allow_unsafe_links' => false,
        ]);
    }

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
