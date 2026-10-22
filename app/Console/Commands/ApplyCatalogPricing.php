<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

/**
 * Applies config/catalog.php to the imported courses.
 *
 * The owner reviews one config file and runs this, rather than editing 21 rows
 * by hand.
 *
 * Publication is only ever turned ON, and only for a course that is open for
 * enrolment, because an invisible course cannot be sold. It used to be turned
 * on for every course in a tier, which meant a run to change one price also
 * put three unfinished courses in the catalogue, one of them with no published
 * lessons at all. Whether to start advertising a course nobody can buy yet is
 * a decision about the shopfront, not about prices, and it stays in the admin
 * UI where it is made deliberately.
 *
 * Enrolment is the exception, and deliberately so. The 'open' list is applied
 * in both directions: a course named there is opened, a course not named there
 * is closed behind its waitlist. That is the one setting where drift is worth
 * a hard reset, because the failure is a course quietly taking money for
 * material that is not finished, and the config file is where that decision is
 * reviewable. Opening a course by hand on a production database is the thing
 * this command exists to make unnecessary.
 */
class ApplyCatalogPricing extends Command
{
    protected $signature = 'courses:apply-pricing {--dry-run : Show the changes and write nothing}';

    protected $description = 'Apply config/catalog.php prices, publication and enrolment to the catalogue';

    public function handle(): int
    {
        $tiers = config('catalog.tiers');
        $overrides = config('catalog.overrides', []);
        $open = config('catalog.open', []);
        $rows = [];

        foreach (Course::orderBy('course_number')->get() as $course) {
            /*
             * Before the tier check, not after it. Whether a course takes
             * money is not a question about its price band, and a course with
             * no tier yet must still be held shut rather than skipped.
             */
            $shouldOpen = in_array((int) $course->course_number, $open, true);
            $openChanged = $course->is_coming_soon === $shouldOpen;

            if (! $this->option('dry-run') && $openChanged) {
                $course->is_coming_soon = ! $shouldOpen;
                $course->save();
            }

            $enrolment = ($shouldOpen ? 'OPEN' : 'waitlist').($openChanged ? ' (changed)' : '');

            $tier = $tiers[$course->tier] ?? null;

            if ($tier === null) {
                $rows[] = [$course->course_number, mb_substr($course->title, 0, 34), '-', '-', '-', $enrolment, 'no tier'];

                continue;
            }

            $settings = array_merge($tier, $overrides[$course->course_number] ?? []);
            $price = number_format((float) $settings['price'], 2, '.', '');
            $usd = number_format((float) ($settings['price_usd'] ?? 0), 2, '.', '');
            $promote = $shouldOpen && ! $course->is_published;

            $was = [(string) $course->price, $course->is_published];

            if (! $this->option('dry-run')) {
                $course->price = $price;
                $course->price_usd = $usd;
                $course->currency = config('catalog.default_currency', 'UGX');
                // Only ever promotes, and only to make an open course visible.
                // Unpublishing is the owner's to do.
                if ($promote) {
                    $course->is_published = true;
                }
                $course->save();
            }

            $rows[] = [
                $course->course_number,
                mb_substr($course->title, 0, 34),
                'T'.$course->tier,
                $was[0].' → '.$price,
                '$'.rtrim(rtrim($usd, '0'), '.'),
                $enrolment,
                match (true) {
                    $promote => 'published (changed)',
                    (bool) $was[1] => 'published',
                    default => 'draft, owner decides',
                },
            ];
        }

        $this->table(['#', 'Course', 'Tier', 'UGX', 'USD', 'Enrolment', 'Status'], $rows);

        if ($this->option('dry-run')) {
            $this->info('Dry run. Nothing was written.');
        }

        return self::SUCCESS;
    }
}
