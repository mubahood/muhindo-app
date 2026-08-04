<?php

namespace Tests\Feature\Public;

use App\Support\Spam\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * reCAPTCHA is off until both keys are configured, which means the suite has
 * to cover two different systems: the site as it runs today with no keys, and
 * the site the moment the keys are pasted in. Testing only the first would let
 * the whole feature ship without ever having been executed.
 *
 * Google is never called. The package resolves `captcha` from the container at
 * validation time, so a fake bound there decides the verdict.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    /** Turn the feature on and decide in advance what Google would have said. */
    private function withCaptcha(bool $verifies = true): void
    {
        config(['captcha.sitekey' => 'test-site-key', 'captcha.secret' => 'test-secret']);

        $this->app->instance('captcha', new class($verifies)
        {
            public function __construct(private bool $verifies) {}

            public function verifyResponse($response, $clientIp = null): bool
            {
                return $this->verifies && $response === 'valid-token';
            }

            public function display($attributes = [], $lang = null): string
            {
                return '<div class="g-recaptcha" data-sitekey="test-site-key"></div>';
            }

            public function renderJs($lang = null, $callback = false, $onLoadClass = 'onloadCallBack'): string
            {
                return '<script src="https://www.google.com/recaptcha/api.js"></script>';
            }
        });
    }

    /**
     * Registration, which is the public form the captcha guards now — the
     * contact form it was written against is gone.
     *
     * @return array<string, string>
     */
    private function contactPayload(array $overrides = []): array
    {
        return $this->shielded($overrides + [
            'name' => 'Grace Nakato',
            'email' => 'grace@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'account_type' => 'student',
            'terms' => '1',
        ]);
    }

    // ── With no keys configured: the site as it runs today ──────────────────

    public function test_it_is_off_until_both_keys_are_present(): void
    {
        config(['captcha.sitekey' => '', 'captcha.secret' => '']);
        $this->assertFalse(Captcha::enabled());
        $this->assertSame([], Captcha::rules());

        // A key without a secret renders a widget nothing verifies.
        config(['captcha.sitekey' => 'only-a-site-key']);
        $this->assertFalse(Captcha::enabled());
    }

    public function test_public_forms_still_work_with_no_keys(): void
    {
        config(['captcha.sitekey' => '', 'captcha.secret' => '']);

        $this->post(route('register'), $this->contactPayload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'grace@example.com']);
    }

    public function test_no_widget_is_rendered_with_no_keys(): void
    {
        config(['captcha.sitekey' => '', 'captcha.secret' => '']);

        $this->get(route('register'))->assertOk()->assertDontSee('g-recaptcha', false);
    }

    // ── With keys configured: the site once it is switched on ───────────────

    public function test_the_widget_appears_on_every_public_form_once_keys_are_set(): void
    {
        $this->withCaptcha();

        foreach (['register', 'login', 'password.request'] as $route) {
            $html = (string) $this->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('g-recaptcha', $html, "{$route} renders no widget");
            $this->assertStringContainsString('recaptcha/api.js', $html, "{$route} never loads the script");
        }
    }

    public function test_a_submission_with_no_token_is_refused(): void
    {
        $this->withCaptcha();

        // The bypass worth guarding: omitting the field entirely rather than
        // sending a wrong one. Without `required` this would sail through.
        $this->post(route('register'), $this->contactPayload())
            ->assertSessionHasErrors(Captcha::FIELD);

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.com']);
    }

    public function test_a_submission_google_rejects_is_refused(): void
    {
        $this->withCaptcha(verifies: false);

        $this->post(route('register'), $this->contactPayload() + [Captcha::FIELD => 'replayed-token'])
            ->assertSessionHasErrors(Captcha::FIELD);

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.com']);
    }

    public function test_a_verified_submission_goes_through(): void
    {
        $this->withCaptcha();

        $this->post(route('register'), $this->contactPayload() + [Captcha::FIELD => 'valid-token'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'grace@example.com']);
    }

    public function test_the_token_never_reaches_the_model(): void
    {
        // User has no such column; a token that survives validation into the
        // create() call is a 500 on the happy path.
        $this->withCaptcha();

        $this->post(route('register'), $this->contactPayload([Captcha::FIELD => 'valid-token']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'grace@example.com']);
    }

    public function test_every_public_form_is_guarded(): void
    {
        $this->withCaptcha();

        // Each of these creates a record or sends mail on behalf of a stranger,
        // which is precisely what makes them worth automating against.
        $cases = [
            ['register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd', 'account_type' => 'student', 'terms' => '1']],
            ['login', ['email' => 'a@example.com', 'password' => 'Str0ng!Passw0rd']],
            ['password.email', ['email' => 'a@example.com']],
        ];

        foreach ($cases as [$route, $payload]) {
            $this->post(route($route), $this->shielded($payload))
                ->assertSessionHasErrors(Captcha::FIELD);
            $this->flushSession();
        }
    }

    public function test_the_failure_message_is_written_for_a_person(): void
    {
        $this->withCaptcha();

        $this->post(route('register'), $this->contactPayload())
            ->assertSessionHasErrors([Captcha::FIELD => 'Please confirm you are not a robot.']);
    }
}
