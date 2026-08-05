{{-- Polls rather than pushes. Ten seconds is invisible to a watcher and cheap
     enough to leave open on a second monitor all day. --}}
<div wire:poll.10s>
  <div class="tb-page-header">
    <div>
      <h1><span class="an-live-dot @if($this->visitors->isNotEmpty()) is-on @endif"></span> Live</h1>
      <div class="tb-breadcrumb">
        <a href="{{ route('admin.analytics.index') }}" wire:navigate>Analytics</a> <span>/</span>
        Anyone who asked for a page in the last {{ $window }} minutes
      </div>
    </div>
    <div class="an-header-actions">
      <select class="tb-select" style="width:auto;" wire:model.live="window">
        <option value="1">Last minute</option>
        <option value="5">Last 5 minutes</option>
        <option value="15">Last 15 minutes</option>
        <option value="30">Last 30 minutes</option>
      </select>
      <a href="{{ route('admin.analytics.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-chart-line"></i> Overview</a>
    </div>
  </div>

  <div class="tb-stats-grid">
    <x-dash.stat :value="$this->visitors->count()" label="On the site now" icon="fa-tower-broadcast"
                 :tone="$this->visitors->isNotEmpty() ? 'ok' : ''" />
    <x-dash.stat :value="number_format($this->todayTotals['visitors'])" label="Visitors today" icon="fa-user-group"
                 :sub="$this->todayTotals['new_visitors'].' first time'" />
    <x-dash.stat :value="number_format($this->todayTotals['page_views'])" label="Pages today" icon="fa-file-lines" />
    <x-dash.stat :value="gmdate('i:s', $this->todayTotals['avg_seconds'])" label="Read per visit" icon="fa-clock" />
  </div>

  <div class="dash-grid">
    <x-dash.section title="The last hour, minute by minute" icon="fa-wave-square">
      <x-dash.columns :series="$this->lastHour" :height="90" :everyNth="10" />
    </x-dash.section>
  </div>

  <div class="dash-grid cols-2">
    <x-dash.section title="Right now" icon="fa-eye" :count="$this->visitors->count()">
      @if($this->visitors->isEmpty())
        <x-dash.empty icon="fa-moon" text="Nobody on the site this minute. It refreshes itself." />
      @else
        <div class="tb-table-wrap">
          <table class="tb-table an-live-table">
            <thead><tr><th>Who</th><th>Reading</th><th>Came by</th><th>Pages</th><th>Seen</th></tr></thead>
            <tbody>
              @foreach($this->visitors as $row)
                <tr wire:key="live-{{ $row['visitor']->id }}">
                  <td>
                    <a href="{{ route('admin.analytics.visitor', $row['visitor']) }}" wire:navigate class="an-who">
                      <span class="an-avatar {{ $row['visitor']->user ? 'is-known' : '' }}">
                        {{ \Illuminate\Support\Str::of($row['visitor']->displayName())->substr(0, 1)->upper() }}
                      </span>
                      <span>
                        <b>{{ $row['visitor']->user?->name ?? 'Anonymous' }}</b>
                        <em>{{ \App\Support\Analytics\Countries::flag($row['country']) }}
                          {{ ucfirst((string) $row['device']) }}</em>
                      </span>
                    </a>
                  </td>
                  <td class="an-path"><a href="{{ url($row['path']) }}" target="_blank" rel="noopener">{{ $row['path'] }}</a></td>
                  <td class="muted">{{ $row['source'] ?? \App\Support\Analytics\Channel::label($row['channel']) }}</td>
                  <td>{{ $row['pages'] }}</td>
                  <td class="muted">{{ $row['seen']?->diffForHumans(null, true) }} ago</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-dash.section>

    <x-dash.section title="Happening today" icon="fa-bolt">
      @if($this->recent->isEmpty())
        <x-dash.empty icon="fa-bolt" text="No actions recorded today yet" />
      @else
        <div class="an-feed">
          @foreach($this->recent as $event)
            <a class="an-feed-row" href="{{ route('admin.analytics.visitor', $event->visitor_id) }}" wire:navigate
               wire:key="ev-{{ $event->id }}">
              <i class="fas {{ \App\Support\Analytics\Events::icon($event->name) }} an-feed-icon cat-{{ $event->category }}"></i>
              <span class="an-feed-main">
                <b>{{ $event->user?->name ?? 'Someone' }}</b>
                {{ $this->eventLabel($event->name) }}
                @if($event->label)<em>{{ \Illuminate\Support\Str::limit($event->label, 44) }}</em>@endif
              </span>
              @if($event->value)<span class="an-feed-value">{{ $event->currency ?? 'UGX' }} {{ number_format((float) $event->value) }}</span>@endif
              <span class="an-feed-when">{{ $event->occurred_at->format('H:i') }}</span>
            </a>
          @endforeach
        </div>
      @endif
    </x-dash.section>
  </div>
</div>
