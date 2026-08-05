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
            // Rendered through the project's hardened converter, which escapes
            // raw HTML and refuses javascript: links, CommonMark allows both
            // by default.
            'html' => $this->markdown->toHtml($post->body),
            'more' => Post::published()->whereKeyNot($post->id)
                ->latest('published_at')->limit(3)->get(),
        ]);
    }
}
