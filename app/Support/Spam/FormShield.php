<?php

namespace App\Support\Spam;

use Illuminate\Support\Facades\Crypt;
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
 */
class FormShield
{
    /** A person cannot read a form and complete it faster than this. */
    private const MIN_SECONDS = 3;

    /** After this the page is stale; a fresh form (and CSRF token) is needed. */
    private const MAX_SECONDS = 7200;

    public const HONEYPOT = 'website';

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
     */
    public static function looksAutomated(array $input): bool
    {
        return filled($input[self::HONEYPOT] ?? null);
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
