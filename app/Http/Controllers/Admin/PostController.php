<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(): View
    {
        return view('admin.posts.index', [
            'items' => Post::with('author')->latest('created_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.posts.form', ['item' => new Post]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = Post::create($this->validated($request) + ['author_id' => $request->user()->id]);
        $this->storeCover($request, $post);

        return redirect()->route('admin.posts.index')->with('success', 'Post created.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', ['item' => $post]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $post->update($this->validated($request));
        $this->storeCover($request, $post);

        return redirect()->route('admin.posts.index')->with('success', 'Post updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        if ($post->cover_image) {
            Storage::disk('public')->delete($post->cover_image);
        }
        $post->delete();

        return back()->with('success', 'Post deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'slug' => 'nullable|string|max:220|alpha_dash',
            'excerpt' => 'nullable|string|max:400',
            'body' => 'required|string',
            'category' => 'nullable|string|max:80',
            'tags' => 'nullable|string|max:250',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['tags'] = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($data['tags'] ?? ''))
        )));

        unset($data['cover']);

        return $data;
    }

    private function storeCover(Request $request, Post $post): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        if ($post->cover_image) {
            Storage::disk('public')->delete($post->cover_image);
        }

        $post->update(['cover_image' => $request->file('cover')->store('posts', 'public')]);
    }
}
