<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PortfolioProject;
use Illuminate\Http\Response;

/**
 * /sitemap.xml generated from real content (never a static, go-stale file) and
 * /robots.txt generated from the app's actual URL (the static file it replaced pointed
 * at a completely different project's domain, inherited from the true-doctor ancestor,
 * a hand-maintained robots.txt is exactly how that kind of drift happens again).
 */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            // Trailing slash avoids an extra redirect hop some server configs add for a bare
            // Subdirectory path (found crawling the live sitemap during the walkthrough),
            // harmless and equivalent at a plain domain root, where most servers serve both forms identically.
            ['loc' => route('home').'/', 'lastmod' => null],
            ['loc' => route('courses.index'), 'lastmod' => null],
            ['loc' => route('portfolio.work'), 'lastmod' => null],
            ['loc' => route('portfolio.projects.index'), 'lastmod' => null],
            ['loc' => route('portfolio.about'), 'lastmod' => null],
            ['loc' => route('portfolio.services'), 'lastmod' => null],
            ['loc' => route('portfolio.skills'), 'lastmod' => null],
            ['loc' => route('portfolio.experience'), 'lastmod' => null],
            ['loc' => route('portfolio.education'), 'lastmod' => null],
            ['loc' => route('portfolio.research'), 'lastmod' => null],
            ['loc' => route('portfolio.cv'), 'lastmod' => null],
            ['loc' => route('insights.index'), 'lastmod' => null],
            ['loc' => route('gallery.index'), 'lastmod' => null],
            ['loc' => route('shop.index'), 'lastmod' => null],
            ['loc' => route('portfolio.products'), 'lastmod' => null],
            ['loc' => route('privacy'), 'lastmod' => null],
            ['loc' => route('terms'), 'lastmod' => null],
        ];

        foreach (\App\Models\Product::published()->get(['slug', 'updated_at']) as $product) {
            $urls[] = ['loc' => route('shop.show', $product), 'lastmod' => $product->updated_at];
        }

        foreach (\App\Models\Post::published()->get(['slug', 'updated_at']) as $post) {
            $urls[] = ['loc' => route('insights.show', $post), 'lastmod' => $post->updated_at];
        }

        foreach (Course::where('is_published', true)->get(['slug', 'updated_at']) as $course) {
            $urls[] = ['loc' => route('courses.show', $course), 'lastmod' => $course->updated_at];
        }

        foreach (PortfolioProject::all(['id', 'slug', 'updated_at']) as $project) {
            $urls[] = ['loc' => route('portfolio.project', $project), 'lastmod' => $project->updated_at];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /learn',
            'Disallow: /portal',
            'Disallow: /dashboard',
            'Disallow: /e-learning/*/checkout',
            'Disallow: /courses/*/checkout',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain');
    }
}
