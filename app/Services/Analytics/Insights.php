<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsEvent;
use App\Models\PageView;
use App\Models\Visit;
use App\Models\Visitor;
use App\Support\Analytics\Channel;
use App\Support\Analytics\Events;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The read side. Every number on every analytics screen is defined once, here.
 *
 * Bots are excluded everywhere, without exception. They are recorded so that
 * the traffic can be audited, and they are never counted, because a crawler
 * that reads all 425 lessons in ninety seconds otherwise becomes the most
 * engaged reader the site has ever had.
 */
class Insights
{
    public function __construct(private readonly CarbonImmutable $from, private readonly CarbonImmutable $to) {}

    public static function forDays(int $days): self
    {
        return new self(
            CarbonImmutable::now()->subDays(max(0, $days - 1))->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->startOfDay(), $to->endOfDay());
    }

    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    /** The same window, ending where this one begins, for period-on-period change. */
    public function previous(): self
    {
        $length = $this->from->diffInSeconds($this->to);

        return new self($this->from->subSeconds($length + 1), $this->from->subSecond());
    }

    /* Headline numbers ---------------------------------------------------- */

    /** @return array{visitors:int, visits:int, page_views:int, new_visitors:int, bounce_rate:float, avg_seconds:int, engaged_seconds:int} */
    public function totals(): array
    {
        $visits = $this->visits()
            ->selectRaw('COUNT(*) AS visits')
            ->selectRaw('COUNT(DISTINCT visitor_id) AS visitors')
            ->selectRaw('SUM(page_views_count) AS page_views')
            ->selectRaw('SUM(engaged_seconds) AS engaged_seconds')
            ->selectRaw('SUM(is_bounce) AS bounces')
            ->first();

        $count = (int) ($visits->visits ?? 0);

        return [
            'visitors' => (int) ($visits->visitors ?? 0),
            'visits' => $count,
            'page_views' => (int) ($visits->page_views ?? 0),
            'new_visitors' => $this->newVisitors(),
            'bounce_rate' => $count > 0 ? round((int) $visits->bounces / $count * 100, 1) : 0.0,
            'engaged_seconds' => (int) ($visits->engaged_seconds ?? 0),
            'avg_seconds' => $count > 0 ? (int) round((int) $visits->engaged_seconds / $count) : 0,
        ];
    }

    public function newVisitors(): int
    {
        return Visitor::human()->whereBetween('first_seen_at', [$this->from, $this->to])->count();
    }

    /** Visitors on the site in the last few minutes, with what they are reading. */
    public function liveVisitors(?int $minutes = null): Collection
    {
        $minutes ??= (int) config('analytics.online_window_minutes', 5);

        return Visitor::human()
            ->with(['user:id,name,email,role'])
            ->where('last_seen_at', '>=', now()->subMinutes($minutes))
            ->latest('last_seen_at')
            ->limit(60)
            ->get()
            ->map(function (Visitor $visitor) {
                $view = PageView::where('visitor_id', $visitor->id)->latest('viewed_at')->first();
                $visit = Visit::where('visitor_id', $visitor->id)->latest('last_activity_at')->first();

                return [
                    'visitor' => $visitor,
                    'path' => $view?->path ?? $visit?->exit_path ?? '/',
                    'title' => $view?->title,
                    'since' => $visit?->started_at,
                    'pages' => (int) ($visit?->page_views_count ?? 0),
                    'channel' => $visit?->channel,
                    'source' => $visit?->source,
                    'country' => $visit?->country ?? $visitor->last_country,
                    'device' => $visitor->last_device,
                    'seen' => $visitor->last_seen_at,
                ];
            });
    }

    /* Trends -------------------------------------------------------------- */

    /**
     * A value per day across the window, zero-filled.
     *
     * The zero-fill matters more than it sounds: a GROUP BY skips days with no
     * traffic entirely, and a chart drawn from that silently closes the gap,
     * turning a quiet week into a straight line between two busy ones.
     *
     * @return array<string, int>
     */
    public function dailySeries(string $metric = 'visitors'): array
    {
        if ($rolled = $this->rolledSeries($metric)) {
            return $rolled;
        }

        $rows = match ($metric) {
            'page_views' => $this->pageViews()
                ->selectRaw('DATE(viewed_at) AS d, COUNT(*) AS n')->groupBy('d')->pluck('n', 'd'),
            'visits' => $this->visits()
                ->selectRaw('DATE(started_at) AS d, COUNT(*) AS n')->groupBy('d')->pluck('n', 'd'),
            'revenue' => $this->events()->where('name', Events::ORDER_PAID)
                ->selectRaw('DATE(occurred_at) AS d, SUM(value) AS n')->groupBy('d')->pluck('n', 'd'),
            'conversions' => $this->events()->whereIn('name', Events::CONVERSIONS)
                ->selectRaw('DATE(occurred_at) AS d, COUNT(*) AS n')->groupBy('d')->pluck('n', 'd'),
            default => $this->visits()
                ->selectRaw('DATE(started_at) AS d, COUNT(DISTINCT visitor_id) AS n')->groupBy('d')->pluck('n', 'd'),
        };

        $series = [];
        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $series[$key] = (int) ($rows[$key] ?? 0);
        }

