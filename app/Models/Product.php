<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A digital product: an e-book, template, toolkit or downloadable resource. */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'type', 'summary', 'description', 'category', 'tags', 'cover_image',
        'price', 'compare_at_price', 'currency', 'file_path', 'file_name', 'file_bytes',
        'external_url', 'is_published', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public const TYPES = ['ebook' => 'E-book', 'template' => 'Template', 'toolkit' => 'Toolkit', 'resource' => 'Resource'];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name, $product->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $n = 1;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.++$n;
        }

        return $slug;
    }

    /** @param  Builder<Product>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @return HasMany<ProductLicense, $this> */
    public function licenses(): HasMany
    {
        return $this->hasMany(ProductLicense::class);
    }

    public function isFree(): bool
    {
        return bccomp((string) $this->price, '0', 2) <= 0;
    }

    /** True only when the struck-through price is genuinely higher than what is charged. */
    public function isDiscounted(): bool
    {
        return $this->compare_at_price !== null
            && bccomp((string) $this->compare_at_price, (string) $this->price, 2) > 0;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? Str::headline((string) $this->type);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? asset('storage/'.$this->cover_image) : null;
    }

    /** Human file size for the buyer, so "what am I actually getting" is answered before paying. */
    public function fileSize(): ?string
    {
        if (! $this->file_bytes) {
            return null;
        }

        $mb = $this->file_bytes / 1048576;

        return $mb >= 1 ? round($mb, 1).' MB' : max(1, (int) round($this->file_bytes / 1024)).' KB';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
