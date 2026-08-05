<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sitting. A new one begins after 30 minutes without a request, which is
 * the industry convention and, more usefully, roughly the point past which
 * somebody has stopped reading and started doing something else.
 */
class Visit extends Model
{
    protected $fillable = [
        'visitor_id', 'user_id', 'entry_path', 'exit_path', 'referrer', 'referrer_host',
        'channel', 'source', 'medium', 'campaign',
        'device', 'browser', 'os', 'ip', 'country', 'city', 'language',
        'page_views_count', 'events_count', 'engaged_seconds', 'is_bounce',
        'started_at', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_bounce' => 'boolean',
        ];
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PageView, $this> */
    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class)->orderBy('viewed_at');
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class)->orderBy('occurred_at');
    }

    /** @param Builder<$this> $query */
    public function scopeInPeriod(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->whereBetween('started_at', [$from, $to]);
    }
}
