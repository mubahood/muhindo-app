<?php

namespace Tests;

use App\Support\Spam\FormShield;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;

abstract class TestCase extends BaseTestCase
{
    /**
     * Add the spam shield's fields to a public form payload.
     *
     * Public forms carry an encrypted "issued at" stamp and refuse anything
     * submitted implausibly fast, so a test posting a bare array is rejected
     * exactly as a bot would be. This supplies a stamp old enough to read as
     * human — which is what a real submission carries.
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
        ];
    }
}
