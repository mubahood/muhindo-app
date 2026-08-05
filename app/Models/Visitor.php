<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One browser, for as long as its cookie survives.
 *
 * A visitor is not a person and does not pretend to be one until they sign in,
 * at which point user_id is set and every anonymous visit already recorded
 * becomes part of that person's history. That backfill is the whole point: the
 * interesting question is never "what did this customer do after registering",
 * it is "what were they reading for two weeks before they decided to".
 */
class Visitor extends Model
{
    protected $fillable = [
        'token', 'user_id', 'identified_at',
        'first_landing_path', 'first_referrer', 'first_source', 'first_medium', 'first_campaign',
        'last_country', 'last_city', 'last_device', 'last_browser', 'last_os', 'last_ip',
        'visits_count', 'page_views_count', 'events_count', 'engaged_seconds',
        'converted_at', 'revenue', 'is_bot', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'identified_at' => 'datetime',
            'converted_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_bot' => 'boolean',
            'revenue' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->latest('started_at');
    }

    /** @return HasMany<PageView, $this> */
    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class)->latest('viewed_at');
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class)->latest('occurred_at');
    }

    /** @param Builder<$this> $query */
    public function scopeHuman(Builder $query): void
    {
        $query->where('is_bot', false);
    }

    /** @param Builder<$this> $query */
    public function scopeOnlineNow(Builder $query, int $minutes = 5): void
    {
        $query->where('is_bot', false)->where('last_seen_at', '>=', now()->subMinutes($minutes));
    }

    /** Whoever this is, as well as we can name them. */
    public function displayName(): string
    {
        if ($this->relationLoaded('user') ? $this->user : $this->user()->first()) {
            return $this->user->name;
        }

        return 'Anonymous #'.substr($this->token, 0, 6);
    }

    public function isReturning(): bool
    {
        return $this->visits_count > 1;
    }
}
