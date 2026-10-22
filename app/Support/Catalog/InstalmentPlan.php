<?php

namespace App\Support\Catalog;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Invoice;
use App\Models\Lesson;

/**
 * How a course is divided when it is bought a part at a time.
 *
 * Two divisions live here and they are deliberately separate, because they
 * answer different questions and are allowed to disagree.
 *
 * The CONTENT split is by whole modules. A module is the unit a student thinks
 * in, and "you may watch lessons 1 to 3 of module 5 but not 4 to 6" is a rule
 * nobody can hold in their head. Modules are indivisible, so the parts come out
 * near-equal rather than equal, and that is the right trade.
 *
 * The MONEY split is by the invoice total, in exact shillings that add back up.
 * It is never derived from the course price, because a coupon, a discount or a
 * price change after the sale would then unlock a different amount of the
 * course than the student actually paid for. What they owe is on the invoice;
 * the invoice is the contract.
 *
 * Both are computed, never stored. A stored module-to-part column would drift
 * the moment a module was added, and would need a migration, an admin screen
 * and a backfill to say something this file can say in one pass.
 */
final class InstalmentPlan
{
    /**
     * @param  list<array{part:int,module:CourseModule,lessons:int}>  $assignments
     */
    private function __construct(
        private readonly Course $course,
        private readonly array $assignments,
    ) {}

    /** How many parts a course is sold in. Four of them are quarters. */
    public static function partCount(): int
    {
        return max(2, (int) config('catalog.instalments.parts', 4));
    }

    /** What one part is called on screen: "quarter". */
    public static function label(): string
    {
        return (string) config('catalog.instalments.label', 'quarter');
    }

    /** "quarter" -> "Quarter 2". */
    public static function partName(int $index): string
    {
        return ucfirst(self::label()).' '.$index;
    }

    /** The fewest modules a course needs before splitting it means anything. */
    public static function minimumModules(): int
    {
        return max(self::partCount(), (int) config('catalog.instalments.min_modules', 4));
    }

    /* ---------------------------------------------------------------- content */

    public static function for(Course $course): self
    {
        $course->loadMissing('modules.lessons');

        $modules = $course->modules
            ->sortBy('sort_order')
            ->values()
            ->map(fn (CourseModule $module) => [
                'module' => $module,
                'lessons' => $module->lessons->where('is_published', true)->count(),
            ])
            ->all();

        return new self($course, self::assign($modules));
    }

    /**
     * Walk the modules once, opening a new part when the one being filled has
     * had its fair share of what is left.
     *
     * "Fair share" is recomputed at every part from the lessons still unplaced,
     * so one fat module early does not starve everything after it. A module is
     * added while it would not overshoot that share by more than half of
     * itself: past the halfway mark it sits better at the head of the next
     * part than the tail of this one.
     *
     * The invariant that matters is that every part gets at least one module.
     * A student who pays for a part and is handed nothing is the one bug in
     * this file that costs money, so the loop keeps one module in reserve for
     * each part still to come and the final part sweeps up whatever is left.
     *
     * @param  list<array{module:CourseModule,lessons:int}>  $modules
     * @return list<array{part:int,module:CourseModule,lessons:int}>
     */
    private static function assign(array $modules): array
    {
        $count = count($modules);
        $parts = self::partCount();

        if ($count === 0) {
            return [];
        }

        // Fewer modules than parts: there is no honest split, so it is one part.
        if ($count < $parts) {
            return array_map(fn ($m) => ['part' => 1] + $m, $modules);
        }

        $assigned = [];
        $index = 0;

        for ($part = 1; $part <= $parts; $part++) {
            if ($part === $parts) {
                for (; $index < $count; $index++) {
                    $assigned[] = ['part' => $part] + $modules[$index];
                }

                break;
            }

            $partsLeft = $parts - $part + 1;
            $lessonsLeft = array_sum(array_column(array_slice($modules, $index), 'lessons'));
            $share = $lessonsLeft / $partsLeft;

            $taken = 0;
            $running = 0;

            while ($index + $taken < $count) {
                // Keep one module back for each part still to be filled.
                if (($count - $index - $taken) <= ($partsLeft - 1)) {
                    break;
                }

                $next = $modules[$index + $taken]['lessons'];

                if ($taken > 0 && ($running + $next / 2) > $share) {
                    break;
                }

                $running += $next;
                $taken++;
            }

            for ($i = 0; $i < $taken; $i++, $index++) {
                $assigned[] = ['part' => $part] + $modules[$index];
            }
        }

        return $assigned;
    }

