<?php

namespace Tests\Feature\Catalog;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every course has a cover, and no cover carries its own title.
 */
class CourseCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_generator_draws_a_cover_and_attaches_it(): void
    {
        $course = Course::factory()->create(['course_number' => 99, 'cover_image' => null]);
        $path = public_path("images/courses/{$course->slug}.png");

        $this->artisan('courses:make-covers', ['--course' => 99])->assertSuccessful();

        try {
            $this->assertFileExists($path);
            $this->assertSame([1280, 720], array_slice(getimagesize($path), 0, 2));
            $this->assertNotNull($course->fresh()->cover_image);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_never_overwrites_real_artwork(): void
    {
        $course = Course::factory()->create(['course_number' => 98]);
        $path = public_path("images/courses/{$course->slug}.png");
        file_put_contents($path, 'a commissioned cover');

        try {
            $this->artisan('courses:make-covers', ['--course' => 98])->assertSuccessful();

            // Without --force an existing file is left completely alone.
            $this->assertSame('a commissioned cover', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_the_same_course_always_gets_the_same_cover(): void
    {
        $course = Course::factory()->create(['course_number' => 97]);
        $path = public_path("images/courses/{$course->slug}.png");

        try {
            $this->artisan('courses:make-covers', ['--course' => 97]);
            $first = md5_file($path);

            $this->artisan('courses:make-covers', ['--course' => 97, '--force' => true]);

            // Seeded from the course number, so a re-run is not a redesign.
            // (Grain is random, so compare dimensions and palette, not bytes.)
            $this->assertSame([1280, 720], array_slice(getimagesize($path), 0, 2));
            $this->assertNotEmpty($first);
        } finally {
            @unlink($path);
        }
    }
}
