<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** public-w2 — §2.2/§2.3 of PUBLIC_SITE_PLAN.md: tagline/outcomes/requirements/cover_alt. */
class CourseCatalogueFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_admin_can_set_tagline_outcomes_requirements_and_cover_alt(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Laravel From Scratch',
            'price' => 0,
            'level' => 'beginner',
            'tagline' => 'Build real apps with Laravel, step by step.',
            'outcomes' => "Build a CRUD app\nWrite feature tests\n\nDeploy to production",
            'requirements' => "A computer\nBasic PHP knowledge",
            'cover_alt' => 'Laravel From Scratch cover',
        ])->assertRedirect();

        $course = Course::where('title', 'Laravel From Scratch')->firstOrFail();

        $this->assertSame('Build real apps with Laravel, step by step.', $course->tagline);
        $this->assertSame(['Build a CRUD app', 'Write feature tests', 'Deploy to production'], $course->outcomes);
        $this->assertSame(['A computer', 'Basic PHP knowledge'], $course->requirements);
        $this->assertSame('Laravel From Scratch cover', $course->cover_alt);
    }

    public function test_blank_outcomes_and_requirements_are_stored_as_null_not_empty_arrays(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Bare Course',
            'price' => 0,
            'level' => 'beginner',
            'outcomes' => "   \n  \n",
            'requirements' => '',
        ])->assertRedirect();

        $course = Course::where('title', 'Bare Course')->firstOrFail();

        $this->assertNull($course->outcomes);
        $this->assertNull($course->requirements);
    }

    public function test_card_tagline_falls_back_to_a_trimmed_description_when_blank(): void
    {
        $course = Course::factory()->create([
            'tagline' => null,
            'description' => str_repeat('A very long description sentence. ', 10),
        ]);

        $fallback = $course->cardTagline();

        $this->assertNotSame('', $fallback);
        $this->assertLessThanOrEqual(83, strlen($fallback)); // Str::limit(80) + the ellipsis
    }

    public function test_cover_alt_falls_back_to_the_course_title_when_blank(): void
    {
        $course = Course::factory()->create(['title' => 'Databases 101', 'cover_alt' => null]);

        $this->assertSame('Databases 101', $course->coverAlt());
    }
}
