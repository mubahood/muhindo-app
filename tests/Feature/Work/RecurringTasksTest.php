<?php

namespace Tests\Feature\Work;

use App\Livewire\Admin\Today;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Support\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two features that exist to stop the same failure happening twice: a
 * habit that survives being forgotten, and a number that counts silence.
 */
class RecurringTasksTest extends TestCase
{
    use RefreshDatabase;

    private function template(array $attributes = []): ProjectTask
    {
        return ProjectTask::create($attributes + [
            'title' => 'Post a progress update',
            'status' => 'todo',
            'priority' => 'normal',
            'repeat_every' => 'daily',
            'sort_order' => 0,
        ]);
    }

    /**
     * Post an update and backdate it.
     *
     * created_at is not fillable on ProjectUpdate and Eloquent stamps it on
     * save, so passing it to create() is silently ignored. It has to be forced
     * afterwards, or every backdated fixture silently reads as "today".
     */
    private function updatedDaysAgo(Project $project, int $days): ProjectUpdate
    {
        $update = ProjectUpdate::create(['project_id' => $project->id, 'update_text' => 'x']);
        $update->forceFill(['created_at' => Carbon::today()->subDays($days)])->save();

        return $update;
    }

    private function project(string $client = 'Mr. Baluku', ?Carbon $started = null): Project
    {
        $c = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_number' => 'CL-'.Str::random(5),
            'name' => $client,
        ]);

        return Project::create([
            'uuid' => (string) Str::uuid(),
            'project_number' => 'PR-'.Str::random(5),
            'title' => 'Their system',
            'client_id' => $c->id,
            'status' => 'active',
            'priority' => 'high',
            'currency' => 'UGX',
            'start_date' => $started ?? Carbon::today()->subDays(40),
        ]);
    }

    /* Generation ---------------------------------------------------------- */

    public function test_a_daily_template_produces_one_task_today(): void
    {
        $template = $this->template();

        $this->artisan('tasks:generate-recurring')->assertSuccessful();

        $copy = ProjectTask::where('repeats_from_id', $template->id)->sole();
        $this->assertTrue($copy->due_date->isToday());
        $this->assertSame('Post a progress update', $copy->title);
        $this->assertNull($copy->repeat_every, 'a copy is a to-do, not another template');
    }

    /**
     * The property the whole design serves. This runs on a schedule and will
     * also be run by hand after a missed day; a second copy would teach you
     * that your own list lies to you.
     */
    public function test_running_it_twice_produces_exactly_one_task(): void
    {
        $this->template();

        $this->artisan('tasks:generate-recurring')->assertSuccessful();
        $this->artisan('tasks:generate-recurring')->assertSuccessful();
        $this->artisan('tasks:generate-recurring')->assertSuccessful();

        $this->assertSame(1, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    public function test_a_copy_carries_the_templates_project_priority_and_notes(): void
    {
        $project = $this->project();
        $this->template([
            'project_id' => $project->id,
            'priority' => 'high',
            'description' => 'Two lines is enough.',
        ]);

        $this->artisan('tasks:generate-recurring');

        $copy = ProjectTask::whereNotNull('repeats_from_id')->sole();
        $this->assertSame($project->id, $copy->project_id);
        $this->assertSame('high', $copy->priority);
        $this->assertSame('Two lines is enough.', $copy->description);
    }

    public function test_weekdays_skips_the_weekend(): void
    {
        $this->template(['repeat_every' => 'weekdays']);

        $this->artisan('tasks:generate-recurring', ['--date' => '2026-08-16']); // a Sunday
        $this->assertSame(0, ProjectTask::whereNotNull('repeats_from_id')->count());

        $this->artisan('tasks:generate-recurring', ['--date' => '2026-08-17']); // Monday
        $this->assertSame(1, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    public function test_weekly_only_fires_on_the_templates_own_weekday(): void
    {
        // Friday 21 Aug 2026 carries the weekday for this habit.
        $this->template(['repeat_every' => 'weekly', 'due_date' => Carbon::parse('2026-08-21')]);

        $this->artisan('tasks:generate-recurring', ['--date' => '2026-08-27']); // Thursday
        $this->assertSame(0, ProjectTask::whereNotNull('repeats_from_id')->count());

        $this->artisan('tasks:generate-recurring', ['--date' => '2026-08-28']); // Friday
        $this->assertSame(1, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    public function test_a_weekly_template_with_no_date_generates_nothing(): void
    {
        // There is no way to know which weekday was meant, and guessing "today"
        // would silently walk the habit forward every time this ran.
        $this->template(['repeat_every' => 'weekly', 'due_date' => null]);

        $this->artisan('tasks:generate-recurring');

        $this->assertSame(0, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    public function test_an_expired_rule_stops_producing(): void
    {
        $this->template(['repeat_until' => Carbon::today()->subDay()]);

        $this->artisan('tasks:generate-recurring');

        $this->assertSame(0, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    public function test_nothing_is_generated_before_the_habit_starts(): void
    {
        $this->template(['repeat_every' => 'daily', 'due_date' => Carbon::today()->addDays(5)]);

        $this->artisan('tasks:generate-recurring');

        $this->assertSame(0, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->template();

        $this->artisan('tasks:generate-recurring', ['--dry' => true])->assertSuccessful();

        $this->assertSame(0, ProjectTask::whereNotNull('repeats_from_id')->count());
    }

    /** A template is a rule. Shown in a list it is an item that can never be finished. */
    public function test_templates_never_appear_in_any_task_list(): void
    {
        $this->template(['due_date' => Carbon::today()]);
        $this->artisan('tasks:generate-recurring');

        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $today = Livewire::actingAs($admin)->test(Today::class)->instance()->today;

        $this->assertCount(1, $today, 'the copy shows, the rule does not');
        $this->assertNotNull($today->first()->repeats_from_id);

        $this->assertSame(1, app(DashboardService::class)->myPendingTasksCount());
    }

    /* Contact health ------------------------------------------------------ */

    public function test_days_since_the_last_update_are_counted_per_client(): void
    {
        $project = $this->project('Mr. Hambren');
        $this->updatedDaysAgo($project, 20);

        $row = app(DashboardService::class)->clientContactHealth()->sole();

        $this->assertSame('Mr. Hambren', $row['client']->name);
        $this->assertSame(20, $row['days']);
        $this->assertSame('critical', $row['level']);
    }

    public function test_a_client_updated_today_is_quiet(): void
    {
        $project = $this->project();
        ProjectUpdate::create(['project_id' => $project->id, 'update_text' => 'Posted just now']);

        $row = app(DashboardService::class)->clientContactHealth()->sole();

        $this->assertSame(0, $row['days']);
        $this->assertSame('ok', $row['level']);
    }

    public function test_a_week_of_silence_is_a_warning_and_a_fortnight_is_critical(): void
    {
        $this->updatedDaysAgo($this->project('Seven days'), 7);
        $this->updatedDaysAgo($this->project('Fourteen days'), 14);

        $rows = app(DashboardService::class)->clientContactHealth()->keyBy(fn ($r) => $r['client']->name);

        $this->assertSame('warn', $rows['Seven days']['level']);
        $this->assertSame('critical', $rows['Fourteen days']['level']);
    }

    /** A client who has never been updated is the worst case, not an empty cell. */
    public function test_a_client_never_updated_counts_from_when_the_work_started(): void
    {
        $this->project('Never told anything', Carbon::today()->subDays(60));

        $row = app(DashboardService::class)->clientContactHealth()->sole();

        $this->assertSame(60, $row['days']);
        $this->assertSame('critical', $row['level']);
        $this->assertNull($row['last_at']);
    }

    public function test_the_worst_offender_is_listed_first(): void
    {
        $quiet = $this->project('Recently updated');
        ProjectUpdate::create(['project_id' => $quiet->id, 'update_text' => 'x']);
        $this->project('Ignored for ages', Carbon::today()->subDays(50));

        $rows = app(DashboardService::class)->clientContactHealth();

        $this->assertSame('Ignored for ages', $rows->first()['client']->name);
    }

    public function test_finished_projects_are_left_out(): void
    {
        $this->project('Done and dusted')->update(['status' => 'completed']);

        $this->assertCount(0, app(DashboardService::class)->clientContactHealth());
    }

    public function test_posting_an_update_resets_the_count(): void
    {
        $project = $this->project('Mr. Muhsin', Carbon::today()->subDays(30));
        $service = app(DashboardService::class);

        $this->assertSame(30, $service->clientContactHealth()->sole()['days']);

        ProjectUpdate::create(['project_id' => $project->id, 'update_text' => 'Handed over today']);

        $this->assertSame(0, $service->clientContactHealth()->sole()['days']);
    }

    public function test_the_going_quiet_count_is_what_the_dashboard_shows(): void
    {
        $ok = $this->project('Fine');
        ProjectUpdate::create(['project_id' => $ok->id, 'update_text' => 'x']);
        $this->project('Quiet one', Carbon::today()->subDays(10));
        $this->project('Very quiet one', Carbon::today()->subDays(40));

        $this->assertSame(2, app(DashboardService::class)->clientsGoingQuietCount());
    }
}
