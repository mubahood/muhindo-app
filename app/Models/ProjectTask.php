<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A unit of work.
 *
 * When project_id is null this is a standalone personal task (owner's own
 * to-do list). Both kinds live in this table on purpose: the question "what am
 * I doing today" does not care whose work it is, and splitting them would mean
 * every day view had to union two tables and every bug had to be fixed twice.
 *
 * A task carrying `repeat_every` is a TEMPLATE, not a to-do. It is never
 * completed and never shown in a list; the nightly generator copies it. See
 * scopeActionable(), which is the one place that distinction is enforced.
 */
class ProjectTask extends Model
{
    public const PRIORITIES = ['low', 'normal', 'high'];

    public const REPEATS = ['daily', 'weekdays', 'weekly'];

    protected $fillable = [
        'project_id', 'title', 'description', 'status', 'priority', 'due_date',
        'repeat_every', 'repeat_until', 'repeats_from_id',
        'completed_at', 'assigned_to', 'created_by', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'repeat_until' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<self, $this> The template a generated copy came from. */
    public function template(): BelongsTo
    {
        return $this->belongsTo(self::class, 'repeats_from_id');
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isTemplate(): bool
    {
        return $this->repeat_every !== null;
    }

    /**
     * Unfinished and its date has passed.
     *
     * A task due today is NOT overdue; the day is not over. Getting that wrong
     * paints the whole list red every morning, and a list that is always red
     * stops being read.
     */
    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_date !== null
            && $this->due_date->lt(Carbon::today());
    }

    /** Who this is for, in the words the day view uses. */
    public function ownerLabel(): string
    {
        return $this->project?->client?->name ?? 'Personal';
    }

    /**
     * Everything that is a real to-do.
     *
     * Templates are excluded here rather than at each call site, because they
     * look exactly like ordinary tasks and every list that forgot to exclude
     * them would show a phantom item that can never be finished.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActionable(Builder $query): void
    {
        $query->whereNull('repeat_every');
    }

    /** @param Builder<$this> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->actionable()->where('status', '!=', 'done');
    }

    /** @param Builder<$this> $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today());
    }

    /** @param Builder<$this> $query */
    public function scopeDueOn(Builder $query, Carbon|string $date): void
    {
        $query->open()->whereDate('due_date', $date);
    }
}
