<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\PageView;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use App\Support\Analytics\Agent;
use App\Support\Analytics\Channel;
use App\Support\Analytics\Events;
use Illuminate\Cookie\CookieJar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The write side of analytics. Everything that records anything goes through
 * here, so there is one definition of what a visitor is and one place where a
 * mistake in that definition can be fixed.
 *
 * Two rules hold throughout:
 *
 *   Nothing here may break a page. Every public method is wrapped so that a
 *   failed insert loses a statistic instead of a sale. Analytics is the least
 *   important thing this application does and must behave like it.
 *
 *   Nothing here runs before the response. The middleware calls in terminate(),
 *   after the bytes have gone to the browser, so the cost is real but invisible.
 */
class Tracker
{
    public const COOKIE = 'mm_v';

    /** Minutes of silence that end a visit. */
    public const VISIT_IDLE_MINUTES = 30;

    private const COOKIE_DAYS = 730;

    private ?Visitor $visitor = null;

    private ?Visit $visit = null;

    /**
     * When this request started.
     *
     * It lives on the tracker rather than on the middleware because Laravel
     * builds a second, fresh middleware instance to call terminate() on, and
     * anything held in a property of the first one is gone by then. The
     * tracker is a singleton, so it is the one thing that spans both halves.
     */
    private ?float $startedAt = null;

    public function __construct(private readonly CookieJar $cookies) {}

