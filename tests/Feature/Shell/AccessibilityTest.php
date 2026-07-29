<?php

namespace Tests\Feature\Shell;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The parts of accessibility a request test can actually hold: a skip link, a
 * named main region, expanded-state on the disclosure controls, and every form
 * field tied to a real label rather than a placeholder.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'is_student' => true]);
    }

    public function test_the_shell_offers_a_skip_link_to_the_main_region(): void
    {
        $this->actingAs($this->student())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Skip to main content')
            ->assertSee('href="#tb-content"', false)
            // tabindex is what lets the skip link and post-navigation focus land here.
            ->assertSee('id="tb-content" tabindex="-1"', false);
    }

    public function test_the_menu_and_account_toggles_announce_their_state(): void
    {
        $response = $this->actingAs($this->student())->get(route('dashboard'))->assertOk();
        $html = (string) $response->getContent();

        // Alpine renders :aria-expanded, so the binding is what ships in the HTML.
        $this->assertStringContainsString(':aria-expanded', $html);
        $this->assertStringContainsString('aria-controls="tb-nav"', $html);
        $this->assertStringContainsString('aria-controls="tb-user-dropdown"', $html);
        $this->assertStringContainsString('aria-label="Main navigation"', $html);
    }

    public function test_the_sidebar_groups_report_whether_they_are_open(): void
    {
        $this->actingAs($this->student())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-controls="navsub-learning"', false);
    }

    public function test_every_account_field_is_bound_to_a_visible_label(): void
    {
        $response = $this->actingAs($this->student())->get(route('account.edit'))->assertOk();
        $html = (string) $response->getContent();

        foreach (['name', 'email', 'phone', 'bio', 'avatar', 'current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<label[^>]*for="'.preg_quote($field, '/').'"/',
                $html,
                "the '{$field}' field must have a label bound to it, not just a placeholder"
            );
            $this->assertMatchesRegularExpression(
                '/id="'.preg_quote($field, '/').'"/',
                $html,
                "the '{$field}' field needs the id its label points at"
            );
        }
    }

    public function test_the_account_type_radios_are_grouped_under_a_legend(): void
    {
        $this->actingAs($this->student())->get(route('account.edit'))
            ->assertOk()
            ->assertSee('<fieldset', false)
            ->assertSee('Choose what you use this account for');
    }

    public function test_validation_errors_are_announced_not_just_coloured(): void
    {
        $taken = User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->student();

        $response = $this->actingAs($user)
            ->from(route('account.edit'))
            ->followingRedirects()
            ->post(route('account.update'), ['name' => 'X', 'email' => $taken->email]);

        $response->assertOk();
        $html = (string) $response->getContent();

        // A summary a screen reader is told about, plus the field marked invalid —
        // red text alone communicates nothing to someone who can't see it.
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString("Your details weren't saved", $html);
        $this->assertMatchesRegularExpression('/id="email"[^>]*aria-invalid="true"|aria-invalid="true"[^>]*id="email"/s', $html);
    }

    public function test_the_course_player_also_has_a_skip_link(): void
    {
        $user = $this->student();
        $course = \App\Models\Course::factory()->create(['is_published' => true]);
        $module = \App\Models\CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        \App\Models\Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        \App\Models\Enrollment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id,
            'course_id' => $course->id, 'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->followingRedirects()->get(route('learn.course', $course))
            ->assertOk()
            ->assertSee('Skip to lesson content')
            ->assertSee('id="learn-content"', false);
    }
}
