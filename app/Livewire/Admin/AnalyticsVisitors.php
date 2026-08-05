<?php

namespace App\Livewire\Admin;

use App\Models\Visitor;
use App\Support\Analytics\Channel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every visitor the site has ever had, searchable.
 *
 * The default sort is last seen, not first: the useful question about an
 * audience list is almost always "who has been here recently", and a list
 * ordered by signup date answers it only on the day you build the page.
 */
class AnalyticsVisitors extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 'all')]
    public string $segment = 'all';

    #[Url(except: '')]
    public string $channel = '';

    #[Url(except: '')]
    public string $country = '';

    #[Url(except: 'last_seen_at')]
    public string $sort = 'last_seen_at';

    public bool $includeBots = false;

    public const SEGMENTS = [
        'all' => 'Everyone',
        'known' => 'Signed in',
        'anonymous' => 'Never signed in',
        'returning' => 'Came back',
        'converted' => 'Converted',
        'engaged' => 'Read properly',
    ];

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'segment', 'channel', 'country']);
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.analytics-visitors', [
            'visitors' => $this->results(),
            'channels' => Channel::LABELS,
            'countries' => Visitor::human()->whereNotNull('last_country')
                ->distinct()->orderBy('last_country')->pluck('last_country'),
            'segments' => self::SEGMENTS,
        ])->layout('layouts.admin')->title('Visitors');
    }

    private function results()
    {
        return Visitor::query()
            ->with('user:id,name,email,role')
            ->when(! $this->includeBots, fn (Builder $q) => $q->where('is_bot', false))
            ->when($this->q !== '', function (Builder $query) {
                $term = '%'.$this->q.'%';
                $query->where(function (Builder $q) use ($term) {
                    $q->where('token', 'like', $term)
                        ->orWhere('first_landing_path', 'like', $term)
                        ->orWhere('first_source', 'like', $term)
                        ->orWhere('last_ip', 'like', $term)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($this->country !== '', fn (Builder $q) => $q->where('last_country', $this->country))
            ->when($this->channel !== '', fn (Builder $q) => $q->whereHas('visits', fn (Builder $v) => $v->where('channel', $this->channel)))
            ->when($this->segment === 'known', fn (Builder $q) => $q->whereNotNull('user_id'))
            ->when($this->segment === 'anonymous', fn (Builder $q) => $q->whereNull('user_id'))
            ->when($this->segment === 'returning', fn (Builder $q) => $q->where('visits_count', '>', 1))
            ->when($this->segment === 'converted', fn (Builder $q) => $q->whereNotNull('converted_at'))
            // "Read properly" is a minute of attention across at least three
            // pages. One long page view is a tab left open; three is a person.
            ->when($this->segment === 'engaged', fn (Builder $q) => $q->where('engaged_seconds', '>=', 60)->where('page_views_count', '>=', 3))
            ->orderByDesc(match ($this->sort) {
                'first_seen_at' => 'first_seen_at',
                'page_views_count' => 'page_views_count',
                'engaged_seconds' => 'engaged_seconds',
                'revenue' => 'revenue',
                'visits_count' => 'visits_count',
                default => 'last_seen_at',
            })
            ->paginate(30);
    }
}
