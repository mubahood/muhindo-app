<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The WhatsApp launcher.
 *
 * What is worth testing is not that a button exists, but that the message it
 * composes is right: the number, the two intents, and the fact that the
 * wording knows which page it was pressed on. A generic "hi" is what this
 * feature exists to prevent.
 */
class WhatsAppLauncherTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBER = '256783204665';

    public function test_it_is_on_the_public_pages(): void
    {
        foreach (['/', '/about', '/e-learning', '/source-code', '/work'] as $path) {
            $this->get($path)->assertOk()->assertSee('wa.me/'.self::NUMBER, false);
        }
    }

    public function test_it_offers_exactly_two_intents(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        // The single question that makes the message write itself.
        $this->assertStringContainsString('What brings you here?', $html);
        $this->assertStringContainsString('I want to learn', $html);
        $this->assertStringContainsString('I want something built', $html);
    }

    public function test_both_links_go_to_the_right_number_with_a_prepared_message(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('#https://wa\.me/(\d+)\?text=([^"]+)#', $html, $matches, PREG_SET_ORDER);

        $this->assertCount(2, $matches, 'one link per intent, no more');

        foreach ($matches as [$whole, $number, $text]) {
            $this->assertSame(self::NUMBER, $number);
            $message = rawurldecode($text);
            $this->assertStringStartsWith('Hello Muhindo,', $message);
            $this->assertGreaterThan(40, strlen($message), 'a prepared message, not a greeting');
        }
    }

    /** A course page should name the course, so the first line is already useful. */
    public function test_the_message_knows_it_is_on_a_course_page(): void
    {
        $course = Course::factory()->create([
            'title' => 'Flutter Mobile App Development',
            'is_published' => true,
        ]);

        $html = (string) $this->get(route('courses.show', $course))->assertOk()->getContent();

        preg_match_all('#https://wa\.me/\d+\?text=([^"]+)#', $html, $matches);
        $messages = array_map('rawurldecode', $matches[1]);

        $this->assertStringContainsString('Flutter Mobile App Development', $messages[0]);
        // And the hire option still reads as a hire, not a course enquiry.
        $this->assertStringContainsString('project', $messages[1]);
    }

    public function test_the_message_knows_it_is_on_a_work_page(): void
    {
        $project = PortfolioProject::create([
            'title' => 'ULITS National Livestock Traceability',
            'slug' => 'ulits-'.Str::random(5),
            'description' => 'A national system.',
            'is_featured' => false,
            'sort_order' => 0,
        ]);

        $html = (string) $this->get(route('portfolio.project', $project))->assertOk()->getContent();

        preg_match_all('#https://wa\.me/\d+\?text=([^"]+)#', $html, $matches);
        $messages = array_map('rawurldecode', $matches[1]);

        $this->assertStringContainsString('ULITS National Livestock Traceability', $messages[1]);
        $this->assertStringContainsString('similar', $messages[1]);
    }

    public function test_a_page_about_nothing_in_particular_still_gets_a_usable_message(): void
    {
        $html = (string) $this->get('/about')->assertOk()->getContent();

        preg_match_all('#https://wa\.me/\d+\?text=([^"]+)#', $html, $matches);
        $messages = array_map('rawurldecode', $matches[1]);

        $this->assertStringContainsString('recommend', $messages[0]);
        $this->assertStringContainsString('project', $messages[1]);
    }

    /**
     * The layering rule. The header, the mobile menu and the mobile action bar
     * all sit above this on purpose: a floating circle must never cover Buy,
     * Hire, or an open menu.
     */
    public function test_it_sits_below_the_controls_it_must_never_cover(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\.wa\{[^}]*z-index:45/', $html);
        $this->assertMatchesRegularExpression('/\.act-bar\{[^}]*z-index:50/', $html);
    }

    /**
     * Found while testing the launcher, and nothing to do with it.
     *
     * Blade's startSection() treats a null second argument as "no content
     * supplied" and opens an output buffer to capture the section body, which
     * for a one-line @section never arrives. So a portfolio project saved with
     * no description left a buffer open for the rest of the request and
     * swallowed the page into its own meta description.
     */
    public function test_a_record_with_no_description_does_not_break_its_page(): void
    {
        $project = PortfolioProject::create([
            'title' => 'A system with no description yet',
            'slug' => 'blank-'.Str::random(5),
            'description' => null,
            'is_featured' => false,
            'sort_order' => 0,
        ]);

        $level = ob_get_level();

        $this->get(route('portfolio.project', $project))->assertOk();

        $this->assertSame($level, ob_get_level(), 'the page left an output buffer open');
    }

    public function test_it_stays_out_of_the_back_office(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        // The admin layout is a different layout; a customer-facing chat
        // button has no business on the screens used to run the business.
        $this->actingAs($admin)->get(route('admin.courses.index'))->assertOk()
            ->assertDontSee('wa.me/'.self::NUMBER, false);
    }
}
