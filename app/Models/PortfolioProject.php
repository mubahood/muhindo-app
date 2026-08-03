<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioProject extends Model
{
    protected $fillable = ['client',
        'title', 'slug', 'description', 'tags', 'highlights',
        'cover_image', 'external_link', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'highlights' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
