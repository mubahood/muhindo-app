<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin split-pane preview renders through the exact same pipeline students see. */
class LessonMarkdownPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_an_admin_can_preview_markdown_content(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.lessons.preview-markdown'), ['content' => '# Hello'])
            ->assertOk()
            ->assertJson(['html' => "<h1>Hello</h1>\n"]);
    }

    public function test_a_non_admin_cannot_preview_markdown(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->postJson(route('admin.lessons.preview-markdown'), ['content' => '# Hello'])
            ->assertRedirect(route('login'));
    }
}
