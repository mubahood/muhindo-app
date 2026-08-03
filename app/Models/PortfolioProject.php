<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioProject extends Model
{
    protected $fillable = ['client',
        'title', 'slug', 'description', 'tags', 'highlights',
        'cover_image', 'external_link', 'is_featured', 'sort_order',
        'problem', 'approach', 'mechanics', 'stack', 'constraints', 'role', 'period',
    ];

    /**
     * The system's screenshot.
     *
     * These are drawn rather than captured: the real screens hold livestock
     * registries, patient records and human-rights case files, none of which
     * can be published. Each one is the actual screen a user of that system
     * works in, redrawn in the site's own two inks — so the set reads as one
     * body of work, and nothing anybody's data appears in a portfolio.
     *
     * A real cover uploaded through the admin wins, if there ever is one.
     */
    public function screenshotUrl(): ?string
    {
        if ($this->cover_image) {
            return asset('storage/'.$this->cover_image);
        }

        $drawn = "images/systems/{$this->slug}.svg";

        return is_file(public_path($drawn)) ? asset($drawn) : null;
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'highlights' => 'array',
            'mechanics' => 'array',
            'stack' => 'array',
            'constraints' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
