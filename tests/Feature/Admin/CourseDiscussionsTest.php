<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CourseDiscussions;
use App\Models\Course;
use App\Models\User;
use App\Notifications\DiscussionRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** The instructor's per-course Q&A inbox: reply (auto-badged) and resolve. */
class CourseDiscussionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_a_non_admin_cannot_view_the_inbox(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.courses.discussions', $course))->assertRedirect(route('login'));
    }

    public function test_the_inbox_lists_open_threads(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $course->discussions()->create(['user_id' => $student->id, 'body' => 'Why does this fail?']);

        $this->actingAs($admin)->get(route('admin.courses.discussions', $course))
            ->assertOk()->assertSee('Why does this fail?');
    }

    public function test_replying_from_the_inbox_is_flagged_as_the_instructor_answer_and_notifies_the_student(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $thread = $course->discussions()->create(['user_id' => $student->id, 'body' => 'Help?']);

        Livewire::actingAs($admin)
            ->test(CourseDiscussions::class, ['course' => $course])
            ->call('openThread', $thread->id)
            ->set('reply', 'Here is how you fix it.')
            ->call('submitReply');

        $this->assertSame(1, $thread->replies()->count());
        $this->assertTrue($thread->replies()->first()->is_instructor_answer);
        Notification::assertSentTo($student, DiscussionRepliedNotification::class);
    }

    public function test_resolving_from_the_inbox_marks_the_thread_resolved(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $thread = $course->discussions()->create(['user_id' => $student->id, 'body' => 'Help?']);

        Livewire::actingAs($admin)
            ->test(CourseDiscussions::class, ['course' => $course])
            ->call('resolve', $thread->id);

        $this->assertTrue($thread->fresh()->isResolved());
    }
}
