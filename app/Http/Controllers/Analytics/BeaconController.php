<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Services\Analytics\Tracker;
use App\Support\Analytics\Events;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Where the browser reports what it alone can see: how long a page was
 * actually being read, how far down it got, and what was pressed.
 *
 * The endpoint is unauthenticated because most of the audience is, so the
 * question is what stops it being a way to write whatever you like into the
 * reporting tables.
 *
 *   The page view is named by an encrypted id, not an integer. A caller
 *   cannot enumerate rows or invent one; they can only complete a page the
 *   server itself handed them minutes earlier.
 *
 *   Event names are checked against a short allow-list. The browser may report
 *   a click; it may not report a payment.
 *
 *   Every field is clamped server-side. A tab claiming nine hours of rapt
 *   attention gets an hour, and 150% scrolled gets 100.
 *
 * It answers 204 to everything, including refusals. sendBeacon discards the
 * response, and an error body would only tell a prober what it got wrong.
 */
class BeaconController extends Controller
{
    public function __invoke(Request $request, Tracker $tracker): Response
    {
        $blank = response()->noContent();

        if (! config('analytics.enabled', true) || ! config('analytics.beacon.enabled', true)) {
            return $blank;
        }

        // sendBeacon posts a Blob with no content type of ours, so the payload
        // is read as raw JSON rather than through form input.
        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return $blank;
        }

        $token = $payload['v'] ?? null;
        $view = is_string($token) && $token !== '' ? $tracker->pageViewForToken($token) : null;
        if (! $view) {
            return $blank;
        }

        // One page cannot report more than a handful of times, however many
        // tabs or retries are involved.
        $key = 'beacon:'.$view->id;
        if ((int) Cache::get($key, 0) > 20) {
            return $blank;
        }
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addHour());

        $tracker->completePageView(
            $view,
            isset($payload['s']) ? (int) $payload['s'] : null,
            isset($payload['d']) ? (int) $payload['d'] : null,
        );

        foreach (array_slice((array) ($payload['e'] ?? []), 0, 10) as $event) {
            $this->recordEvent($tracker, $view, is_array($event) ? $event : []);
        }

        return $blank;
    }

    private function recordEvent(Tracker $tracker, PageView $view, array $event): void
    {
        $name = is_string($event['n'] ?? null) ? $event['n'] : '';

        if (! in_array($name, Events::CLIENT_REPORTABLE, true)) {
            return;
        }

        $label = is_string($event['l'] ?? null) ? Str::limit($event['l'], 185) : null;

        $tracker->event(
            name: $name,
            label: $label,
            meta: ['page_view_id' => $view->id, 'reported_by' => 'browser'],
            visitor: $view->visitor,
            visitId: $view->visit_id,
        );
    }
}
