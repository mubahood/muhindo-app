<?php

namespace App\Models;

use App\Services\Learning\MarkdownRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** An Insights article. */
class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'category', 'tags',
        'cover_image', 'is_published', 'published_at', 'read_minutes', 'author_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Derived fields are filled here rather than in the controller so every
        // path that writes a post (admin form, seeder, future import) produces
        // the same record.
        static::saving(function (Post $post) {
            if (blank($post->slug)) {
                $post->slug = static::uniqueSlug($post->title, $post->id);
            }

            if (blank($post->excerpt)) {
                // The body is Markdown, so stripping HTML tags alone leaves the
                // syntax behind and the listing reads "## A heading The first
                // sentence...". Render first, then strip, then collapse the
                // whitespace the block elements leave behind.
                $text = strip_tags(app(MarkdownRenderer::class)->toHtml($post->body));
                $post->excerpt = Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 180);
            }

            $post->read_minutes = max(1, (int) ceil(str_word_count(strip_tags((string) $post->body)) / 200));

            // "Published" and "has a publish date" must never disagree.
            if ($post->is_published && $post->published_at === null) {
                $post->published_at = now();
            }
        });
    }

    /** Appends -2, -3 ... only when needed, and ignores the post's own row when editing. */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $n = 1;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.++$n;
        }

        return $slug;
    }

    /** @param  Builder<Post>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
