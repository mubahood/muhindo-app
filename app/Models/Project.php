<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string|null $budget
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'project_number', 'title', 'description', 'client_id', 'category',
        'status', 'priority', 'start_date', 'due_date', 'completed_date',
        'budget', 'currency', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Staff working on this project, beyond the owner. */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_team')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->latest();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOwnedByUser(User $user): bool
    {
        return $this->client?->user_id === $user->id;
    }
}
