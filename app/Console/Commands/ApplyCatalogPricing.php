<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

/**
 * Applies config/catalog.php to the imported courses.
 *
 * The owner reviews one config file and runs this, rather than editing 21 rows
 * by hand. Publication is only ever turned ON here — a course the owner
 * unpublished stays unpublished, because this command exists to roll out a
 * decision, not to overrule one made later in the admin UI.
 */
class ApplyCatalogPricing extends Command
{
    protected $signature = 'courses:apply-pricing {--dry-run : Show the changes and write nothing}';

    protected $description = 'Apply config/catalog.php prices and publication to the catalogue';

    public function handle(): int
    {
        $tiers = config('catalog.tiers');
        $overrides = config('catalog.overrides', []);
        $rows = [];

        foreach (Course::orderBy('course_number')->get() as $course) {
            $tier = $tiers[$course->tier] ?? null;

            if ($tier === null) {
                $rows[] = [$course->course_number, mb_substr($course->title, 0, 34), '—', '—', 'no tier'];

                continue;
            }

            $settings = array_merge($tier, $overrides[$course->course_number] ?? []);
            $price = number_format((float) $settings['price'], 2, '.', '');
            $publish = (bool) $settings['publish'];

            $was = [(string) $course->price, $course->is_published];

            if (! $this->option('dry-run')) {
                $course->price = $price;
                $course->currency = config('catalog.currency', 'UGX');
                // Only ever promotes. Unpublishing is the owner's to do.
                if ($publish) {
                    $course->is_published = true;
                }
                $course->save();
            }

            $rows[] = [
                $course->course_number,
                mb_substr($course->title, 0, 34),
                'T'.$course->tier,
                $was[0].' → '.$price,
                ($publish ? 'published' : ($was[1] ? 'left published' : 'draft — needs approval')),
            ];
        }

        $this->table(['#', 'Course', 'Tier', 'Price', 'Status'], $rows);

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }
}