    /** Which part a module belongs to, or 1 for anything this plan has never heard of. */
    public function partOfModule(int $moduleId): int
    {
        foreach ($this->assignments as $row) {
            if ($row['module']->id === $moduleId) {
                return $row['part'];
            }
        }

        return 1;
    }

    public function partOfLesson(Lesson $lesson): int
    {
        return $this->partOfModule($lesson->course_module_id);
    }

    /**
     * The plan as the student is shown it: each part, its modules and its size.
     *
     * @return list<array{part:int,name:string,modules:list<CourseModule>,lessons:int}>
     */
    public function summary(): array
    {
        $out = [];

        foreach ($this->assignments as $row) {
            $part = $row['part'];
            $out[$part] ??= ['part' => $part, 'name' => self::partName($part), 'modules' => [], 'lessons' => 0];
            $out[$part]['modules'][] = $row['module'];
            $out[$part]['lessons'] += $row['lessons'];
        }

        ksort($out);

        return array_values($out);
    }

    /** How many parts this plan actually produced; 1 for a course too small to split. */
    public function realisedParts(): int
    {
        return count($this->summary());
    }

    public function course(): Course
    {
        return $this->course;
    }

    /* ------------------------------------------------------------------ money */

    /**
     * Split a total into parts that add back up to it, exactly.
     *
     * The remainder goes on the last part rather than being spread about, so
     * the instalments a student is quoted up front are the ones they are
     * charged, and the final payment is the only one that can look odd.
     *
     * @return list<string> 1-indexed by position, each a "0.00" string
     */
    public static function split(string $total): array
    {
        $parts = self::partCount();
        $base = bcdiv($total, (string) $parts, 2);

        $out = array_fill(0, $parts - 1, $base);
        $out[] = bcsub($total, bcmul($base, (string) ($parts - 1), 2), 2);

        return $out;
    }

    /**
     * What has to have been paid, in total, to hold part N.
     *
     * @return list<string>
     */
    public static function cumulative(string $total): array
    {
        $running = '0.00';

        return array_map(function (string $amount) use (&$running): string {
            $running = bcadd($running, $amount, 2);

            return $running;
        }, self::split($total));
    }

    /** The price of one part on its own. */
    public static function priceOfPart(string $total, int $index): string
    {
        return self::split($total)[max(1, $index) - 1] ?? '0.00';
    }

    /**
     * How many parts a given amount of money has bought.
     *
     * Rounded down and never past the end: paying a little over one instalment
     * buys one instalment, and paying the lot buys the course.
     */
    public static function partsCoveredBy(string $amountPaid, string $total): int
    {
        if (bccomp($total, '0', 2) <= 0) {
            return self::partCount();
        }

        $covered = 0;

        foreach (self::cumulative($total) as $threshold) {
            if (bccomp($amountPaid, $threshold, 2) >= 0) {
                $covered++;
            }
        }

        return $covered;
    }

    /**
     * What an invoice looks like to somebody buying a course in parts, or null
     * when this invoice is not that.
     *
     * One invoice, for the whole course, is deliberate. Four invoices would
     * put four debts on a student's orders page for one course, and would make
     * "how much of this course have you paid for?" a question about four rows
     * that can each be cancelled, refunded or voided on their own. A single
     * invoice with a running balance answers it in one number, which is also
     * the number the ledger already keeps.
     *
     * @return array{course:Course,paid:int,of:int,next_amount:string,next_name:string,part_prices:list<string>}|null
     */
    public static function contextFor(Invoice $invoice): ?array
    {
        if (! $invoice->status->isPayable()) {
            return null;
        }

        $item = $invoice->items->first(fn ($line) => $line->source_type === Course::class);
        $course = $item ? Course::find($item->source_id) : null;

        if ($course === null || ! $course->allowsInstalments()) {
            return null;
        }

        $total = (string) $invoice->total;
        $paid = self::partsCoveredBy((string) $invoice->amount_paid, $total);

        return [
            'course' => $course,
            'paid' => $paid,
            'of' => self::partCount(),
            'next_amount' => self::amountForNextPart((string) $invoice->amount_paid, $total),
            'next_name' => self::partName(min($paid + 1, self::partCount())),
            'part_prices' => self::split($total),
        ];
    }

    /**
     * What to pay to take the next part, given what has been paid already.
     *
     * Not simply the price of one part: somebody who has overpaid slightly, or
     * been credited a partial refund, owes the difference to the next threshold
     * and not a penny more. Returns "0.00" when there is nothing left to buy.
     */
    public static function amountForNextPart(string $amountPaid, string $total): string
    {
        foreach (self::cumulative($total) as $threshold) {
            if (bccomp($amountPaid, $threshold, 2) < 0) {
                return bcsub($threshold, $amountPaid, 2);
            }
        }

        return '0.00';
    }
}
