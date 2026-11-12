<?php

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\RedemptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A discount code.
 *
 * `used_count` is kept as a cache of the confirmed redemptions so list screens
 * can show a number without a sub-query per row. The redemptions table is the
 * record; this column is only ever derived from it.
 *
 * @property numeric-string $value
 * @property numeric-string|null $min_subtotal
 * @property CouponScope $scope
 * @property CouponType $type
 * @property string|null $target_type
 * @property int|null $target_id
 * @property int|null $max_uses
 * @property int|null $max_uses_per_user
 * @property int $used_count
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'scope', 'target_type', 'target_id', 'type', 'value', 'min_subtotal',
        'max_uses', 'max_uses_per_user', 'used_count', 'starts_at', 'expires_at',
        'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'scope' => CouponScope::class,
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || now()->gte($this->starts_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->gt($this->expires_at);
    }

    /**
     * Uses still available, counting reservations that have not gone stale.
     *
     * Null means unlimited. Reserved slots count against the cap so a limited
     * campaign cannot oversell while buyers sit on the payment screen.
     */
    public function usesRemaining(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - $this->redemptions()->holdingASlot()->count());
    }

    public function hasUsesRemaining(): bool
    {
        $remaining = $this->usesRemaining();

        return $remaining === null || $remaining > 0;
    }

    public function usesRemainingFor(User $user): ?int
    {
        if ($this->max_uses_per_user === null) {
            return null;
        }

        $held = $this->redemptions()->holdingASlot()->where('user_id', $user->id)->count();

        return max(0, $this->max_uses_per_user - $held);
    }

    public function hasUsesRemainingFor(User $user): bool
    {
        $remaining = $this->usesRemainingFor($user);

        return $remaining === null || $remaining > 0;
    }

    public function meetsMinimum(string $subtotal): bool
    {
        return $this->min_subtotal === null
            || bccomp($subtotal, (string) $this->min_subtotal, 2) >= 0;
    }

    /** Is this coupon allowed to discount the thing being bought? */
    public function appliesTo(Model $purchasable): bool
    {
        if ($this->scope === CouponScope::Target) {
            return $this->target_type === $purchasable->getMorphClass()
                && (int) $this->target_id === (int) $purchasable->getKey();
        }

        return $this->scope->admits($purchasable);
    }

    /** What this coupon has actually earned: confirmed uses and the discount given away. */
    public function performance(): array
    {
        $rows = $this->redemptions()
            ->selectRaw('status, count(*) as uses, coalesce(sum(discount), 0) as discount')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($r) => $r->status->value);

        $of = fn (RedemptionStatus $s, string $key) => $rows->has($s->value)
            ? $rows->get($s->value)->{$key}
            : 0;

        return [
            'confirmed' => (int) $of(RedemptionStatus::Confirmed, 'uses'),
            'confirmed_discount' => (string) $of(RedemptionStatus::Confirmed, 'discount'),
            'reserved' => (int) $of(RedemptionStatus::Reserved, 'uses'),
            'released' => (int) $of(RedemptionStatus::Released, 'uses'),
        ];
    }

    public function targetLabel(): string
    {
        if ($this->scope !== CouponScope::Target) {
            return $this->scope->label();
        }

        $target = $this->target;

        return match (true) {
            $target instanceof Course => 'Course: '.$target->title,
            $target instanceof Product => 'Product: '.$target->name,
            default => 'One specific item (missing)',
        };
    }
}
