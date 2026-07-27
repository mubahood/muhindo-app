<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment gateway adapter — swap the binding to change providers; the
        // app only ever depends on the PaymentGateway interface.
        $this->app->bind(
            \App\Services\Gateway\PaymentGateway::class,
            \App\Services\Gateway\FlutterwaveGateway::class,
        );

        // Telescope is a dev-only dependency — register its providers only when
        // the package is actually installed (local), so production --no-dev
        // installs work without it.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // Cap indexed string columns at 191 chars so utf8mb4 indexes stay within
        // the 1000-byte key limit on older MySQL/MariaDB builds.
        Schema::defaultStringLength(191);

        // §9 — every timestamp is stored/computed in UTC (config('app.timezone'));
        // student/instructor-facing due dates and time windows render in the
        // learners' actual timezone instead. Deliberately a Carbon macro (not a
        // model accessor) so it applies uniformly to any datetime that needs it —
        // currently Assignment::due_at and Quiz::available_from/available_until.
        \Illuminate\Support\Carbon::macro('toLocal', function () {
            /** @var \Illuminate\Support\Carbon $this */
            return $this->copy()->timezone('Africa/Kampala');
        });

        // API JSON Resources don't add their own {"data": …} wrapper — the single
        // envelope comes from App\Support\ApiResponse, so resources stay flat.
        \Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();

        // Password policy enforced. Applies
        // everywhere Password::defaults() is used (reset, forced change, API).
        Password::defaults(fn () => Password::min(8));

        // Use our themed pagination view for every ->links() call,
        // instead of the default unstyled Tailwind markup.
        Paginator::defaultView('pagination::simple-default');
        Paginator::defaultSimpleView('pagination::simple-default');

        // The 'api' rate limiter — every /api/* route via $middleware->throttleApi()
        // in bootstrap/app.php. Per authenticated user when
        // signed in (Sanctum), else per IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Force the root URL so url() / route() helpers generate correct
        // paths when the app is served from a subdirectory (e.g. /onyx/).
        if ($root = config('app.url')) {
            URL::forceRootUrl($root);

            // Also force HTTPS scheme when APP_URL starts with https
            if (str_starts_with($root, 'https://')) {
                URL::forceScheme('https');
            }
        }

        $this->fixLivewireSubdirectoryUrls();

        // PUBLIC_SITE_PLAN.md §6.6 — livewire.js is ~380KB and was measured as the single
        // largest render-blocking resource on the public marketing pages (Lighthouse:
        // ~3.6s of estimated savings). Those pages don't mount real Livewire components —
        // it's only loaded there to power wire:navigate's link interception, which is a
        // progressive enhancement over real <a href> navigation and works fine once the
        // script finishes loading a beat later. `defer` costs nothing on pages that DO use
        // live components either, since Livewire itself waits for DOMContentLoaded.
        \Livewire\Livewire::useScriptTagAttributes(['defer' => true]);
    }

    /**
     * When the app is served from a subdirectory (APP_URL has a path, e.g.
     * /muhindo-app), Livewire 3 emits BOTH its <script src> and its
     * data-update-uri root-relative (/livewire/livewire.js, /livewire/update).
     * Under the subdirectory those 404 at the web server before Laravel ever
     * sees them — the script 404 blanks the page (x-cloak never lifts), and the
     * update 404 makes every wire:click/model fail with Livewire's error
     * overlay showing the server's "Not Found" page.
     *
     * This deployment's request base path is empty (the front controller does
     * not put /muhindo-app in SCRIPT_NAME), so URL generation does not restore
     * the prefix on its own. Fix both endpoints explicitly:
     *   - script asset  → livewire.asset_url config
     *   - update route  → registered under the base prefix, so the emitted
     *     data-update-uri carries it. The default (unprefixed) route stays
     *     registered too, so the POST matches whether or not the base is
     *     stripped before routing.
     */
    private function fixLivewireSubdirectoryUrls(): void
    {
        $base = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($base === '') {
            return; // Served from the domain root — Livewire's defaults are correct.
        }

        config(['livewire.asset_url' => $base.'/livewire/livewire.js']);

        \Livewire\Livewire::setUpdateRoute(
            fn ($handle) => \Illuminate\Support\Facades\Route::post($base.'/livewire/update', $handle)
        );
    }
}
