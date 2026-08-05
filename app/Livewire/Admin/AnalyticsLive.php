<?php

namespace App\Livewire\Admin;

use App\Services\Analytics\Insights;
use App\Support\Analytics\Events;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who is on the site right now, and what they are reading.
 *
 * Polls rather than pushes, because a WebSocket server is a whole piece of
 * infrastructure to run for a page one person looks at. Ten seconds is slow
 * enough to cost nothing and fast enough to feel live.
 *
 * The window is what makes "now" meaningful: somebody is here if they asked
 * for a page in the last few minutes. There is no way to know they closed the
 * tab, so anything longer starts counting ghosts.
 */
class AnalyticsLive extends Component
{
    public int $window = 5;

    public function render(): View
    {
        return view('livewire.admin.analytics-live')
            ->layout('layouts.admin')
            ->title('Live');
    }

    #[Computed]
    public function visitors()
    {
        return $this->today()->liveVisitors($this->window);
    }

    #[Computed]
    public function todayTotals(): array
    {
        return $this->today()->totals();
    }

    #[Computed]
    public function recent()
    {
        return $this->today()->recentEvents(15);
    }

    #[Computed]
    public function lastHour(): array
    {
        $insights = Insights::between(
            \Carbon\CarbonImmutable::today(),
            \Carbon\CarbonImmutable::today(),
        );

        $counts = $insights->pageViews()
            ->where('viewed_at', '>=', now()->subHour())
            ->selectRaw(Insights::datePart('minute', 'viewed_at').' AS m, COUNT(*) AS n')
            ->groupBy('m')->pluck('n', 'm');

        // Keyed by clock minute so the newest column is always the right-hand
        // one, whatever minute of the hour it happens to be.
        $series = [];
        for ($i = 59; $i >= 0; $i--) {
            $minute = (int) now()->subMinutes($i)->format('i');
            $series[now()->subMinutes($i)->format('H:i')] = (int) ($counts[$minute] ?? 0);
        }

        return $series;
    }

    public function eventLabel(string $name): string
    {
        return Events::label($name);
    }

    private function today(): Insights
    {
        return Insights::between(\Carbon\CarbonImmutable::today(), \Carbon\CarbonImmutable::now());
    }
}
