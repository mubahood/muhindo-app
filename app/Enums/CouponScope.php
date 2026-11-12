<?php

namespace App\Enums;

use App\Models\Course;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * What a coupon is allowed to discount.
 *
 * The two halves of this catalogue price very differently — a course is
 * UGX 130,000 and a source-code archive is UGX 550,000 — so "10% off" means
 * something different in each. Being able to say which half a campaign applies
 * to is the point of this enum; `Target` exists for the single-item case.
 */
enum CouponScope: string
{
    case Any = 'any';
    case Courses = 'courses';
    case Products = 'products';
    case Target = 'target';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Anything on the site',
            self::Courses => 'Any course',
            self::Products => 'Any source-code product',
            self::Target => 'One specific item',
        };
    }

    /** Does this scope, on its own, admit the given purchasable? */
    public function admits(Model $purchasable): bool
    {
        return match ($this) {
            self::Any => true,
            self::Courses => $purchasable instanceof Course,
            self::Products => $purchasable instanceof Product,
            // Target narrows to one row, which the coupon itself has to check:
            // the scope alone cannot know which row was meant.
            self::Target => false,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
