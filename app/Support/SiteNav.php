<?php

namespace App\Support;

/**
 * The public navigation, defined once.
 *
 * The desktop bar, the mega panel and the mobile sheet all render from this,
 * so the three cannot drift, which is exactly what had happened before, where
 * the mobile menu still listed pages the desktop bar had renamed and had lost
 * others entirely.
 *
 * It lives in PHP rather than a Blade partial because a partial cannot define
 * variables for the view that includes it, and because a route list is worth
 * asserting against in a test.
 */
class SiteNav
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function items(): array
    {
        return [
            [
                'label' => 'Learn',
                'url' => route('courses.index'),
                'match' => ['courses.*'],
                'icon' => 'fa-graduation-cap',
                // The gold dot: the one thing to notice first.
                'flag' => true,
            ],
            [
                'label' => 'About Me',
                'url' => route('portfolio.about'),
                'match' => [
                    'portfolio.about', 'portfolio.cv', 'portfolio.education',
                    'portfolio.skills', 'portfolio.experience', 'portfolio.research', 'gallery.index',
                    'portfolio.services', 'hire', 'propose', 'portfolio.work', 'portfolio.projects.index', 'portfolio.project',
                    'portfolio.products',
                ],
                'icon' => 'fa-user',
                'blurb' => 'Who I am, what I have built, and what I am researching.',
                'children' => [
                    ['label' => 'About me', 'url' => route('portfolio.about'), 'icon' => 'fa-user',
                        'desc' => 'The short version, how I work and who I work with.',
                        'match' => ['portfolio.about']],
                    ['label' => 'My work', 'url' => route('portfolio.work'), 'icon' => 'fa-diagram-project',
                        'desc' => 'Systems I have delivered, with case studies.',
                        'match' => ['portfolio.work', 'portfolio.projects.index', 'portfolio.project']],
                    ['label' => 'My CV', 'url' => route('portfolio.cv'), 'icon' => 'fa-file-lines',
                        'desc' => 'The full record on one page. Print or save as PDF.',
                        'match' => ['portfolio.cv']],
                    ['label' => 'Qualifications', 'url' => route('portfolio.education'), 'icon' => 'fa-award',
                        'desc' => 'Degrees, certifications and where they came from.',
                        'match' => ['portfolio.education']],
                    ['label' => 'Skills & experience', 'url' => route('portfolio.skills'), 'icon' => 'fa-layer-group',
                        'desc' => 'The toolbox, and where each tool has been used.',
                        'match' => ['portfolio.skills', 'portfolio.experience']],
                    ['label' => 'Research', 'url' => route('portfolio.research'), 'icon' => 'fa-flask',
                        'desc' => 'Current MSc work on distributed systems and ML.',
                        'match' => ['portfolio.research']],
                    ['label' => 'Gallery', 'url' => route('gallery.index'), 'icon' => 'fa-images',
                        'desc' => 'The work, the desk and the people behind it.',
                        'match' => ['gallery.*']],
                    ['label' => 'Consultancy', 'url' => route('portfolio.services'), 'icon' => 'fa-handshake',
                        'desc' => 'How I can help, and how to start a project.',
                        'match' => ['portfolio.services', 'hire', 'propose']],
                ],
            ],
            [
                'label' => 'Source code',
                'url' => route('shop.index'),
                'match' => ['shop.*', 'cart.*', 'checkout.*'],
                'icon' => 'fa-basket-shopping',
            ],
            [
                'label' => 'Blog',
                'url' => route('insights.index'),
                'match' => ['insights.*'],
                'icon' => 'fa-pen-nib',
            ],
            /*
             * The company, as a section of its own rather than a single link.
             *
             * Last in the bar because a visitor wants the products first, but
             * present on every page because the reader who needs it most,
             * somebody verifying the business behind a payment, must reach it
             * from wherever they landed, and must reach the exact page they
             * came for without hunting: the registration details, the product
             * list, or the refund terms.
             */
            [
                'label' => 'Company',
                'url' => route('solavia.home'),
                'match' => ['solavia.*'],
                'icon' => 'fa-building',
                'blurb' => 'SOLAVIA GROUP LIMITED, the registered company behind this site and everything on it.',
                'children' => [
                    ['label' => 'About SOLAVIA', 'url' => route('solavia.home'), 'icon' => 'fa-building',
                        'desc' => 'Who we are, the registered details, and who runs the company.',
                        'match' => ['solavia.home']],
                    ['label' => 'Our products', 'url' => route('solavia.products'), 'icon' => 'fa-cubes',
                        'desc' => 'Everything we build and operate, and what each one costs.',
                        'match' => ['solavia.products']],
                    ['label' => 'Terms of Service', 'url' => route('solavia.terms'), 'icon' => 'fa-file-contract',
                        'desc' => 'What you agree to when you buy or use our products.',
                        'match' => ['solavia.terms']],
                    ['label' => 'Privacy Policy', 'url' => route('solavia.privacy'), 'icon' => 'fa-user-shield',
                        'desc' => 'What we collect, why, and who we share it with.',
                        'match' => ['solavia.privacy']],
                    ['label' => 'Refund policy', 'url' => route('solavia.refund-policy'), 'icon' => 'fa-rotate-left',
                        'desc' => 'When we refund, how to ask, and how long it takes.',
                        'match' => ['solavia.refund-policy']],
                    ['label' => 'Contact us', 'url' => route('solavia.contact'), 'icon' => 'fa-envelope',
                        'desc' => 'Address, phone, WhatsApp and a message form.',
                        'match' => ['solavia.contact']],
                ],
            ],
        ];
    }

    /**
     * The legal pages, defined here for the same reason as everything else.
     *
     * These were three hand-written links in one footer column and nothing at
     * all in the mobile sheet, so on a phone the refund policy could not be
     * reached from the menu by anybody who had not been sent the URL. A
     * payment provider checks that on a phone.
     *
     * They are kept apart from items() because they are not navigation: they
     * belong in the footer's bottom rule and at the foot of the mobile sheet,
     * not in the header bar or the mega panel.
     *
     * @return list<array{label:string, url:string}>
     */
    public static function legal(): array
    {
        return [
            ['label' => 'Company', 'url' => route('solavia.home')],
            ['label' => 'Products', 'url' => route('solavia.products')],
            ['label' => 'Terms', 'url' => route('solavia.terms')],
            ['label' => 'Privacy', 'url' => route('solavia.privacy')],
            ['label' => 'Refund policy', 'url' => route('solavia.refund-policy')],
            ['label' => 'Contact', 'url' => route('solavia.contact')],
        ];
    }

    /** Every destination the menu can reach, for smoke-testing that none 404s. */
    public static function urls(): array
    {
        $urls = [];
        foreach (self::items() as $item) {
            $urls[] = $item['url'];
            foreach ($item['children'] ?? [] as $child) {
                $urls[] = $child['url'];
            }
        }

        foreach (self::legal() as $item) {
            $urls[] = $item['url'];
        }

        return array_values(array_unique($urls));
    }
}
