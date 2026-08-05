<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One page, and how much of it was actually read.
 *
 * engaged_seconds and scroll_percent arrive later, from the beacon, and stay
 * null for anyone who closes the tab at once or blocks scripts. Null is the
 * honest value there: it means "not measured", and averaging it in as zero
 * would quietly drag every page's dwell time toward the floor.
 */
class PageView extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'visit_id', 'visitor_id', 'user_id', 'path', 'query', 'route_name', 'title',
        'subject_type', 'subject_id', 'status', 'response_ms',
        'engaged_seconds', 'scroll_percent', 'viewed_at',
    ];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
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

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
