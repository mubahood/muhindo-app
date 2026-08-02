<?php

namespace Tests\Feature\Public;

use App\Models\ContactMessage;
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

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'A Person', 'email' => 'person@example.com',
            'message' => 'I would like to talk about a project.',
            FormShield::TIMESTAMP => $this->humanStamp(),
        ], $overrides);
    }

    public function test_a_person_can_send_the_contact_form(): void
    {
        $this->post(route('contact.store'), $this->contactPayload())->assertRedirect();

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_a_filled_honeypot_is_silently_dropped(): void
    {
        // Answered exactly as a real submission would be. A bot told it failed
        // just retries with the field cleared.
        $this->post(route('contact.store'), $this->contactPayload([FormShield::HONEYPOT => 'http://spam.example']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_an_instant_submission_is_refused(): void
    {
        // Nobody reads a form and completes it in under three seconds.
        $this->post(route('contact.store'), $this->contactPayload([FormShield::TIMESTAMP => $this->humanStamp(0)]))
            ->assertSessionHasErrors(FormShield::TIMESTAMP);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_stale_form_is_refused_with_an_explanation(): void
    {
        // A real person can trip this by leaving a tab open, so unlike the
        // honeypot they are told what happened.
        $this->post(route('contact.store'), $this->contactPayload([FormShield::TIMESTAMP => $this->humanStamp(60 * 60 * 5)]))
            ->assertSessionHasErrors(FormShield::TIMESTAMP);
    }

    public function test_a_forged_timestamp_is_refused(): void
    {
        // Plain text would let a bot post any value it liked; the stamp is
        // encrypted with the app key, so an edited one cannot be decrypted.
        $this->post(route('contact.store'), $this->contactPayload([FormShield::TIMESTAMP => '1700000000']))
            ->assertSessionHasErrors(FormShield::TIMESTAMP);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_missing_stamp_is_refused(): void
    {
        $payload = $this->contactPayload();
        unset($payload[FormShield::TIMESTAMP]);

        $this->post(route('contact.store'), $payload)->assertSessionHasErrors(FormShield::TIMESTAMP);
    }

    public function test_the_project_form_is_shielded_too(): void
    {
        $this->post(route('start-a-project.store'), [
            'name' => 'A Person', 'email' => 'p@example.com',
            'project_type' => 'website', 'description' => 'A shop for my business.',
            FormShield::HONEYPOT => 'bot',
            FormShield::TIMESTAMP => $this->humanStamp(),
        ])->assertRedirect();

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
        foreach ([route('contact'), route('start-a-project'), route('register')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('name="'.FormShield::HONEYPOT.'"', $html, "{$url} is missing the honeypot");
            $this->assertStringContainsString('name="'.FormShield::TIMESTAMP.'"', $html, "{$url} is missing the stamp");
        }
    }

    public function test_the_honeypot_is_hidden_from_assistive_tech(): void
    {
        /* Hidden by inline style rather than a class. It used to rely on
           `.hp-field` from the marketing layout, so on the auth pages — which
           use a different layout — the honeypot rendered fully visible, label
           and all. Anyone who typed in it would have been silently discarded. */
        foreach ([route('contact'), route('register')] as $url) {
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
