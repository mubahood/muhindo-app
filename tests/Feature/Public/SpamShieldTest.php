<?php

namespace Tests\Feature\Public;

use App\Models\ProjectInquiry;
use App\Models\User;
use App\Support\Spam\FormShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Public forms are protected without asking a person to solve anything: a
 * honeypot, and an encrypted timestamp issued with the form.
 */
class SpamShieldTest extends TestCase
{
    use RefreshDatabase;

    /** A form issued far enough in the past to look human. */
    private function humanStamp(int $secondsAgo = 20): string
    {
        return Crypt::encryptString((string) now()->subSeconds($secondsAgo)->getTimestamp());
    }

    /**
     * Registration is what the shield guards now. The contact form it was
     * written against is gone "get in touch" produced messages nobody could
     * act on, and hiring goes through an account instead.
     */
    private function contactPayload(array $overrides = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'name' => 'A Person',
            'email' => "person{$n}@example.com",
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
            'account_type' => 'student',
            FormShield::TIMESTAMP => $this->humanStamp(),
        ], $overrides);
    }

    public function test_a_person_can_send_a_public_form(): void
    {
        $this->post(route('register'), $this->contactPayload())->assertRedirect();

        $this->assertSame(1, \App\Models\User::count());
    }

    public function test_a_filled_honeypot_is_silently_dropped(): void
    {
        // Answered exactly as a real submission would be. A bot told it failed
        // just retries with the field cleared.
        $this->post(route('register'), $this->contactPayload([FormShield::HONEYPOT => 'http://spam.example']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, \App\Models\User::count());
    }

    public function test_an_instant_submission_is_refused(): void
    {
        // Nobody reads a form and completes it in under two seconds.
        $this->post(route('register'), $this->contactPayload([FormShield::TIMESTAMP => $this->humanStamp(0)]))
            ->assertSessionHasErrors(FormShield::TIMESTAMP);

        $this->assertSame(0, \App\Models\User::count());
    }

    public function test_a_stale_form_is_refused_with_an_explanation(): void
    {
        // A real person can trip this by leaving a tab open, so unlike the
        // honeypot they are told what happened.
        $this->post(route('register'), $this->contactPayload([FormShield::TIMESTAMP => $this->humanStamp(60 * 60 * 5)]))
            ->assertSessionHasErrors(FormShield::TIMESTAMP);
    }

    public function test_a_forged_timestamp_is_refused(): void
    {
        // Plain text would let a bot post any value it liked; the stamp is
        // encrypted with the app key, so an edited one cannot be decrypted.
        $this->post(route('register'), $this->contactPayload([FormShield::TIMESTAMP => '1700000000']))
            ->assertSessionHasErrors(FormShield::TIMESTAMP);

        $this->assertSame(0, \App\Models\User::count());
    }

    public function test_a_missing_stamp_is_refused(): void
    {
        $payload = $this->contactPayload();
        unset($payload[FormShield::TIMESTAMP]);

        $this->post(route('register'), $payload)->assertSessionHasErrors(FormShield::TIMESTAMP);
    }

    /**
     * The public lead form is gone. Proposing a project now needs an account,
     * which is a harder wall than a honeypot. A bot has to get through
     * registration, and registration is shielded.
     */
    public function test_proposing_a_project_needs_an_account(): void
    {
        $this->post(route('propose.store'), [
            'title' => 'Spam', 'category' => 'website',
            'description' => str_repeat('buy cheap things ', 5),
            'timeline' => 'asap', 'budget_currency' => 'UGX', 'phone' => '0700000000',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, ProjectInquiry::count());
    }

    public function test_registration_is_shielded_too(): void
    {
        $this->post(route('register'), [
            'name' => 'Bot', 'email' => 'bot@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'terms' => '1', 'account_type' => 'student',
            FormShield::HONEYPOT => 'bot',
            FormShield::TIMESTAMP => $this->humanStamp(),
        ])->assertRedirect();

        $this->assertSame(0, User::where('email', 'bot@example.com')->count());
    }

    public function test_every_public_form_ships_the_shield(): void
    {
        foreach ([route('register'), route('login'), route('password.request')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('name="'.FormShield::HONEYPOT.'"', $html, "{$url} is missing the honeypot");
            $this->assertStringContainsString('name="'.FormShield::TIMESTAMP.'"', $html, "{$url} is missing the stamp");
        }
    }

    /**
     * The bug this class exists to prevent a repeat of.
     *
     * The honeypot was called `website`. Chrome, Safari and every password
     * manager fill a field of that name from a saved address card, whether or
     * not it is on screen, and autocomplete="off" is documented as ignored for
     * exactly that. So people with password managers had the trap filled in
     * for them and were refused an account, a sign-in and a password reset,
     * silently, with no error anyone could act on.
     *
     * The defence is the name itself, so the name is what gets asserted.
     */
    public function test_the_honeypot_is_not_named_after_anything_a_browser_autofills(): void
    {
        $name = FormShield::HONEYPOT;

        foreach (['url', 'web', 'site', 'mail', 'name', 'phone', 'tel', 'addr',
            'city', 'zip', 'post', 'country', 'company', 'organi', 'user', 'nick',
            'bday', 'birth', 'card', 'cc-', 'street', 'homepage'] as $token) {
            $this->assertStringNotContainsString($token, strtolower($name),
                "A honeypot containing \"{$token}\" invites the autofill that broke this before");
        }
    }

    public function test_the_honeypot_tells_password_managers_to_leave_it_alone(): void
    {
        // Each manager honours its own attribute and ignores the others, so
        // all of them have to be present for the field to be left alone.
        $html = (string) $this->get(route('register'))->assertOk()->getContent();

        foreach (['data-1p-ignore', 'data-lpignore="true"', 'data-bwignore',
            'data-form-type="other"', 'autocomplete="off"'] as $attribute) {
            $this->assertStringContainsString($attribute, $html,
                "the honeypot must carry {$attribute}");
        }
    }

    public function test_a_dropped_submission_leaves_a_trace(): void
    {
        // Silence is right for a bot and disastrous for a false positive: no
        // error for the person, no report for the owner, and no way to find
        // out weeks later why somebody never got an account. The log is it.
        \Illuminate\Support\Facades\Log::spy();

        $this->post(route('register'), $this->contactPayload([
            FormShield::HONEYPOT => 'http://spam.example',
            'name' => 'Looks Like A Person',
        ]))->assertRedirect();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'honeypot')
                && ($context['form'] ?? null) === 'register'
                && ($context['name'] ?? null) === 'Looks Like A Person');
    }

    public function test_the_honeypot_is_hidden_from_assistive_tech(): void
    {
        /* Hidden by inline style rather than a class. It used to rely on
           `.hp-field` from the marketing layout, so on the auth pages, which
           use a different layout, the honeypot rendered fully visible, label
           and all. Anyone who typed in it would have been silently discarded. */
        foreach ([route('register'), route('login')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/aria-hidden="true"\s+style="[^"]*left:-9999px/s', $html,
                "{$url} must hide the honeypot without depending on a layout's stylesheet");
            $this->assertStringContainsString('tabindex="-1"', $html);
        }
    }

    /**
     * The markup shipped on these two before the check did, which meant a
     * hidden field that looked like protection and was not.
     */
    public function test_the_honeypot_is_actually_enforced_on_login_and_password_reset(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret6'), 'is_active' => true]);

        $this->post(route('login'), [
            'email' => $user->email, 'password' => 'secret6',
            FormShield::HONEYPOT => 'http://spam.example',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // The same credentials without the trap tripped still work, so the
        // check discriminates rather than simply breaking the form.
        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret6'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_a_tripped_password_reset_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
            FormShield::HONEYPOT => 'http://spam.example',
        ])->assertSessionHasNoErrors();

        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }
}
