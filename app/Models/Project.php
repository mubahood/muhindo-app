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

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Staff working on this project, beyond the owner.
     *
     * @return BelongsToMany<User, $this>
     */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_team')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<ProjectTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProjectNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->latest();
    }

    /** @return HasMany<ProjectUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    /** @return HasMany<ProjectDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->latest();
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOwnedByUser(User $user): bool
    {
        return $this->client?->user_id === $user->id;
    }
}
