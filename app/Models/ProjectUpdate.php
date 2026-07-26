<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The client-visible progress log entry. */
class ProjectUpdate extends Model
{
    protected $fillable = ['project_id', 'user_id', 'update_text', 'percent_complete'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
