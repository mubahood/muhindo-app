<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Course;
use App\Models\PageView;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\Analytics\Tracker;
use App\Support\Analytics\Agent;
use App\Support\Analytics\Channel;
use App\Support\Analytics\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The capture half: what gets recorded, what deliberately does not, and the
 * two things that are easy to get wrong and impossible to notice afterwards,
 * counting crawlers as an audience and losing everything somebody read before
 * they signed in.
 */
class VisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /**
     * Carry the visitor cookie into every later request in this test.
     *
     * The test client does not feed a response's Set-Cookie back into the next
     * request the way a browser does, so without this every call looks like a
     * brand new browser and nothing about returning visitors can be tested at
     * all. The value is the real token, encrypted by the same helper the
     * framework uses, so the path under test is the production one.
     */
    private function keepCookie(): void
    {
        $this->withCookie(\App\Services\Analytics\Tracker::COOKIE, Visitor::latest('id')->firstOrFail()->token);
    }

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_a_visit_records_the_visitor_the_session_and_the_page(): void
    {
        $this->withServerVariables(['HTTP_USER_AGENT' => self::IPHONE])->get('/')->assertOk();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Visit::count());
        $this->assertSame(1, PageView::count());

        $visit = Visit::sole();
        $this->assertSame('mobile', $visit->device);
        $this->assertSame('Safari', $visit->browser);
        $this->assertSame('iOS', $visit->os);
        $this->assertSame('/', $visit->entry_path);
        $this->assertTrue($visit->is_bounce);
    }

    public function test_the_same_browser_is_one_visitor_across_pages(): void
    {
        $this->get('/');
        $this->keepCookie();
        $this->get('/about');
        $this->get('/services');

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, Visit::count());
        $this->assertSame(3, PageView::count());

        $visit = Visit::sole();
        $this->assertSame(3, $visit->page_views_count);
        // Three pages is not a bounce, whatever the first request looked like.
        $this->assertFalse($visit->is_bounce);
        $this->assertSame('/services', $visit->exit_path);
    }

    public function test_a_new_visit_begins_after_the_idle_window(): void
    {
        $this->get('/');
        $this->keepCookie();

        Visit::sole()->forceFill([
            'last_activity_at' => now()->subMinutes(Tracker::VISIT_IDLE_MINUTES + 5),
        ])->save();

        $this->get('/');

        $this->assertSame(1, Visitor::count(), 'the browser is still the same person');
        $this->assertSame(2, Visit::count(), 'but this is a second sitting');
        $this->assertSame(2, Visitor::sole()->visits_count);
    }

    public function test_the_referrer_becomes_a_channel_and_a_source(): void
    {
        $this->withServerVariables(['HTTP_REFERER' => 'https://www.youtube.com/watch?v=xyz'])->get('/');

        $visit = Visit::sole();
        $this->assertSame(Channel::SOCIAL, $visit->channel);
        $this->assertSame('YouTube', $visit->source);
        $this->assertSame('youtube.com', $visit->referrer_host);
    }

    public function test_a_tagged_link_beats_the_referrer(): void
    {
        $this->withServerVariables(['HTTP_REFERER' => 'https://www.google.com/'])
            ->get('/?utm_source=newsletter&utm_medium=email&utm_campaign=august');

        $visit = Visit::sole();
        $this->assertSame(Channel::EMAIL, $visit->channel);
        $this->assertSame('newsletter', $visit->source);
        $this->assertSame('august', $visit->campaign);
    }

    public function test_a_crawler_is_recorded_and_flagged_rather_than_counted(): void
    {
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/');

        $visitor = Visitor::sole();
        $this->assertTrue($visitor->is_bot, 'the traffic is kept so it can be audited');
        $this->assertSame(0, Visitor::human()->count(), 'and never counted as an audience');
    }

    public function test_the_back_office_is_never_measured(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();

        $this->assertSame(0, PageView::count());
        $this->assertSame(0, Visitor::count());
    }

    public function test_an_admin_browsing_the_public_site_is_not_counted_either(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->actingAs($admin)->get('/')->assertOk();

        $this->assertSame(0, PageView::count(), 'measuring your own site visits is how a one-person business fakes its traffic');
    }

    public function test_a_redirect_is_not_a_page_view(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertRedirect();

        $this->assertSame(0, PageView::count());
    }

    public function test_a_page_view_knows_what_it_was_about(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $this->get(route('courses.show', $course))->assertOk();

        $view = PageView::whereNotNull('subject_type')->sole();
        $this->assertSame(Course::class, $view->subject_type);
        $this->assertSame($course->id, (int) $view->subject_id);
        $this->assertSame($course->id, $view->subject->id);
    }

    public function test_a_page_that_was_not_found_is_still_recorded(): void
    {
        $this->get('/a-link-somebody-else-published')->assertNotFound();

        $this->assertSame(404, PageView::sole()->status);
    }

    /**
     * The one that matters most. Somebody reads for a fortnight, then
     * registers; without the backfill their history begins on the day they
     * registered and the fortnight that actually sold them is invisible.
     */
    public function test_signing_in_claims_the_whole_anonymous_history(): void
    {
        $this->get('/');
        $this->keepCookie();
        $this->get('/services');

        $visitor = Visitor::sole();
        $this->assertNull($visitor->user_id);
        $this->assertSame(2, PageView::whereNull('user_id')->count());

        $user = User::factory()->create(['password' => 'password123']);
        $this->post('/login', $this->shielded(['email' => $user->email, 'password' => 'password123']));

        $visitor->refresh();
        $this->assertSame($user->id, $visitor->user_id);
        $this->assertNotNull($visitor->identified_at);
        $this->assertSame(2, PageView::where('user_id', $user->id)->count(), 'the pages read before the account existed now belong to it');
        $this->assertSame(0, Visit::whereNull('user_id')->count(), 'and so does the session they were read in');
    }

    public function test_registering_is_recorded_as_a_conversion(): void
    {
        $this->get('/');
        $this->keepCookie();

        $this->post(route('register'), $this->shielded([
            'name' => 'New Person', 'email' => 'new@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'account_type' => 'student', 'terms' => '1',
        ]));

        $event = AnalyticsEvent::where('name', Events::SIGNUP)->sole();
        $this->assertSame(Events::CATEGORY_CONVERSION, $event->category);
        $this->assertNotNull(Visitor::sole()->converted_at);
    }

    public function test_the_user_agent_parser_tells_people_from_scripts(): void
    {
        $this->assertTrue(Agent::parse('curl/8.1.2')->isBot);
        $this->assertTrue(Agent::parse('')->isBot, 'no user-agent at all is a script that did not bother to lie');
        $this->assertTrue(Agent::parse('facebookexternalhit/1.1')->isBot);
        $this->assertTrue(Agent::parse('GPTBot/1.0')->isBot);

        $chrome = Agent::parse(self::CHROME);
        $this->assertFalse($chrome->isBot);
        $this->assertSame('desktop', $chrome->device);
        $this->assertSame('Chrome', $chrome->browser);
        $this->assertSame('Windows', $chrome->os);

        // Android without "Mobile" is the documented way to spot a tablet.
        $this->assertSame('tablet', Agent::parse('Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 Chrome/120.0 Safari/537.36')->device);
        $this->assertSame('mobile', Agent::parse(self::IPHONE)->device);
    }

    public function test_tracking_can_be_switched_off_entirely(): void
    {
        config(['analytics.enabled' => false]);

        $this->get('/')->assertOk();

        $this->assertSame(0, Visitor::count());
    }
}
