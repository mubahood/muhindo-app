<?php

namespace Tests\Feature;

use App\Models\Course;
use Database\Seeders\PublicCourseCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w2, of PUBLIC_SITE_PLAN.md: a real-looking public catalogue to build every e-Learning page against. */
class PublicCourseCatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_at_least_six_published_courses_spanning_free_and_paid(): void
    {
        $this->seed(PublicCourseCatalogueSeeder::class);

        $courses = Course::all();

        $this->assertGreaterThanOrEqual(6, $courses->count());
        $this->assertTrue($courses->every(fn (Course $c) => $c->is_published));
        $this->assertGreaterThanOrEqual(2, $courses->where('price', 0)->count());
        $this->assertGreaterThanOrEqual(2, $courses->where('price', '>', 0)->count());
        $this->assertGreaterThanOrEqual(2, $courses->pluck('category')->unique()->count());
        $this->assertGreaterThanOrEqual(2, $courses->pluck('level')->unique()->count());
    }

    public function test_every_seeded_course_has_real_content_not_lorem_text(): void
    {
        $this->seed(PublicCourseCatalogueSeeder::class);

        Course::all()->each(function (Course $course): void {
            $this->assertNotEmpty($course->tagline);
            $this->assertNotEmpty($course->outcomes);
            $this->assertNotEmpty($course->requirements);
            $this->assertGreaterThan(0, $course->modules()->count());
            $this->assertGreaterThan(0, $course->lessonCount());
            $this->assertTrue(
                $course->lessons()->where('is_free_preview', true)->exists(),
                "{$course->title} has no free-preview lesson."
            );
        });
    }

    public function test_it_is_safe_to_run_twice_without_duplicating_content(): void
    {
        $this->seed(PublicCourseCatalogueSeeder::class);
        $firstCount = Course::count();

        $this->seed(PublicCourseCatalogueSeeder::class);

        $this->assertSame($firstCount, Course::count());
    }
}
