<?php

namespace App\Support;

/**
 * The public navigation, defined once.
 *
 * The desktop bar, the mega panel and the mobile sheet all render from this,
 * so the three cannot drift — which is exactly what had happened before, where
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
                    'portfolio.services', 'start-a-project', 'portfolio.work', 'portfolio.project',
                    'portfolio.products',
                ],
                'icon' => 'fa-user',
                'blurb' => 'Who I am, what I have built, and what I am researching.',
                'children' => [
                    ['label' => 'About me', 'url' => route('portfolio.about'), 'icon' => 'fa-user',
                        'desc' => 'The short version — how I work and who I work with.',
                        'match' => ['portfolio.about']],
                    ['label' => 'My work', 'url' => route('portfolio.work'), 'icon' => 'fa-diagram-project',
                        'desc' => 'Systems I have delivered, with case studies.',
                        'match' => ['portfolio.work', 'portfolio.project']],
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
                        'match' => ['portfolio.services', 'start-a-project']],
                ],
            ],
            [
                'label' => 'Projects for sale',
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

        return array_values(array_unique($urls));
    }
}
