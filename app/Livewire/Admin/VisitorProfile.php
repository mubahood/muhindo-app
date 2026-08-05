<?php

namespace App\Livewire\Admin;

use App\Models\AnalyticsEvent;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\PageView;
use App\Models\ProjectInquiry;
use App\Models\Visit;
use App\Models\Visitor;
use App\Support\Analytics\Channel;
use App\Support\Analytics\Events;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * One visitor, reconstructed.
 *
 * The story a summary cannot tell: every sitting they have had, in order, with
 * the pages inside it and the things they did between them. Where they came
 * from the first time, what they kept coming back to, how long they read it,
 * and what finally made them act, or where they stopped.
 *
 * Sessions are the unit rather than pages, because that is the shape of the
 * thing being remembered. A flat list of 300 page views ordered by time is
 * technically the same data and tells you nothing; the same list broken at the
 * gaps shows a person who read for ten minutes in March, came back in April
 * and bought.
 */
class VisitorProfile extends Component
{
    public Visitor $visitor;

    public int $sessionLimit = 15;

    public function mount(Visitor $visitor): void
    {
        $this->visitor = $visitor->load('user');
    }

    public function showMore(): void
    {
        $this->sessionLimit += 15;
    }

    public function render(): View
    {
        return view('livewire.admin.visitor-profile')
            ->layout('layouts.admin')
            ->title($this->visitor->displayName());
    }

    /**
     * The visit list, each one carrying its pages and its events already
     * interleaved into a single ordered stream.
     */
    #[Computed]
    public function sessions(): Collection
    {
        $visits = Visit::where('visitor_id', $this->visitor->id)
            ->latest('started_at')
            ->limit($this->sessionLimit)
            ->get();

        if ($visits->isEmpty()) {
            return collect();
        }

        $ids = $visits->pluck('id');

        $views = PageView::whereIn('visit_id', $ids)->orderBy('viewed_at')->get()->groupBy('visit_id');
        $events = AnalyticsEvent::whereIn('visit_id', $ids)->orderBy('occurred_at')->get()->groupBy('visit_id');

        return $visits->map(function (Visit $visit) use ($views, $events) {
            $timeline = collect()
                ->concat(($views[$visit->id] ?? collect())->map(fn (PageView $v) => [
                    'kind' => 'page',
                    'at' => $v->viewed_at,
                    'title' => $v->title ?: $this->prettyPath($v->path),
                    'path' => $v->path,
                    'seconds' => $v->engaged_seconds,
                    'scroll' => $v->scroll_percent,
                    'status' => $v->status,
                ]))
                ->concat(($events[$visit->id] ?? collect())->map(fn (AnalyticsEvent $e) => [
                    'kind' => 'event',
                    'at' => $e->occurred_at,
                    'title' => Events::label($e->name),
                    'label' => $e->label,
                    'icon' => Events::icon($e->name),
                    'category' => $e->category,
                    'value' => $e->value,
                    'currency' => $e->currency,
                ]))
                ->sortBy('at')
                ->values();

            return ['visit' => $visit, 'timeline' => $timeline];
        });
    }

    #[Computed]
    public function totalSessions(): int
    {
        return Visit::where('visitor_id', $this->visitor->id)->count();
    }

    /** The pages this person came back to. Interest, as opposed to a click. */
    #[Computed]
    public function favouritePages(): Collection
    {
        return PageView::where('visitor_id', $this->visitor->id)
            ->selectRaw('path, COUNT(*) AS views, SUM(COALESCE(engaged_seconds,0)) AS seconds, MAX(viewed_at) AS last_at')
            ->groupBy('path')
            ->orderByDesc('views')->orderByDesc('seconds')
            ->limit(8)
            ->get();
    }

    /** What they looked at, resolved to the actual course or product. */
    #[Computed]
    public function interests(): Collection
    {
        return PageView::where('visitor_id', $this->visitor->id)
            ->whereNotNull('subject_type')
            ->selectRaw('subject_type, subject_id, COUNT(*) AS views, SUM(COALESCE(engaged_seconds,0)) AS seconds')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->subject = rescue(fn () => app($row->subject_type)::find($row->subject_id), null, false);

                return $row;
            })
            ->filter(fn ($row) => $row->subject !== null)
            ->values();
    }

    /**
     * What this person is worth, pulled from the business records rather than
     * from the analytics tables. Only available once they have an account.
     */
    #[Computed]
    public function commercial(): array
    {
        $user = $this->visitor->user;

        if (! $user) {
            return ['known' => false];
        }

        $invoices = Invoice::where('billable_type', $user->getMorphClass())
            ->where('billable_id', $user->id)
            ->get();

        return [
            'known' => true,
            'enrollments' => Enrollment::where('user_id', $user->id)->with('course:id,title,slug')->latest()->get(),
            'invoices' => $invoices,
            'paid' => (float) $invoices->sum('amount_paid'),
            'outstanding' => (float) $invoices->sum('balance'),
            'currency' => $invoices->first()->currency ?? 'UGX',
            'inquiries' => ProjectInquiry::where('user_id', $user->id)->latest()->get(),
        ];
    }

    #[Computed]
    public function acquisition(): array
    {
        $first = Visit::where('visitor_id', $this->visitor->id)->oldest('started_at')->first();

        return [
            'channel' => Channel::label($first?->channel),
            'source' => $this->visitor->first_source ?? $first?->source,
            'campaign' => $this->visitor->first_campaign,
            'referrer' => $this->visitor->first_referrer,
            'landing' => $this->visitor->first_landing_path,
            'at' => $this->visitor->first_seen_at,
        ];
    }

    /** A readable name for a path, for the many rows with no recorded title. */
    private function prettyPath(string $path): string
    {
        $last = trim(substr($path, (int) strrpos($path, '/')), '/');

        if ($last === '') {
            return 'Home';
        }

        return ucfirst(str_replace('-', ' ', $last));
    }
}
