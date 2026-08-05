<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PageView;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\Analytics\Insights;
use App\Services\Analytics\Tracker;
use App\Support\Analytics\Countries;
use App\Support\Analytics\Events;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The reporting half: the screens open, the numbers are the right numbers, and only the right people can see them. */
class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function traffic(int $visitors = 3, string $channel = 'search'): void
    {
        foreach (range(1, $visitors) as $n) {
            $visitor = Visitor::create([
                'token' => (string) \Illuminate\Support\Str::uuid(),
                'first_seen_at' => now()->subDays($n),
                'last_seen_at' => now()->subMinutes($n),
                'first_source' => 'Google',
                'last_device' => $n % 2 ? 'mobile' : 'desktop',
                'last_country' => 'UG',
                'visits_count' => 1,
                'page_views_count' => 2,
            ]);

            $visit = Visit::create([
                'visitor_id' => $visitor->id,
                'entry_path' => '/', 'exit_path' => '/e-learning',
                'channel' => $channel, 'source' => 'Google', 'medium' => 'organic',
                'device' => $visitor->last_device, 'browser' => 'Chrome', 'os' => 'Android',
                'country' => 'UG', 'page_views_count' => 2, 'engaged_seconds' => 90,
                'is_bounce' => false,
                'started_at' => now()->subMinutes(30), 'last_activity_at' => now()->subMinutes($n),
            ]);

            foreach (['/', '/e-learning'] as $path) {
                PageView::create([
                    'visit_id' => $visit->id, 'visitor_id' => $visitor->id,
                    'path' => $path, 'status' => 200,
                    'engaged_seconds' => 45, 'scroll_percent' => 70,
                    'viewed_at' => now()->subMinutes(20),
                ]);
            }
        }
    }

    public function test_every_screen_opens_for_an_admin(): void
    {
        $admin = $this->admin();
        $this->traffic();

        foreach (['index', 'content', 'sources', 'live', 'visitors'] as $screen) {
            $this->actingAs($admin)->get(route('admin.analytics.'.$screen))
                ->assertOk()
                ->assertSee('Analytics', false);
        }

        $this->actingAs($admin)
            ->get(route('admin.analytics.visitor', Visitor::first()))
            ->assertOk();
    }

    public function test_every_screen_opens_with_no_data_at_all(): void
    {
        // The state the module ships in. An empty dashboard that throws is a
        // dashboard nobody ever sees working.
        $admin = $this->admin();

        foreach (['index', 'content', 'sources', 'live', 'visitors'] as $screen) {
            $this->actingAs($admin)->get(route('admin.analytics.'.$screen))->assertOk();
        }
    }

    public function test_a_student_cannot_reach_any_of_it(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $student->syncSpatieRole();

        // The back-office guard signs a non-staff account out and returns it to
        // the login screen rather than showing it a 403 that confirms the area
        // exists. That is the app's existing answer for every /admin route.
        foreach (['index', 'visitors', 'live'] as $screen) {
            $this->actingAs($student)->get(route('admin.analytics.'.$screen))
                ->assertRedirect(route('login'));
        }
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));
    }

    public function test_the_totals_count_what_they_say_they_count(): void
    {
        $this->traffic(3);

        $totals = Insights::forDays(30)->totals();

        $this->assertSame(3, $totals['visitors']);
        $this->assertSame(3, $totals['visits']);
        $this->assertSame(6, $totals['page_views']);
        $this->assertSame(90, $totals['avg_seconds'], 'engaged time per visit, not per page');
    }

    public function test_crawler_traffic_never_reaches_a_report(): void
    {
        $this->traffic(2);

        $bot = Visitor::create([
            'token' => (string) \Illuminate\Support\Str::uuid(), 'is_bot' => true,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        Visit::create([
            'visitor_id' => $bot->id, 'channel' => 'direct', 'page_views_count' => 400,
            'started_at' => now(), 'last_activity_at' => now(),
        ]);

        $insights = Insights::forDays(30);

        $this->assertSame(2, $insights->totals()['visitors']);
        $this->assertSame(1, $insights->botVisits(), 'still visible, still never counted');
    }

    public function test_the_daily_series_fills_the_quiet_days_with_zero(): void
    {
        $this->traffic(1);

        $series = Insights::forDays(7)->dailySeries('visitors');

        $this->assertCount(7, $series, 'a day with no traffic is a zero, not a missing key');
        $this->assertSame(array_keys($series), array_map(
            fn ($i) => CarbonImmutable::now()->subDays(6 - $i)->format('Y-m-d'),
            range(0, 6)
        ));
    }

    public function test_the_funnel_counts_people_not_actions(): void
    {
        $this->traffic(2);
        $visitor = Visitor::human()->first();

        // One person adding three things to a basket is one person.
        foreach (range(1, 3) as $ignored) {
            AnalyticsEvent::create([
                'visitor_id' => $visitor->id, 'name' => Events::CART_ADD,
                'category' => Events::CATEGORY_INTENT, 'occurred_at' => now(),
            ]);
        }

        $funnel = Insights::forDays(30)->funnel();

        $this->assertSame(2, $funnel['Visited the site']);
        $this->assertSame(1, $funnel['Put something in a basket']);
    }

    public function test_revenue_is_credited_to_the_source_that_first_brought_them(): void
    {
        $this->traffic(1);
        $visitor = Visitor::human()->sole();
        $visitor->update(['first_source' => 'YouTube']);

        AnalyticsEvent::create([
            'visitor_id' => $visitor->id, 'name' => Events::ORDER_PAID,
            'category' => Events::CATEGORY_CONVERSION, 'value' => 150000, 'currency' => 'UGX',
            'occurred_at' => now(),
        ]);

        $rows = Insights::forDays(30)->revenueByFirstTouch();

        $this->assertSame('YouTube', $rows->first()->source);
        $this->assertSame(150000.0, (float) $rows->first()->revenue);
        $this->assertSame(150000.0, Insights::forDays(30)->revenue());
    }

    public function test_an_enrollment_is_recorded_without_touching_its_controller(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        Visitor::create([
            'token' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        Enrollment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id, 'course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);

        $event = AnalyticsEvent::where('name', Events::ENROLLED)->sole();
        $this->assertSame($course->id, (int) $event->subject_id);
        $this->assertSame($user->id, $event->user_id);
    }

    public function test_the_rollup_summarises_a_day(): void
    {
        $this->traffic(3);

        $this->artisan('analytics:rollup', ['--days' => 2])->assertSuccessful();

        $today = AnalyticsDaily::whereDate('date', today())->sole();
        $this->assertSame(3, $today->visits);
        $this->assertSame(6, $today->page_views);
        $this->assertNotEmpty($today->by_channel);
    }

    /**
     * A twelve-month chart must not scan every page view ever recorded. It
     * reads the rollup, and only when the rollup actually covers the window,
     * because half-rolled and half-live is how two charts of the same thing
     * come to disagree.
     */
    public function test_a_long_window_is_answered_from_the_rollup(): void
    {
        $this->traffic(2);

        // Nothing rolled up yet: the long window must still be correct.
        $live = Insights::forDays(90)->dailySeries('visitors');
        $this->assertCount(90, $live);
        $this->assertSame(2, $live[today()->format('Y-m-d')]);

        // A rollup that covers the window is believed, today excepted.
        for ($i = 0; $i < 90; $i++) {
            AnalyticsDaily::updateOrCreate(
                ['date' => today()->subDays($i)->toDateString()],
                ['visitors' => 7],
            );
        }

        $rolled = Insights::forDays(90)->dailySeries('visitors');
        $this->assertSame(7, $rolled[today()->subDays(5)->format('Y-m-d')], 'an older day comes from the rollup');
        $this->assertCount(90, $rolled);
    }

    public function test_pruning_keeps_the_rollup_and_drops_the_detail(): void
    {
        $this->traffic(1);
        PageView::query()->update(['viewed_at' => now()->subDays(1000)]);
        Visit::query()->update(['started_at' => now()->subDays(1000)]);
        AnalyticsDaily::create(['date' => now()->subDays(1000)->toDateString(), 'visits' => 9]);

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame(0, PageView::count());
        $this->assertSame(1, AnalyticsDaily::count(), 'the history of the numbers survives the detail');
    }

    public function test_geolocation_stays_off_until_it_is_switched_on(): void
    {
        config(['analytics.geo.enabled' => false]);

        // Nothing may leave this server without the owner having chosen it.
        \Illuminate\Support\Facades\Http::fake();
        $this->artisan('analytics:geolocate')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    /* The beacon ---------------------------------------------------------- */

    public function test_the_beacon_completes_a_page_with_reading_time_and_depth(): void
    {
        $this->get('/');
        $view = PageView::sole();
        $this->assertNull($view->engaged_seconds);

        $token = app(Tracker::class)->beaconToken(request()->create('/'));
        $this->assertIsString($token);

        $this->call('POST', route('analytics.beacon'), [], [], [], [], json_encode([
            'v' => $token, 's' => 42, 'd' => 88,
        ]))->assertNoContent();

        $view->refresh();
        $this->assertSame(42, $view->engaged_seconds);
        $this->assertSame(88, $view->scroll_percent);
        $this->assertSame(42, Visit::sole()->engaged_seconds);
    }

    public function test_the_beacon_clamps_a_tab_left_open_all_afternoon(): void
    {
        $this->get('/');
        $token = app(Tracker::class)->beaconToken(request()->create('/'));

        $this->call('POST', route('analytics.beacon'), [], [], [], [], json_encode([
            'v' => $token, 's' => 999999, 'd' => 400,
        ]))->assertNoContent();

        $view = PageView::sole();
        $this->assertSame(3600, $view->engaged_seconds);
        $this->assertSame(100, $view->scroll_percent);
    }

    public function test_the_beacon_refuses_a_handle_it_did_not_issue(): void
    {
        $this->get('/');

        foreach (['1', 'not-encrypted', base64_encode('1|/')] as $forged) {
            $this->call('POST', route('analytics.beacon'), [], [], [], [], json_encode([
                'v' => $forged, 's' => 500, 'd' => 100,
            ]))->assertNoContent();
        }

        $this->assertNull(PageView::sole()->engaged_seconds, 'nothing a stranger sent was believed');
    }

    public function test_the_browser_may_report_a_click_but_not_a_payment(): void
    {
        $this->get('/');
        $token = app(Tracker::class)->beaconToken(request()->create('/'));

        $this->call('POST', route('analytics.beacon'), [], [], [], [], json_encode([
            'v' => $token, 's' => 10, 'd' => 50,
            'e' => [
                ['n' => Events::OUTBOUND_CLICK, 'l' => 'github.com/mubahood'],
                ['n' => Events::ORDER_PAID, 'l' => 'a free course, apparently'],
                ['n' => 'made.up.event', 'l' => 'nope'],
            ],
        ]))->assertNoContent();

        $this->assertSame(1, AnalyticsEvent::count());
        $this->assertSame(Events::OUTBOUND_CLICK, AnalyticsEvent::sole()->name);
    }

    public function test_a_flag_is_built_from_any_country_code(): void
    {
        $this->assertSame('Uganda', Countries::name('UG'));
        $this->assertSame('🇺🇬', Countries::flag('UG'));
        $this->assertSame('ZZ', Countries::name('ZZ'), 'an unnamed country still shows its code');
        $this->assertSame('', Countries::flag('nonsense'));
    }
}
