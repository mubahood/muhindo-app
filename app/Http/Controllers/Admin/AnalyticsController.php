<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Services\Analytics\Insights;
use App\Support\Analytics\Events;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The 360: audience, acquisition, content and outcome on one screen, with the
 * detail screens hanging off it.
 *
 * Every screen takes the same period from the query string and hands it to one
 * Insights instance, so two numbers on the same page can never quietly be
 * measuring two different fortnights.
 */
class AnalyticsController extends Controller
{
    /** Ranges offered in the period switch, in days. */
    private const PERIODS = [
        1 => 'Today',
        7 => '7 days',
        30 => '30 days',
        90 => '90 days',
        365 => '12 months',
    ];

    public function index(Request $request): View
    {
        $insights = $this->period($request);
        $previous = $insights->previous();

        $totals = $insights->totals();
        $before = $previous->totals();

        return view('admin.analytics.index', [
            'periods' => self::PERIODS,
            'days' => $this->days($request),
            'insights' => $insights,
            'totals' => $totals,
            'change' => [
                'visitors' => $this->change($totals['visitors'], $before['visitors']),
                'visits' => $this->change($totals['visits'], $before['visits']),
                'page_views' => $this->change($totals['page_views'], $before['page_views']),
                'revenue' => $this->change($insights->revenue(), $previous->revenue()),
            ],
            'series' => $insights->dailySeries('visitors'),
            'pageSeries' => $insights->dailySeries('page_views'),
            'revenueSeries' => $insights->dailySeries('revenue'),
            'byHour' => $insights->byHour(),
            'channels' => $insights->byChannel(),
            'sources' => $insights->topSources(8),
            'referrers' => $insights->topReferrers(8),
            'devices' => $insights->byDevice(),
            'browsers' => $insights->byBrowser(6),
            'countries' => $insights->byCountry(10),
            'loyalty' => $insights->loyalty(),
            'topPages' => $insights->topPages(12),
            'landing' => $insights->landingPages(8),
            'exits' => $insights->exitPages(8),
            'broken' => $insights->brokenPages(6),
            'funnel' => $insights->funnel(),
            'conversionRate' => $insights->conversionRate(),
            'conversions' => $insights->conversions(),
            'revenue' => $insights->revenue(),
            'revenueByTouch' => $insights->revenueByFirstTouch(6),
            'eventCounts' => $insights->eventCounts(),
            'recent' => $insights->recentEvents(18),
            'live' => $insights->liveVisitors()->count(),
            'bots' => $insights->botVisits(),
        ]);
    }

    /**
     * Content performance, ranked by what a page is about rather than by URL.
     *
     * A course sold on three URLs (catalogue card, sales page, checkout) is one
     * course here, and the columns that matter are the ones a hit count cannot
     * give you: how long people stayed, how far they read, and whether any of
     * it turned into an enrolment.
     */
    public function content(Request $request): View
    {
        $insights = $this->period($request);

        $types = [
            Course::class => 'Courses',
            Product::class => 'Source code',
            PortfolioProject::class => 'Work',
            Post::class => 'Writing',
        ];

        $sets = [];
        foreach ($types as $class => $label) {
            $rows = $insights->topSubjects($class, 25);
            if ($rows->isNotEmpty()) {
                $sets[$label] = $rows;
            }
        }

        return view('admin.analytics.content', [
            'periods' => self::PERIODS,
            'days' => $this->days($request),
            'insights' => $insights,
            'sets' => $sets,
            'topPages' => $insights->topPages(30),
            'broken' => $insights->brokenPages(15),
        ]);
    }

    /** Acquisition on its own screen: channels, sources, campaigns, and what each earned. */
    public function sources(Request $request): View
    {
        $insights = $this->period($request);

        return view('admin.analytics.sources', [
            'periods' => self::PERIODS,
            'days' => $this->days($request),
            'insights' => $insights,
            'channels' => $insights->byChannel(),
            'sources' => $insights->topSources(25),
            'referrers' => $insights->topReferrers(25),
            'campaigns' => $insights->campaigns(25),
            'revenueByTouch' => $insights->revenueByFirstTouch(15),
            'landing' => $insights->landingPages(15),
            'countries' => $insights->byCountry(25),
            'funnel' => $insights->funnel(),
            'events' => $insights->eventCounts(),
            'intent' => $insights->eventCounts(Events::CATEGORY_INTENT),
        ]);
    }

    private function period(Request $request): Insights
    {
        $days = $this->days($request);

        return $days === 1
            ? Insights::between(CarbonImmutable::today(), CarbonImmutable::today())
            : Insights::forDays($days);
    }

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 30);

        return array_key_exists($days, self::PERIODS) ? $days : 30;
    }

    /** Percentage change, or null when there is no baseline to compare against. */
    private function change(float $now, float $before): ?float
    {
        if ($before <= 0) {
            return $now > 0 ? null : 0.0;
        }

        return round(($now - $before) / $before * 100, 1);
    }
}
