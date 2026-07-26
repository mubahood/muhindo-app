<?php

namespace App\Models;

use App\Enums\BadgeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** §6.5 — one row per user per earned badge. `created_at` doubles as `earned_at`; never updated. */
class UserBadge extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'badge_type'];

    protected function casts(): array
    {
        return [
            'badge_type' => BadgeType::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
