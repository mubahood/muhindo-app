<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** When project_id is null this is a standalone personal task (owner's own to-do list). */
class ProjectTask extends Model
{
    protected $fillable = [
        'project_id', 'title', 'description', 'status', 'due_date',
        'completed_at', 'assigned_to', 'created_by', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}
