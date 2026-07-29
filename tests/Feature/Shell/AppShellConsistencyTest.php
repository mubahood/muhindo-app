<?php

namespace Tests\Feature\Shell;

use App\Models\Client;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\User;
use App\Support\AppShell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every page a signed-in person can reach renders in one shell, on one
 * stylesheet, with SPA navigation wired up. Two chromes for the same user is
 * how the logged-in side drifted apart before — and shared assets are the
 * precondition for wire:navigate, because Livewire's head merge appends the
 * incoming page's stylesheets without removing the outgoing ones.
 */
class AppShellConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function dualRoleUser(): User
    {
        $user = User::factory()->create([
            'role' => 'student', 'is_student' => true, 'is_client' => true,
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(), 'client_number' => 'CL-SHELL-1',
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ]);

        Project::create([
            'uuid' => (string) Str::uuid(), 'project_number' => 'PRJ-SHELL-1',
            'title' => 'Shell project', 'client_id' => $client->id, 'status' => 'active',
        ]);

        return $user;
    }

    private function enrolledCourse(User $user): Course
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return $course;
    }

    public function test_every_signed_in_page_renders_in_the_app_shell(): void
    {
        $user = $this->dualRoleUser();
        $project = Project::firstOrFail();

        $pages = [
            route('dashboard'),
            route('learn.index'),
            route('account.edit'),
            route('portal.index'),
            route('portal.invoices'),
            route('portal.project', $project),
            route('notifications.index'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
            $html = $response->getContent();

            $this->assertStringContainsString('css/td-admin.css', $html, "{$url} must load the shared stylesheet");
            $this->assertStringContainsString('class="tb-sidebar"', $html, "{$url} must render inside the app shell");
            $this->assertStringContainsString('id="tb-content"', $html, "{$url} must expose the shell's main region");
        }
    }

    public function test_the_course_player_shares_the_shells_stylesheet(): void
    {
        $user = $this->dualRoleUser();
        $course = $this->enrolledCourse($user);

        // The player is deliberately full-bleed rather than sidebar-and-topbar,
        // but it must sit on the same assets or navigating in and out of a course
        // would stack two design systems on top of each other.
        // The course entry point resumes straight into a lesson, so follow it
        // through to the page the student actually lands on.
        $this->actingAs($user)->followingRedirects()->get(route('learn.course', $course))
            ->assertOk()
            ->assertSee('css/td-admin.css', false)
            ->assertSee('learn-shell', false)
            ->assertSee('id="learn-content"', false);
    }

    public function test_spa_navigation_is_wired_up_on_both_shells(): void
    {
        $user = $this->dualRoleUser();
        $course = $this->enrolledCourse($user);

        foreach ([route('dashboard'), route('learn.course', $course)] as $url) {
            $response = $this->actingAs($user)->followingRedirects()->get($url);
            $response->assertOk();

            $this->assertStringContainsString(
                'Alpine.navigate', (string) $response->getContent(),
                "{$url} must ship the SPA link handler"
            );
        }
    }

    public function test_the_spa_boundary_covers_every_signed_in_area(): void
    {
        $paths = AppShell::paths();

        foreach (['dashboard', 'account', 'notifications', 'admin', 'learn', 'portal'] as $area) {
            $this->assertContains(
                rtrim((string) parse_url(url('/'.$area), PHP_URL_PATH), '/'),
                $paths['prefixes'],
                "/{$area} is part of the signed-in app, so its links must navigate without a full reload"
            );
        }
    }

    public function test_no_view_still_extends_the_retired_second_layout(): void
    {
        // layouts.app was a second chrome for the same signed-in users; anything
        // reviving it puts the two-look problem straight back.
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        $offenders = [];
        foreach ($views as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
                && str_contains((string) file_get_contents($file->getPathname()), "@extends('layouts.app')")) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders);
        $this->assertFileDoesNotExist(resource_path('views/layouts/app.blade.php'));
    }
}
