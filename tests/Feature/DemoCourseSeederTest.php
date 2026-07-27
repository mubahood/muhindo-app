<?php

namespace Tests\Feature;

use App\Models\Course;
use Database\Seeders\DemoCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §9 — "a factory + seeder ... demo course with quiz + assignment seeded for local dev." */
class DemoCourseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_complete_demo_course(): void
    {
        $this->seed(DemoCourseSeeder::class);

        $course = Course::where('slug', 'demo-laravel-fundamentals')->firstOrFail();
        $this->assertTrue($course->is_published);
        $this->assertSame(1, $course->modules()->count());
        $this->assertSame(2, $course->lessons()->count());
        $this->assertSame(1, $course->quizzes()->count());
        $this->assertSame(3, $course->quizzes()->first()->questions()->count());
        $this->assertSame(1, $course->assignments()->count());
    }

    public function test_it_is_safe_to_run_twice_without_duplicating_content(): void
    {
        $this->seed(DemoCourseSeeder::class);
        $this->seed(DemoCourseSeeder::class);

        $this->assertSame(1, Course::where('slug', 'demo-laravel-fundamentals')->count());
        $course = Course::where('slug', 'demo-laravel-fundamentals')->firstOrFail();
        $this->assertSame(2, $course->lessons()->count());
        $this->assertSame(3, $course->quizzes()->first()->questions()->count());
    }
}
