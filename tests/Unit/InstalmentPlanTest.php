<?php

namespace Tests\Unit;

use App\Support\Catalog\InstalmentPlan;
use Tests\TestCase;

/**
 * The two divisions behind paying for a course a part at a time.
 *
 * The money assertions are the ones that matter most: every one of them is a
 * sum somebody is charged, and a rounding error here either overcharges a
 * student or hands out a quarter of a course for nothing.
 */
class InstalmentPlanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('catalog.instalments', ['parts' => 4, 'label' => 'quarter', 'min_modules' => 4]);
    }

    public function test_a_total_is_split_into_equal_parts(): void
    {
        $this->assertSame(['32500.00', '32500.00', '32500.00', '32500.00'],
            InstalmentPlan::split('130000.00'));
    }

    public function test_the_parts_always_add_back_up_to_the_total(): void
    {
        // 100000/4 is 25000 exactly; 100001 is not, and the odd shilling has
        // to land somewhere rather than evaporate.
        foreach (['130000.00', '100001.00', '40000.00', '99999.99', '0.03'] as $total) {
            $sum = array_reduce(
                InstalmentPlan::split($total),
                fn (string $carry, string $part) => bcadd($carry, $part, 2),
                '0.00',
            );

            $this->assertSame(0, bccomp($sum, $total, 2), "parts of {$total} summed to {$sum}");
        }
    }

    public function test_the_remainder_lands_on_the_last_part_only(): void
    {
        $parts = InstalmentPlan::split('100001.00');

        $this->assertSame(['25000.25', '25000.25', '25000.25'], array_slice($parts, 0, 3));
        $this->assertSame('25000.25', $parts[3]);
    }

    public function test_money_paid_buys_whole_parts_and_never_a_fraction(): void
    {
        $total = '130000.00';

        $this->assertSame(0, InstalmentPlan::partsCoveredBy('0.00', $total));
        $this->assertSame(0, InstalmentPlan::partsCoveredBy('32499.99', $total));
        $this->assertSame(1, InstalmentPlan::partsCoveredBy('32500.00', $total));
        // Over one instalment but short of two buys one. Paying extra is not
        // the same as paying the next part.
        $this->assertSame(1, InstalmentPlan::partsCoveredBy('50000.00', $total));
        $this->assertSame(2, InstalmentPlan::partsCoveredBy('65000.00', $total));
        $this->assertSame(3, InstalmentPlan::partsCoveredBy('97500.00', $total));
        $this->assertSame(4, InstalmentPlan::partsCoveredBy('130000.00', $total));
    }

    public function test_a_free_course_is_entirely_covered(): void
    {
        $this->assertSame(4, InstalmentPlan::partsCoveredBy('0.00', '0.00'));
    }

    public function test_the_next_payment_is_the_gap_to_the_next_threshold(): void
    {
        $total = '130000.00';

        $this->assertSame('32500.00', InstalmentPlan::amountForNextPart('0.00', $total));
        $this->assertSame('32500.00', InstalmentPlan::amountForNextPart('32500.00', $total));
        // Already overpaid into the second part: they owe the difference only.
        $this->assertSame('15000.00', InstalmentPlan::amountForNextPart('50000.00', $total));
        $this->assertSame('0.00', InstalmentPlan::amountForNextPart('130000.00', $total));
    }

    public function test_paying_every_next_part_in_turn_settles_the_invoice_exactly(): void
    {
        $total = '130000.00';
        $paid = '0.00';

        for ($i = 0; $i < 4; $i++) {
            $paid = bcadd($paid, InstalmentPlan::amountForNextPart($paid, $total), 2);
        }

        $this->assertSame(0, bccomp($paid, $total, 2));
        $this->assertSame(4, InstalmentPlan::partsCoveredBy($paid, $total));
    }

    public function test_a_part_is_named_for_the_label(): void
    {
        $this->assertSame('Quarter 3', InstalmentPlan::partName(3));

        config()->set('catalog.instalments.label', 'stage');
        $this->assertSame('Stage 3', InstalmentPlan::partName(3));
    }
}