    /**
     * Resolve (or create) the visitor and the current visit.
     *
     * Called during the request, not in terminate, because issuing the cookie
     * has to happen while there is still a response to attach it to.
     */
    public function begin(Request $request): ?Visit
    {
        $this->startedAt = microtime(true);

        try {
            $agent = Agent::parse($request->userAgent());
            $token = $this->tokenFor($request);

            $visitor = Visitor::firstOrNew(['token' => $token]);
            $isNew = ! $visitor->exists;

            if ($isNew) {
                $acquisition = Channel::resolve($request);
                $visitor->fill([
                    'first_seen_at' => now(),
                    'first_landing_path' => Str::limit($request->path() === '/' ? '/' : '/'.ltrim($request->path(), '/'), 250, ''),
                    'first_referrer' => $acquisition['referrer'],
                    'first_source' => $acquisition['source'],
                    'first_medium' => $acquisition['medium'],
                    'first_campaign' => $acquisition['campaign'],
                ]);
            }

            $visitor->fill([
                'is_bot' => $agent->isBot,
                'last_seen_at' => now(),
                'last_device' => $agent->device,
                'last_browser' => $agent->browser,
                'last_os' => $agent->os,
                'last_ip' => $request->ip(),
                'last_country' => $this->country($request) ?? $visitor->last_country,
            ]);

            if (($user = $request->user()) && $visitor->user_id !== $user->id) {
                $this->identify($visitor, $user);
            }

            $visitor->save();
            $this->visitor = $visitor;
            $this->visit = $this->currentVisit($visitor, $request, $agent);

            return $this->visit;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Drop everything held about the current request.
     *
     * Called for requests that are not being recorded, so state from an
     * earlier request in the same process can never be mistaken for this one's.
     */
    public function forget(): void
    {
        $this->visitor = null;
        $this->visit = null;
        $this->startedAt = null;
    }

    /** Milliseconds spent producing the response, or null if begin() never ran. */
    public function elapsedMs(): ?int
    {
        return $this->startedAt === null ? null : (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    /** The cookie to send back, or null when the browser already has the right one. */
    public function cookie(Request $request): mixed
    {
        if (! $this->visitor) {
            return null;
        }

        if ($request->cookie(self::COOKIE) === $this->visitor->token) {
            return null;
        }

        return $this->cookies->make(
            self::COOKIE,
            $this->visitor->token,
            self::COOKIE_DAYS * 24 * 60,
            null, null, $request->secure(), true, false, 'lax'
        );
    }

    /** Record one page. Returns the row so the beacon can complete it later. */
    public function pageView(Request $request, int $status, ?int $responseMs = null): ?PageView
    {
        if (! $this->visit || ! $this->visitor) {
            return null;
        }

        try {
            $path = $this->path($request);
            [$subjectType, $subjectId] = $this->subjectOf($request);

            $view = PageView::create([
                'visit_id' => $this->visit->id,
                'visitor_id' => $this->visitor->id,
                'user_id' => $request->user()?->id,
                'path' => $path,
                'query' => Str::limit((string) $request->getQueryString(), 500, '') ?: null,
                'route_name' => Str::limit((string) $request->route()?->getName(), 90, ''),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'status' => $status,
                'response_ms' => $responseMs,
                'viewed_at' => now(),
            ]);

            // A visit stops being a bounce at its second page, not its second
            // request: assets and form posts never reach here.
            $this->visit->forceFill([
                'exit_path' => $path,
                'last_activity_at' => now(),
                'page_views_count' => $this->visit->page_views_count + 1,
                'is_bounce' => $this->visit->page_views_count + 1 <= 1,
            ])->save();

            $this->visitor->increment('page_views_count');

            return $view;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Record a named event.
     *
     * Callable from anywhere, including places with no request context at all
     * (a queued job, an artisan command), which is why the visitor is resolved
     * from the signed-in user when there is no cookie to read.
     */
    public function event(
        string $name,
        ?Model $subject = null,
        ?string $label = null,
        ?float $value = null,
        ?string $currency = null,
        array $meta = [],
        ?User $user = null,
        ?Visitor $visitor = null,
        ?int $visitId = null,
    ): ?AnalyticsEvent {
        try {
            $request = request();
            $user ??= $request?->user();
            // A caller that already knows whose event this is says so, and is
            // believed. The beacon is the case that needs it: it arrives on a
            // request of its own, and resolving the visitor from that request's
            // cookie would fail for anyone who has since blocked them.
            $visitor ??= $this->visitor ?? $this->visitorFor($request, $user);

            if (! $visitor) {
                return null;
            }

            $event = AnalyticsEvent::create([
                'visit_id' => $visitId ?? $this->visit?->id ?? $this->lastVisitId($visitor),
                'visitor_id' => $visitor->id,
                'user_id' => $user?->id,
                'name' => $name,
                'category' => Events::category($name),
                'label' => $label !== null ? Str::limit($label, 185) : null,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'path' => $request ? $this->path($request) : null,
                'value' => $value,
                'currency' => $currency,
                'meta' => $meta === [] ? null : $meta,
                'occurred_at' => now(),
            ]);

            $visitor->increment('events_count');
            if ($visitId === null) {
                $this->visit?->increment('events_count');
            } else {
                Visit::whereKey($visitId)->increment('events_count');
            }

            if (in_array($name, Events::CONVERSIONS, true)) {
                $visitor->forceFill([
                    'converted_at' => $visitor->converted_at ?? now(),
                    'revenue' => (float) $visitor->revenue + (float) ($value ?? 0),
                ])->save();
            }

            return $event;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Attach every visit this browser ever made to the person who just signed
     * in. Without this, the history of somebody who read for a fortnight and
     * then registered begins on the day they registered.
     */
    public function identify(Visitor $visitor, User $user): void
    {
        $visitor->forceFill([
            'user_id' => $user->id,
            'identified_at' => $visitor->identified_at ?? now(),
        ]);

        DB::transaction(function () use ($visitor, $user) {
            $visitor->save();
            Visit::where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);
            PageView::where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);
            AnalyticsEvent::where('visitor_id', $visitor->id)->whereNull('user_id')->update(['user_id' => $user->id]);
        });
    }

    public function currentVisitor(): ?Visitor
    {
        return $this->visitor;
    }

    /**
     * The visitor behind the current request, whether or not it is one being
     * recorded.
     *
     * Signing in and registering are both POSTs, and a POST is never recorded
     * as a page view, so the tracker holds nothing by the time the auth event
     * fires. Reading the cookie directly is what lets the account still claim
     * the browsing that led to it, which is the entire point of identifying.
     */
    public function resolveVisitor(?User $user = null): ?Visitor
    {
        return $this->visitor ?? $this->visitorFor(request(), $user);
    }

    public function current(): ?Visit
    {
        return $this->visit;
    }

    /**
     * The handle the browser quotes when it reports back.
     *
     * It names the visit and the path rather than the page view, because the
     * page view does not exist yet: it is written in terminate(), after this
     * HTML has already gone. Encrypted, so it cannot be forged or enumerated,
     * and the beacon resolves it to the most recent matching page.
     */
    public function beaconToken(Request $request): ?string
    {
        if (! $this->visit) {
            return null;
        }

        try {
            return Crypt::encryptString($this->visit->id.'|'.$this->path($request));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Resolve a beacon token back to the page it was issued on. */
    public function pageViewForToken(string $token): ?PageView
    {
        try {
            [$visitId, $path] = array_pad(explode('|', Crypt::decryptString($token), 2), 2, null);
        } catch (Throwable) {
            return null;
        }

        if (! ctype_digit((string) $visitId) || $path === null) {
            return null;
        }

        return PageView::where('visit_id', (int) $visitId)
            ->where('path', $path)
            ->where('viewed_at', '>=', now()->subHours(6))
            ->latest('viewed_at')
            ->first();
    }

    /** Fold the beacon's measurements into a page already recorded. */
    public function completePageView(PageView $view, ?int $seconds, ?int $scroll): void
    {
        try {
            $max = (int) config('analytics.beacon.max_seconds', 3600);
            $seconds = $seconds === null ? null : max(0, min($seconds, $max));
            $scroll = $scroll === null ? null : max(0, min($scroll, 100));

            // Read before the write. save() re-syncs the model's original
            // attributes, so asking afterwards what the value used to be
            // returns what it has just become, and the rollup below adds zero.
            $before = (int) $view->engaged_seconds;

            // Beacons can arrive twice (pagehide, then visibilitychange). Keep
            // the larger reading rather than the later one.
            $view->forceFill([
                'engaged_seconds' => max($before, (int) $seconds) ?: null,
                'scroll_percent' => max((int) $view->scroll_percent, (int) $scroll) ?: null,
            ])->save();

            $delta = max(0, (int) $seconds - $before);
            if ($delta > 0) {
                Visit::whereKey($view->visit_id)->increment('engaged_seconds', $delta);
                Visitor::whereKey($view->visitor_id)->increment('engaged_seconds', $delta);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function currentVisit(Visitor $visitor, Request $request, Agent $agent): Visit
    {
        $open = Visit::where('visitor_id', $visitor->id)
            ->where('last_activity_at', '>=', now()->subMinutes(self::VISIT_IDLE_MINUTES))
            ->latest('last_activity_at')
            ->first();

        if ($open) {
            $open->forceFill(['last_activity_at' => now()]);
            if ($open->user_id === null && $request->user()) {
                $open->user_id = $request->user()->id;
            }
            $open->save();

            return $open;
        }

        $acquisition = Channel::resolve($request);

        $visit = Visit::create([
            'visitor_id' => $visitor->id,
            'user_id' => $request->user()?->id,
            'entry_path' => $this->path($request),
            'exit_path' => $this->path($request),
            'referrer' => $acquisition['referrer'],
            'referrer_host' => $acquisition['referrer_host'],
            'channel' => $acquisition['channel'],
            'source' => $acquisition['source'],
            'medium' => $acquisition['medium'],
            'campaign' => $acquisition['campaign'],
            'device' => $agent->device,
            'browser' => $agent->browser,
            'os' => $agent->os,
            'ip' => $request->ip(),
            'country' => $this->country($request),
            'language' => Str::limit((string) $request->getPreferredLanguage(), 8, ''),
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $visitor->increment('visits_count');

        return $visit;
    }

    /**
     * A page's subject: the course, product, project or post it is about.
     *
     * Read off the resolved route's bound models rather than parsed out of the
     * path, so it keeps working when a URL changes and needs nothing per route.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function subjectOf(Request $request): array
    {
        $interesting = [
            \App\Models\Course::class, \App\Models\Product::class,
            \App\Models\PortfolioProject::class, \App\Models\Post::class,
            \App\Models\Lesson::class, \App\Models\GalleryPhoto::class,
        ];

        foreach ((array) $request->route()?->parameters() as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }
            foreach ($interesting as $class) {
                if ($parameter instanceof $class) {
                    return [$parameter->getMorphClass(), (int) $parameter->getKey()];
                }
            }
        }

        return [null, null];
    }

    private function tokenFor(Request $request): string
    {
        $cookie = (string) $request->cookie(self::COOKIE);

        return Str::isUuid($cookie) ? $cookie : (string) Str::uuid();
    }

    /**
     * Country from whatever the edge put in front of us. Cloudflare and most
     * cPanel stacks set one of these; where nothing does, the column stays
     * null and `analytics:geolocate` can fill it in later from the IP.
     */
    private function country(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Geo-Country', 'GEOIP_COUNTRY_CODE', 'X-Country-Code'] as $header) {
            $value = $request->headers->get($header);
            if (is_string($value) && preg_match('/^[A-Za-z]{2}$/', $value) && strtoupper($value) !== 'XX') {
                return strtoupper($value);
            }
        }

        return null;
    }

    private function path(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');

        return Str::limit($path === '//' ? '/' : $path, 250, '');
    }

    private function visitorFor(?Request $request, ?User $user): ?Visitor
    {
        if ($request) {
            $token = (string) $request->cookie(self::COOKIE);
            if (Str::isUuid($token) && $found = Visitor::where('token', $token)->first()) {
                return $found;
            }
        }

        // No cookie, but a known person: their most recent browser is the best
        // available answer, and keeps API and console activity attributable.
        return $user ? Visitor::where('user_id', $user->id)->latest('last_seen_at')->first() : null;
    }

    private function lastVisitId(Visitor $visitor): ?int
    {
        return Visit::where('visitor_id', $visitor->id)->latest('last_activity_at')->value('id');
    }
}
