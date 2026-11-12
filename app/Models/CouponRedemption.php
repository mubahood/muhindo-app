<?php

namespace App\Models;

use App\Enums\RedemptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of one coupon, by one person, against one invoice.
 *
 * @property numeric-string $discount
 * @property RedemptionStatus $status
 * @property \Illuminate\Support\Carbon $reserved_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property int $coupon_id
 * @property int $user_id
 * @property int|null $invoice_id
 * @property string $currency
 */
class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id', 'user_id', 'invoice_id', 'discount', 'currency',
        'status', 'reserved_at', 'confirmed_at', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RedemptionStatus::class,
            'discount' => 'decimal:2',
            'reserved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * A reservation nobody paid for stops holding its slot after the window.
     *
     * Expiry is read at query time rather than swept by a scheduled job, so a
     * campaign heals itself even on a host whose cron is not running — which
     * is the normal state of a small deployment. The cost is that this scope
     * must be used everywhere "uses remaining" is computed.
     *
     * @param  Builder<CouponRedemption>  $query
     */
    public function scopeHoldingASlot(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('status', RedemptionStatus::Confirmed)
                ->orWhere(fn (Builder $r) => $r
                    ->where('status', RedemptionStatus::Reserved)
                    ->where('reserved_at', '>', now()->subHours(self::reservationHours())));
        });
    }

    public static function reservationHours(): int
    {
        return (int) config('catalog.coupon_reservation_hours', 48);
    }

    public function isStale(): bool
    {
        return $this->status === RedemptionStatus::Reserved
            && $this->reserved_at->lt(now()->subHours(self::reservationHours()));
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
