<?php

namespace App\Support\Spam;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Spam protection for public forms, without a third-party captcha.
 *
 * A captcha service would mean an external dependency, keys to rotate, a
 * request to someone else's server on every page, and a puzzle that is
 * measurably harder for disabled and low-bandwidth visitors, on a site whose
 * whole audience is in places where bandwidth is not free. So this uses the two
 * signals that actually separate a bot from a person, and asks the person to do
 * nothing at all:
 *
 *   1. A honeypot field a human never sees and never fills.
 *   2. An encrypted timestamp issued with the form. Anything submitted faster
 *      than a person could read the form, or long after the page went stale,
 *      is refused.
 *
 * The timestamp is encrypted rather than plain, so it cannot be forged or
 * replayed with an edited value, Crypt already carries the app key and a MAC.
 *
 * The honeypot's NAME is part of the mechanism rather than decoration. It was
 * `website`, which is one of the names Chrome, Safari and every password
 * manager fill from a saved address card, and autocomplete="off" is documented
 * as ignored for that kind of autofill. So a visitor with a password manager
 * had the trap filled in for them, invisibly, and then:
 *
 *   registering   silently produced no account, and told them to check an
 *                 inbox nothing had been sent to
 *   signing in    was answered as a wrong password, and after five attempts
 *                 rate-limited them out altogether
 *   resetting     promised a link that was never sent
 *
 * The name below therefore sits in no autofill taxonomy: no url, web, site,
 * mail, name, phone, address, company or user anywhere in it. The component
 * adds the opt-out attributes the major password managers honour on top of
 * that, and the assumption that "no person can fill in something they cannot
 * see" is retired: their software can, and did.
 */
class FormShield
{
    /**
     * A person cannot read a form and complete it faster than this.
     *
     * Two seconds rather than three: a browser that autofills name and email
     * leaves a person only the account type and the terms box, and an honest
     * submission at 2.4 seconds is perfectly possible. A script posts in well
     * under one.
     */
    private const MIN_SECONDS = 2;

    /** After this the page is stale; a fresh form (and CSRF token) is needed. */
    private const MAX_SECONDS = 7200;

    /**
     * Never rename this to anything a browser recognises as an address-book
     * field. See the class comment: `website` cost real people their accounts.
     */
    public const HONEYPOT = 'referral_note';

    public const TIMESTAMP = 'form_started_at';

    /** An encrypted "this form was issued now", to be embedded in the form. */
    public static function stamp(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * Whether a submission looks automated.
     *
     * Never throws for the honeypot: a bot that is told it failed simply tries
     * again with the field cleared, so the caller pretends the submission
     * succeeded instead.
     *
     * Every trip is logged. Silence is the right answer to a bot and the worst
     * possible answer to a false positive: the person sees no error, the owner
     * sees no report, and the only evidence the site turned somebody away is a
     * complaint weeks later. This log line is that evidence.
     */
    public static function looksAutomated(array $input, string $form = 'form'): bool
    {
        if (! filled($input[self::HONEYPOT] ?? null)) {
            return false;
        }

        Log::warning('Spam shield: honeypot filled, submission dropped.', [
            'form' => $form,
            'value' => Str::limit((string) $input[self::HONEYPOT], 100),
            'ip' => request()?->ip(),
            'agent' => Str::limit((string) request()?->userAgent(), 120),
            // The tell for a false positive: a bot's other fields are gibberish
            // or missing, a real person's read like a real person.
            'name' => Str::limit((string) ($input['name'] ?? ''), 60),
            'email' => Str::limit((string) ($input['email'] ?? ''), 60),
        ]);

        return true;
    }

    /**
     * Validate the timing signal. Unlike the honeypot this is surfaced to the
     * sender, because a real person can legitimately trip it by leaving a tab
     * open, and they need to be told to try again rather than silently ignored.
     *
     * @throws ValidationException
     */
    public static function assertHumanTiming(array $input): void
    {
        $raw = $input[self::TIMESTAMP] ?? null;

        if (! is_string($raw) || $raw === '') {
            self::fail('This form could not be verified. Please reload the page and try again.');
        }

        try {
            $issuedAt = (int) Crypt::decryptString($raw);
        } catch (\Throwable) {
            self::fail('This form could not be verified. Please reload the page and try again.');
        }

        $elapsed = now()->getTimestamp() - $issuedAt;

        if ($elapsed < self::MIN_SECONDS) {
            self::fail('That was submitted a little too quickly, please try once more.');
        }

        if ($elapsed > self::MAX_SECONDS) {
            self::fail('This page has been open a while. Please reload and send it again.');
        }
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages([self::TIMESTAMP => $message]);
    }
}
