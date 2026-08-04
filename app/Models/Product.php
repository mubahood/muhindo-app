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
        'whats_inside', 'stack', 'requirements', 'install_guide', 'demo_url', 'version',
        'license_terms',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'whats_inside' => 'array',
            'stack' => 'array',
            'requirements' => 'array',
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

    /**
     * Whether this can actually be handed over.
     *
     * The single most damaging thing this shop could do is take somebody's
     * money for a file that is not there. Publication is a decision the owner
     * makes; deliverability is a fact about the row, and every path that moves
     * money checks it — the basket, the checkout and the buy buttons.
     *
     * A missing file on disk counts as undeliverable even when file_path is
     * set: a database row is not a download.
     */
    public function isDeliverable(): bool
    {
        if (filled($this->external_url)) {
            return true;
        }

        return filled($this->file_path)
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->file_path);
    }

    /** Why it cannot be handed over, for the admin — never shown to a buyer. */
    public function undeliverableReason(): ?string
    {
        if ($this->isDeliverable()) {
            return null;
        }

        if (blank($this->file_path)) {
            return 'no file uploaded and no external link';
        }

        return "the file is recorded as {$this->file_path} but is not on disk";
    }

    /** @param  Builder<Product>  $query */
    public function scopeBuyable(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNotNull('external_url')->orWhereNotNull('file_path'));
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

    /**
     * The item thumbnail.
     *
     * Drawn rather than photographed, for the same reason the case-study
     * screenshots are: what a buyer needs to see in one second is the
     * wordmark, the promise, the stack and the interface — and a real
     * screengrab of an admin panel delivers none of them at card size.
     *
     * An uploaded cover always wins, so replacing one of these is an upload
     * and nothing else.
     */
    public function coverUrl(): ?string
    {
        if ($this->cover_image) {
            return asset('storage/'.$this->cover_image);
        }

        $drawn = "images/products/{$this->slug}.svg";

        return is_file(public_path($drawn)) ? asset($drawn) : null;
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

    /** Whether there is anything worth opening an install page for. */
    public function hasInstallGuide(): bool
    {
        return filled($this->install_guide);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
