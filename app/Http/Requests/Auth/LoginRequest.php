<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ] + \App\Support\Spam\Captcha::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return \App\Support\Spam\Captcha::messages();
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Honeypot only, deliberately NOT FormShield's timing check. A
        // password manager fills and submits this form in well under a second,
        // and refusing that would lock out exactly the people with the
        // strongest credentials.
        //
        // The hidden field was believed to carry no such risk, on the grounds
        // that nobody can fill in something they cannot see. Their software
        // can: the field was called `website`, which is what a password
        // manager fills from a saved address card, and those same people were
        // told their password was wrong and then rate-limited out. The field
        // is now named and marked so that no manager touches it.
        //
        // A trip is answered exactly as a wrong password is, so a script
        // learns nothing about why it failed.
        if (\App\Support\Spam\FormShield::looksAutomated($this->all(), 'login')) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        try {
            // Always issue the long-lived remember cookie: a signed-in user stays
            // signed in until they explicitly log out, even after the server-side
            // session itself expires (the recaller silently re-authenticates them).
            $authenticated = Auth::attempt($this->only('email', 'password'), true);
        } catch (\RuntimeException $e) {
            // Catches "This password does not use the Bcrypt algorithm" for legacy accounts
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'These credentials are not valid.',
            ]);
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