        return $series;
    }

    /**
     * The same series, read from the daily rollup instead of the raw tables.
     *
     * Only for long windows, and only when every day in the window has a
     * rollup row. A twelve-month chart is 365 days of GROUP BY over every page
     * view ever recorded, on a page somebody opens to glance at a line; the
     * same answer is already sitting in one row per day.
     *
     * All-or-nothing on purpose. Half the window from a rollup and half
     * computed live is how two charts of the same thing come to disagree, and
     * falling back costs one slow query rather than a wrong picture.
     *
     * @return array<string, int>|null
     */
    private function rolledSeries(string $metric): ?array
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;

        if ($days < 60) {
            return null;
        }

        $column = match ($metric) {
            'visitors' => 'visitors',
            'page_views' => 'page_views',
            'visits' => 'visits',
            'revenue' => 'revenue',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $rows = AnalyticsDaily::whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->pluck($column, 'date')
            ->mapWithKeys(fn ($value, $date) => [\Illuminate\Support\Str::substr((string) $date, 0, 10) => $value]);

        // Today is still being written, so it is never final in the rollup.
        $expected = $this->to->isToday() ? $days - 1 : $days;

        if ($rows->count() < $expected) {
            return null;
        }

        $series = [];
        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $series[$key] = $day->isToday() && ! isset($rows[$key])
                ? $this->todayFor($metric)
                : (int) ($rows[$key] ?? 0);
        }

        return $series;
    }

    /** The one day the rollup cannot be trusted for, computed live. */
    private function todayFor(string $metric): int
    {
        $today = self::between(CarbonImmutable::today(), CarbonImmutable::today());

        return match ($metric) {
            'page_views' => (int) $today->pageViews()->count(),
            'visits' => (int) $today->visits()->count(),
            'revenue' => (int) $today->revenue(),
            default => (int) $today->visits()->distinct()->count('visitor_id'),
        };
    }

    /** Traffic by hour of the day, which is when to publish and when to be online. */
    public function byHour(): array
    {
        $rows = $this->pageViews()
            ->selectRaw(self::datePart('hour', 'viewed_at').' AS h, COUNT(*) AS n')->groupBy('h')->pluck('n', 'h');

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = (int) ($rows[$h] ?? 0);
        }

        return $hours;
    }

    /* Where they come from ------------------------------------------------ */

    /** @return array<string, int> */
    public function byChannel(): array
    {
        return $this->visits()
            ->selectRaw('channel, COUNT(*) AS n')->groupBy('channel')->orderByDesc('n')
            ->pluck('n', 'channel')
            ->mapWithKeys(fn ($n, $channel) => [Channel::label($channel) => (int) $n])
            ->all();
    }

    public function topSources(int $limit = 10): Collection
    {
        return $this->visits()
            ->whereNotNull('source')
            ->selectRaw('source, channel, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS visitors')
            ->groupBy('source', 'channel')
            ->orderByDesc('visits')
            ->limit($limit)
            ->get();
    }

    public function topReferrers(int $limit = 10): Collection
    {
        return $this->visits()
            ->whereNotNull('referrer_host')
            ->where('channel', '!=', Channel::INTERNAL)
            ->selectRaw('referrer_host, COUNT(*) AS visits')
            ->groupBy('referrer_host')->orderByDesc('visits')->limit($limit)
            ->get();
    }

    public function campaigns(int $limit = 10): Collection
    {
        return $this->visits()
            ->whereNotNull('campaign')
            ->selectRaw('campaign, source, medium, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS visitors')
            ->groupBy('campaign', 'source', 'medium')
            ->orderByDesc('visits')->limit($limit)
            ->get();
    }

    /* Who they are -------------------------------------------------------- */

    /** @return array<string, int> */
    public function byDevice(): array
    {
        return $this->visits()
            ->whereNotNull('device')
            ->selectRaw('device, COUNT(*) AS n')->groupBy('device')->orderByDesc('n')
            ->pluck('n', 'device')
            ->mapWithKeys(fn ($n, $device) => [ucfirst((string) $device) => (int) $n])
            ->all();
    }

    /** @return array<string, int> */
    public function byBrowser(int $limit = 8): array
    {
        return $this->visits()
            ->whereNotNull('browser')
            ->selectRaw('browser, COUNT(*) AS n')->groupBy('browser')->orderByDesc('n')->limit($limit)
            ->pluck('n', 'browser')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<string, int> */
    public function byOs(int $limit = 8): array
    {
        return $this->visits()
            ->whereNotNull('os')
            ->selectRaw('os, COUNT(*) AS n')->groupBy('os')->orderByDesc('n')->limit($limit)
            ->pluck('n', 'os')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<string, int> */
    public function byCountry(int $limit = 12): array
    {
        return $this->visits()
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(DISTINCT visitor_id) AS n')->groupBy('country')->orderByDesc('n')->limit($limit)
            ->pluck('n', 'country')->map(fn ($n) => (int) $n)->all();
    }

    /** New against returning, which is the difference between reach and a following. */
    public function loyalty(): array
    {
        $new = $this->newVisitors();
        $total = (int) $this->visits()->distinct()->count('visitor_id');

        return ['New' => $new, 'Returning' => max(0, $total - $new)];
    }

    /* What they read ------------------------------------------------------ */

    /**
     * Top pages, with the two columns that separate a page people open from a
     * page people read: median-ish dwell time and how far down they scrolled.
     */
    public function topPages(int $limit = 15): Collection
    {
        return $this->pageViews()
            ->selectRaw('path, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors')
            ->selectRaw('AVG(engaged_seconds) AS avg_seconds, AVG(scroll_percent) AS avg_scroll')
            ->selectRaw('MAX(title) AS title')
            ->groupBy('path')->orderByDesc('views')->limit($limit)
            ->get();
    }

    /** The pages people arrive on. What the site looks like to a stranger. */
    public function landingPages(int $limit = 10): Collection
    {
        return $this->visits()
            ->whereNotNull('entry_path')
            ->selectRaw('entry_path AS path, COUNT(*) AS visits, SUM(is_bounce) AS bounces')
            ->groupBy('entry_path')->orderByDesc('visits')->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->bounce_rate = $row->visits > 0 ? round($row->bounces / $row->visits * 100, 1) : 0;

                return $row;
            });
    }

    /** The last page of a visit. Where people give up. */
    public function exitPages(int $limit = 10): Collection
    {
        return $this->visits()
            ->whereNotNull('exit_path')
            ->selectRaw('exit_path AS path, COUNT(*) AS exits')
            ->groupBy('exit_path')->orderByDesc('exits')->limit($limit)
            ->get();
    }

    /**
     * Content ranked by what it is about rather than by URL: a course, a
     * product, a project. This is the question a catalogue owner actually has,
     * and it survives a page being renamed or paginated.
     */
    public function topSubjects(?string $type = null, int $limit = 12): Collection
    {
        return $this->pageViews()
            ->whereNotNull('subject_type')
            ->when($type, fn ($q) => $q->where('subject_type', $type))
            ->selectRaw('subject_type, subject_id, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors')
            ->selectRaw('AVG(engaged_seconds) AS avg_seconds, AVG(scroll_percent) AS avg_scroll')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('views')->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->subject = rescue(
                    fn () => app($row->subject_type)::find($row->subject_id),
                    null,
                    false,
                );

                return $row;
            });
    }

    /** Pages that 404ed, with how many people hit them. Broken inbound links. */
    public function brokenPages(int $limit = 10): Collection
    {
        return $this->pageViews()
            ->where('status', '>=', 400)
            ->selectRaw('path, status, COUNT(*) AS hits')
            ->groupBy('path', 'status')->orderByDesc('hits')->limit($limit)
            ->get();
    }

    /* What they do -------------------------------------------------------- */

    /** @return array<string, int> */
    public function eventCounts(?string $category = null): array
    {
        return $this->events()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->selectRaw('name, COUNT(*) AS n')->groupBy('name')->orderByDesc('n')
            ->pluck('n', 'name')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * The public funnel. Each step counts distinct visitors who reached it, so
     * one person adding six things to a basket is one person, not six.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $counts = $this->events()
            ->selectRaw('name, COUNT(DISTINCT visitor_id) AS n')->groupBy('name')
            ->pluck('n', 'name');

        $funnel = [];
        foreach (Events::FUNNEL as $step => $label) {
            $funnel[$label] = $step === 'visit'
                ? (int) $this->visits()->distinct()->count('visitor_id')
                : (int) ($counts[$step] ?? 0);
        }

        return $funnel;
    }

    public function conversions(): int
    {
        return (int) $this->events()->whereIn('name', Events::CONVERSIONS)->count();
    }

    public function conversionRate(): float
    {
        $visitors = (int) $this->visits()->distinct()->count('visitor_id');
        if ($visitors === 0) {
            return 0.0;
        }

        $converted = (int) $this->events()
            ->whereIn('name', Events::CONVERSIONS)
            ->distinct()->count('visitor_id');

        return round($converted / $visitors * 100, 1);
    }

    public function revenue(): float
    {
        return (float) $this->events()->where('name', Events::ORDER_PAID)->sum('value');
    }

    /**
     * Revenue credited to the channel that first brought the visitor, not the
     * one they happened to arrive by on the day they paid. First touch is the
     * honest answer for a site where the gap between discovering somebody and
     * hiring them is measured in weeks.
     */
    public function revenueByFirstTouch(int $limit = 8): Collection
    {
        return AnalyticsEvent::query()
            ->join('visitors', 'visitors.id', '=', 'analytics_events.visitor_id')
            ->where('visitors.is_bot', false)
            ->where('analytics_events.name', Events::ORDER_PAID)
            ->whereBetween('analytics_events.occurred_at', [$this->from, $this->to])
            ->selectRaw("COALESCE(visitors.first_source, 'Direct') AS source")
            ->selectRaw('SUM(analytics_events.value) AS revenue, COUNT(*) AS payments')
            ->groupBy('source')->orderByDesc('revenue')->limit($limit)
            ->get();
    }

    /** The most recent things that happened, for the "what is going on" feed. */
    public function recentEvents(int $limit = 25): Collection
    {
        return AnalyticsEvent::query()
            ->join('visitors', 'visitors.id', '=', 'analytics_events.visitor_id')
            ->where('visitors.is_bot', false)
            ->whereBetween('analytics_events.occurred_at', [$this->from, $this->to])
            ->whereIn('analytics_events.category', [Events::CATEGORY_CONVERSION, Events::CATEGORY_INTENT, Events::CATEGORY_LEARNING])
            ->select('analytics_events.*')
            ->with(['visitor.user:id,name', 'user:id,name'])
            ->latest('analytics_events.occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * A date part, in the dialect of whatever database is answering.
     *
     * HOUR() and MINUTE() are MySQL's spelling and exist nowhere else, so a
     * report written with them works in production and fails on the SQLite
     * the test suite runs against, which is precisely backwards: the check
     * that would have caught the mistake is the one that cannot run.
     */
    public static function datePart(string $part, string $column): string
    {
        $format = ['hour' => '%H', 'minute' => '%M', 'day' => '%d'][$part] ?? '%H';

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('{$format}', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT({$part} FROM {$column})",
            default => strtoupper($part)."({$column})",
        };
    }

    /* Scoped base queries ------------------------------------------------- */

    /** @return \Illuminate\Database\Eloquent\Builder<Visit> */
    public function visits()
    {
        return Visit::query()
            ->whereBetween('visits.started_at', [$this->from, $this->to])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('visitors')
                ->whereColumn('visitors.id', 'visits.visitor_id')->where('visitors.is_bot', false));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<PageView> */
    public function pageViews()
    {
        return PageView::query()
            ->whereBetween('page_views.viewed_at', [$this->from, $this->to])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('visitors')
                ->whereColumn('visitors.id', 'page_views.visitor_id')->where('visitors.is_bot', false));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AnalyticsEvent> */
    public function events()
    {
        return AnalyticsEvent::query()
            ->whereBetween('analytics_events.occurred_at', [$this->from, $this->to])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('visitors')
                ->whereColumn('visitors.id', 'analytics_events.visitor_id')->where('visitors.is_bot', false));
    }

    /** Bot traffic, reported separately so it is visible without ever counting. */
    public function botVisits(): int
    {
        return (int) Visit::query()
            ->whereBetween('visits.started_at', [$this->from, $this->to])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('visitors')
                ->whereColumn('visitors.id', 'visits.visitor_id')->where('visitors.is_bot', true))
            ->count();
    }
}
