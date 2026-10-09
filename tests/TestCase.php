<?php

namespace Tests;

use App\Support\Spam\FormShield;
use App\Support\Spam\ImageCaptcha;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;

abstract class TestCase extends BaseTestCase
{
    /**
     * Add everything a public form expects of a human to a payload.
     *
     * Public forms carry an encrypted "issued at" stamp and refuse anything
     * submitted implausibly fast, and now also an image challenge, so a test
     * posting a bare array is rejected exactly as a bot would be. This supplies
     * a stamp old enough to read as human and the answer to the challenge,
     * which is what a real submission carries.
     *
     * The captcha answer is generated and read back from the session here
     * rather than in each test, so that adding a layer to the anti-bot stack
     * does not mean editing every test that posts a form. A test that wants to
     * prove the challenge actually blocks overrides the field with a wrong
     * answer, which is what PublicEnquiryTest does.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    protected function shielded(array $payload = [], int $secondsAgo = 20): array
    {
        return $payload + [
            FormShield::TIMESTAMP => Crypt::encryptString(
                (string) now()->subSeconds($secondsAgo)->getTimestamp()
            ),
            ImageCaptcha::FIELD => ImageCaptcha::regenerate(),
        ];
    }
}
