<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\Learning\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Insights, the public reading side of the blog. */
class InsightController extends Controller
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    public function index(Request $request): View
    {
        $category = $request->string('category')->trim()->value();

        $posts = Post::published()
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('insights.index', [
            'posts' => $posts,
            'jsonLd' => [
                \App\Support\Seo\Schema::itemList('Articles',
                    collect($posts->items())->map(fn ($p) => route('insights.show', $p))->all()),
                \App\Support\Seo\Schema::breadcrumb(['Blog' => route('insights.index')]),
            ],
            'categories' => Post::published()->whereNotNull('category')
                ->distinct()->orderBy('category')->pluck('category'),
            'activeCategory' => $category,
        ]);
    }

    public function show(Post $post): View
    {
        // A draft must 404 for the public exactly as a missing post does.
        // anything else confirms the URL of unpublished work.
        if (! $post->is_published || $post->published_at?->isFuture()) {
            throw new NotFoundHttpException;
        }

        return view('insights.show', [
            'post' => $post->load('author'),
            // The article already describes itself. What it lacked was its
            // place: a breadcrumb is what turns the grey URL line in a result
            // into a readable path.
            /*
             * Built here, not in the Blade file.
             *
             * Laravel has an @context Blade directive, so a literal '@context'
             * key written inside a view is compiled as that directive and the
             * page ships a <script type="application/ld+json"> containing raw
             * PHP instead of JSON. It was live on this page and on /solavia.
             * In PHP the string is just a string.
             */
            'jsonLd' => [
                array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'BlogPosting',
                    'headline' => $post->title,
                    'description' => $post->excerpt,
                    'datePublished' => $post->published_at?->toIso8601String(),
                    'dateModified' => $post->updated_at?->toIso8601String(),
                    'url' => route('insights.show', $post),
                    'image' => $post->cover_image ? asset('storage/'.$post->cover_image) : null,
                    'inLanguage' => 'en',
                    'author' => ['@type' => 'Person', 'name' => $post->author?->name ?? config('app.name')],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => config('company.name'),
                        'logo' => ['@type' => 'ImageObject', 'url' => asset('images/icon-512.png')],
                    ],
                ]),
                \App\Support\Seo\Schema::breadcrumb([
                    'Blog' => route('insights.index'),
                    $post->title => route('insights.show', $post),
                ]),
            ],
            'breadcrumb' => \App\Support\Seo\Schema::breadcrumb([
                'Blog' => route('insights.index'),
                $post->title => route('insights.show', $post),
            ]),
            // Rendered through the project's hardened converter, which escapes
            // raw HTML and refuses javascript: links, CommonMark allows both
            // by default.
            'html' => $this->markdown->toHtml($post->body),
            'more' => Post::published()->whereKeyNot($post->id)
                ->latest('published_at')->limit(3)->get(),
        ]);
    }
}
