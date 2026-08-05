<?php

namespace App\Http\Middleware;

use App\Services\Analytics\Tracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the page, after the page has already been sent.
 *
 * handle() does the little that must happen inside the request: resolving the
 * visitor, because the cookie has to be attached to this response. Everything
 * with a query in it happens in terminate(), which PHP-FPM runs after the
 * connection to the browser is closed, so several inserts cost the visitor
 * nothing.
 *
 * What is deliberately not recorded:
 *
 *   the back office     measuring yourself administering the site is how a
 *                       one-person business convinces itself it has traffic
 *   anything not GET    a form post is an event, and the redirect after it is
 *                       the page; counting both double-counts the moment
 *   Livewire            a component polling every 5s is not 720 page views
 *   assets and probes   never HTML, never a person
 *   crawlers            recorded as a visitor with is_bot, and excluded from
 *                       every report, so the traffic is auditable but never
 *                       flatters a number
 */
class TrackVisitor
{
    /**
     * Marks this request as one being recorded.
     *
     * It lives on the request rather than on the tracker because terminate()
     * must honour the decision handle() made about THIS request. Asking the
     * tracker "do you have a visit" instead reads state left over from an
     * earlier request in the same process, and a POST that was deliberately
     * skipped gets recorded as a page view on the strength of the GET before
     * it.
     */
    private const TRACKING = 'analytics.tracking';

    public function __construct(private readonly Tracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldTrack($request)) {
            $this->tracker->forget();

            return $next($request);
        }

        $request->attributes->set(self::TRACKING, true);
        $this->tracker->begin($request);

        $response = $next($request);

        if ($cookie = $this->tracker->cookie($request)) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->attributes->get(self::TRACKING) || ! $this->tracker->current()) {
            return;
        }

        // A redirect is the tail of the action before it, not a page anybody
        // read. 404s are kept: a broken inbound link is worth seeing.
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            return;
        }

        if (! $this->isHtml($response)) {
            return;
        }

        $this->tracker->pageView($request, $status, $this->tracker->elapsedMs());
    }

    private function shouldTrack(Request $request): bool
    {
        if (! config('analytics.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        // Livewire polls and updates its components over POST, but wire:navigate
        // fetches full pages over GET with this header. Those are real page
        // views to a visitor, so only the component endpoint itself is skipped.
        if ($request->hasHeader('X-Livewire') && $request->is('livewire/*')) {
            return false;
        }

        foreach ((array) config('analytics.ignore_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        if (config('analytics.ignore_admins', true) && $request->user()?->isAdmin()) {
            return false;
        }

        return true;
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', 'text/html');

        return $type === '' || str_contains($type, 'text/html');
    }
}
