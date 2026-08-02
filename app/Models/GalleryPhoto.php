<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A photograph in the gallery. */
class GalleryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'caption', 'alt', 'category', 'path', 'webp_path', 'thumb_path',
        'width', 'height', 'bytes', 'is_published', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /** @param  Builder<GalleryPhoto>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function thumbUrl(): string
    {
        return asset('storage/'.($this->thumb_path ?: $this->path));
    }

    public function webpUrl(): ?string
    {
        return $this->webp_path ? asset('storage/'.$this->webp_path) : null;
    }

    /**
     * The aspect ratio the grid reserves for this tile. Falls back to 4:3 only
     * when dimensions were never recorded, so a missing value degrades to a
     * sensible box rather than a collapsed one.
     */
    public function ratio(): string
    {
        return $this->width && $this->height ? "{$this->width} / {$this->height}" : '4 / 3';
    }

    /** Alt text is required for accessibility; fall back to the title, never to empty. */
    public function altText(): string
    {
        return $this->alt ?: $this->title;
    }
}
