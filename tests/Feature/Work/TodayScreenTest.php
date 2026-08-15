<?php

namespace Tests\Feature\Work;

use App\Livewire\Admin\Today;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The day view.
 *
 * The bug that made this screen necessary is the first thing tested: the
 * dashboard's task list filtered on project_id being null, so client work was
 * invisible and a day could not be read anywhere.
 */
class TodayScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function project(string $client = 'Mr. Albert'): Project
    {
        $c = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_number' => 'CL-'.Str::random(5),
            'name' => $client,
        ]);

        return Project::create([
            'uuid' => (string) Str::uuid(),
            'project_number' => 'PR-'.Str::random(5),
            'title' => 'A system',
            'client_id' => $c->id,
            'status' => 'active',
            'priority' => 'high',
            'currency' => 'UGX',
            'start_date' => Carbon::today()->subDays(30),
        ]);
    }

    private function task(array $attributes = []): ProjectTask
    {
        return ProjectTask::create($attributes + [
            'title' => 'Something to do',
            'status' => 'todo',
            'priority' => 'normal',
            'sort_order' => 0,
        ]);
    }

    /* Buckets ------------------------------------------------------------- */

    public function test_each_task_lands_in_the_right_bucket(): void
    {
        $this->task(['title' => 'Late thing', 'due_date' => Carbon::today()->subDays(3)]);
        $this->task(['title' => 'Today thing', 'due_date' => Carbon::today()]);
        $this->task(['title' => 'Soon thing', 'due_date' => Carbon::today()->addDays(2)]);
        $this->task(['title' => 'Someday thing', 'due_date' => null]);
        // Beyond the week window: visible nowhere on this screen.
        $this->task(['title' => 'Far thing', 'due_date' => Carbon::today()->addDays(30)]);

        $component = Livewire::actingAs($this->admin())->test(Today::class);

        $this->assertSame(['Late thing'], $component->instance()->overdue->pluck('title')->all());
        $this->assertSame(['Today thing'], $component->instance()->today->pluck('title')->all());
        $this->assertSame(['Someday thing'], $component->instance()->undated->pluck('title')->all());

        $upcoming = $component->instance()->upcoming->flatten()->pluck('title')->all();
        $this->assertSame(['Soon thing'], $upcoming);
    }

    public function test_a_task_due_today_is_not_overdue(): void
    {
        $task = $this->task(['due_date' => Carbon::today()]);

        // Painting today red every morning makes the whole list unreadable.
        $this->assertFalse($task->isOverdue());
        $this->assertTrue($this->task(['due_date' => Carbon::yesterday()])->isOverdue());
    }

    public function test_a_completed_task_is_never_overdue(): void
    {
        $task = $this->task(['due_date' => Carbon::today()->subDays(5), 'status' => 'done', 'completed_at' => now()]);

        $this->assertFalse($task->isOverdue());
        $this->assertCount(0, Livewire::actingAs($this->admin())->test(Today::class)->instance()->overdue);
    }

    /**
     * The bug this screen exists because of. Of 38 tasks in the first real
     * plan, 18 belonged to projects and could not be seen from anywhere but
     * the five project pages.
     */
    public function test_client_work_and_personal_work_both_appear(): void
    {
        $project = $this->project();
        $this->task(['title' => 'Client work', 'project_id' => $project->id, 'due_date' => Carbon::today()]);
        $this->task(['title' => 'Personal errand', 'project_id' => null, 'due_date' => Carbon::today()]);

        $titles = Livewire::actingAs($this->admin())->test(Today::class)->instance()->today->pluck('title')->all();

        $this->assertContains('Client work', $titles);
        $this->assertContains('Personal errand', $titles);
    }

    public function test_a_row_says_who_the_work_is_for(): void
    {
        $project = $this->project('Mr. Hambren');
        $this->task(['project_id' => $project->id, 'due_date' => Carbon::today()]);
        $this->task(['title' => 'Mine', 'due_date' => Carbon::today()]);

        Livewire::actingAs($this->admin())->test(Today::class)
            ->assertSee('Mr. Hambren')
            ->assertSee('Personal');
    }

    public function test_high_priority_sorts_above_the_rest(): void
    {
        $this->task(['title' => 'Ordinary', 'due_date' => Carbon::today(), 'priority' => 'normal', 'sort_order' => 0]);
        $this->task(['title' => 'Later', 'due_date' => Carbon::today(), 'priority' => 'low', 'sort_order' => 1]);
        $this->task(['title' => 'Urgent one', 'due_date' => Carbon::today(), 'priority' => 'high', 'sort_order' => 9]);

        $titles = Livewire::actingAs($this->admin())->test(Today::class)->instance()->today->pluck('title')->all();

        $this->assertSame(['Urgent one', 'Ordinary', 'Later'], $titles);
    }

    /* Interaction --------------------------------------------------------- */

    public function test_ticking_a_task_completes_it_and_clears_it_from_the_list(): void
    {
        $task = $this->task(['due_date' => Carbon::today()]);

        $component = Livewire::actingAs($this->admin())->test(Today::class);
        $this->assertCount(1, $component->instance()->today);

        $component->call('toggle', $task->id);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertCount(0, $component->instance()->today, 'the list is recomputed, not stale');
        $this->assertSame(1, $component->instance()->doneToday);
    }

    public function test_ticking_a_task_twice_puts_it_back(): void
    {
        $task = $this->task(['due_date' => Carbon::today()]);

        Livewire::actingAs($this->admin())->test(Today::class)
            ->call('toggle', $task->id)
            ->call('toggle', $task->id);

        $task->refresh();
        $this->assertSame('todo', $task->status);
        $this->assertNull($task->completed_at, 'an un-ticked task must not keep a completion time');
    }

    public function test_quick_capture_creates_an_unscheduled_task(): void
    {
        Livewire::actingAs($this->admin())->test(Today::class)
            ->set('newTask', 'Ring Albert back about the reports')
            ->call('addTask')
            ->assertSet('newTask', '');

        $task = ProjectTask::sole();
        $this->assertSame('Ring Albert back about the reports', $task->title);
        // Undated on purpose: forcing a date at capture time is what stops
        // people capturing at all.
        $this->assertNull($task->due_date);
        $this->assertNull($task->project_id);
    }

    public function test_quick_capture_can_attach_to_a_project(): void
    {
        $project = $this->project();

        Livewire::actingAs($this->admin())->test(Today::class)
            ->set('newTask', 'Send the deployment notes')
            ->set('newTaskProject', $project->id)
            ->call('addTask');

        $this->assertSame($project->id, ProjectTask::sole()->project_id);
    }

    public function test_an_empty_capture_box_creates_nothing(): void
    {
        Livewire::actingAs($this->admin())->test(Today::class)
            ->set('newTask', '   ')
            ->call('addTask');

        $this->assertSame(0, ProjectTask::count());
    }

    public function test_a_task_can_be_pulled_up_to_today(): void
    {
        $task = $this->task(['due_date' => null]);

        Livewire::actingAs($this->admin())->test(Today::class)->call('pullToToday', $task->id);

        $this->assertTrue($task->refresh()->due_date->isToday());
    }

    /* Access and empty state ---------------------------------------------- */

    public function test_the_screen_opens_with_nothing_in_it(): void
    {
        $this->actingAs($this->admin())->get(route('admin.today'))->assertOk();
    }

    public function test_the_screen_opens_with_a_full_day_in_it(): void
    {
        $project = $this->project();
        foreach (range(1, 6) as $n) {
            $this->task(['title' => "Task {$n}", 'project_id' => $project->id, 'due_date' => Carbon::today()]);
        }

        $this->actingAs($this->admin())->get(route('admin.today'))->assertOk()->assertSee('Task 6');
    }

    public function test_a_student_cannot_reach_it(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $student->syncSpatieRole();

        $this->actingAs($student)->get(route('admin.today'))->assertRedirect(route('login'));
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('admin.today'))->assertRedirect(route('login'));
    }
}
