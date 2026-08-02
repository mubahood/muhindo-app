<?php

namespace Tests\Feature\Shell;

use Tests\TestCase;

/**
 * Guards one specific mistake.
 *
 * The public layout carries a single large inline stylesheet. A terminal cursor
 * was styled as a bare `.caret`, and the navigation chevron and the account-menu
 * chevron were both already using that class name — so both header icons turned
 * into a blinking gold block.
 *
 * There is no general rule to assert here. Plenty of classes in this sheet are
 * deliberately styled globally and then refined inside a component (.btn,
 * .eyebrow, .lead, .ph), which is ordinary cascade rather than a collision, and
 * the two are not reliably distinguishable by pattern. So this pins the actual
 * bug: the cursor keeps a name of its own, and the chevron stays scoped.
 */
class StylesheetScopeTest extends TestCase
{
    public function test_the_terminal_cursor_does_not_reuse_the_chevron_class(): void
    {
        $css = $this->layoutCss();

        $this->assertStringContainsString('.term-caret{', $css, 'the terminal cursor must have its own class');
        $this->assertDoesNotMatchRegularExpression(
            '/(?:^|[,}])\s*\.caret\s*\{/m',
            $css,
            '.caret must never be styled without a parent — the nav and account chevrons both use it'
        );
    }

    public function test_both_chevrons_are_styled_only_in_context(): void
    {
        $css = $this->layoutCss();

        $this->assertStringContainsString('.nav-link .caret{', $css);
        $this->assertStringContainsString('.acct-trigger .caret{', $css);
    }

    private function layoutCss(): string
    {
        $blade = (string) file_get_contents(resource_path('views/layouts/marketing.blade.php'));
        preg_match('/<style>(.*?)<\/style>/s', $blade, $m);

        return $m[1] ?? '';
    }
}
