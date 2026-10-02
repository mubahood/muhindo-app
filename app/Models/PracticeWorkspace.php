<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeWorkspace extends Model
{
    protected $fillable = [
        'user_id', 'title', 'html_content', 'css_content', 'js_content', 'bootstrap_enabled', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['bootstrap_enabled' => 'boolean', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
