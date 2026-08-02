<?php

namespace App\Support\Spam;

/**
 * The gate in front of anhskohbo/no-captcha (Google reCAPTCHA v2).
 *
 * Every public form asks this class for its rules rather than hard-coding the
 * `captcha` rule, for one reason: the widget is only real once a site key and
 * a secret exist. Hard-coding it would mean that the moment the package is
 * installed — before anybody has been to the Google console — every public
 * form on the site starts rejecting every submission, because the rule fails
 * closed against an empty secret. Here, no keys means no rule and no widget:
 * the forms keep working and the honeypot in FormShield keeps covering them.
 * Paste the two keys into .env and every form picks the widget up at once,
 * with no further code change.
 *
 * The two layers are deliberate rather than redundant. reCAPTCHA needs
 * JavaScript and a round trip to Google; the honeypot and the timing check in
 * FormShield cost nothing, work with scripting off, and catch the crude
 * scripted POST that never loads the widget at all.
 */
final class Captcha
{
    /** The field Google's widget posts back under. */
    public const FIELD = 'g-recaptcha-response';

    /**
     * Configured means both halves are present. A site key on its own renders
     * a widget nothing verifies; a secret on its own verifies a widget that
     * was never rendered. Either alone is worse than neither.
     */
    public static function enabled(): bool
    {
        return filled(config('captcha.sitekey')) && filled(config('captcha.secret'));
    }

    /**
     * Merge into a form's rules. `required` matters as much as `captcha`:
     * without it a submission that simply omits the field skips validation
     * entirely, which is the easiest bypass there is.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return self::enabled() ? [self::FIELD => ['required', 'captcha']] : [];
    }

    /**
     * "The g-recaptcha-response field is required" tells a person nothing
     * about what they did wrong or how to fix it.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            self::FIELD.'.required' => 'Please confirm you are not a robot.',
            self::FIELD.'.captcha' => 'That check did not pass. Please tick the box again.',
        ];
    }
}
