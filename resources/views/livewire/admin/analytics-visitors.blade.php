<div>
  <div class="tb-page-header">
    <div>
      <h1>Visitors</h1>
      <div class="tb-breadcrumb">
        <a href="{{ route('admin.analytics.index') }}" wire:navigate>Analytics</a> <span>/</span>
        Everyone who has ever opened the site, named where they signed in
      </div>
    </div>
    <div class="an-header-actions">
      <a href="{{ route('admin.analytics.live') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-tower-broadcast"></i> Live</a>
      <a href="{{ route('admin.analytics.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-chart-line"></i> Overview</a>
    </div>
  </div>

  {{-- Segments first, because "everyone" is rarely the question. The useful
       ones are the people who came back and the people who converted. --}}
  <div class="an-segments">
    @foreach($segments as $key => $label)
      <button type="button" wire:click="$set('segment', '{{ $key }}')"
              class="an-segment {{ $segment === $key ? 'on' : '' }}">{{ $label }}</button>
    @endforeach
  </div>

  <div class="tb-card">
    <div class="tb-card-header an-filters">
      <input type="search" class="tb-input" placeholder="Name, email, IP, landing page or source"
             wire:model.live.debounce.400ms="q">
      <select class="tb-select" wire:model.live="channel">
        <option value="">Any channel</option>
        @foreach($channels as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
      </select>
      <select class="tb-select" wire:model.live="country">
        <option value="">Any country</option>
        @foreach($countries as $code)
          <option value="{{ $code }}">{{ \App\Support\Analytics\Countries::name($code) }}</option>
        @endforeach
      </select>
      <select class="tb-select" wire:model.live="sort">
        <option value="last_seen_at">Most recent</option>
        <option value="first_seen_at">Newest</option>
        <option value="visits_count">Most visits</option>
        <option value="page_views_count">Most pages</option>
        <option value="engaged_seconds">Most time</option>
        <option value="revenue">Most spent</option>
      </select>
      <label class="an-checkbox">
        <input type="checkbox" wire:model.live="includeBots"> <span>Show crawlers</span>
      </label>
      @if($q !== '' || $segment !== 'all' || $channel !== '' || $country !== '')
        <button type="button" class="btn-tb btn-tb-sm btn-tb-ghost" wire:click="clearFilters">Clear</button>
      @endif
    </div>

    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead>
          <tr>
            <th>Who</th><th>First came by</th><th>Visits</th><th>Pages</th>
            <th>Read</th><th>Outcome</th><th>Last seen</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($visitors as $visitor)
            <tr wire:key="v-{{ $visitor->id }}" @class(['an-bot' => $visitor->is_bot])>
              <td>
                <a href="{{ route('admin.analytics.visitor', $visitor) }}" wire:navigate class="an-who">
                  <span class="an-avatar {{ $visitor->user ? 'is-known' : '' }}">
                    {{ \Illuminate\Support\Str::of($visitor->displayName())->substr(0, 1)->upper() }}
                  </span>
                  <span>
                    <b>{{ $visitor->user?->name ?? 'Anonymous' }}</b>
                    <em>
                      {{ \App\Support\Analytics\Countries::flag($visitor->last_country) }}
                      {{ $visitor->user?->email ?? ucfirst((string) $visitor->last_device).' · '.($visitor->last_browser ?? 'unknown browser') }}
                    </em>
                  </span>
                </a>
              </td>
              <td>
                @if($visitor->first_source)
                  <span class="badge-tb badge-info">{{ $visitor->first_source }}</span>
                @else
                  <span class="muted">Typed it in</span>
                @endif
                @if($visitor->first_landing_path)
                  <div class="an-sub">{{ \Illuminate\Support\Str::limit($visitor->first_landing_path, 34) }}</div>
                @endif
              </td>
              <td>{{ number_format($visitor->visits_count) }}
                @if($visitor->isReturning())<i class="fas fa-repeat muted" title="Came back"></i>@endif
              </td>
              <td>{{ number_format($visitor->page_views_count) }}</td>
              <td class="muted">{{ $visitor->engaged_seconds ? gmdate($visitor->engaged_seconds >= 3600 ? 'H:i:s' : 'i:s', $visitor->engaged_seconds) : '-' }}</td>
              <td>
                @if($visitor->revenue > 0)
                  <span class="badge-tb badge-success">UGX {{ number_format((float) $visitor->revenue) }}</span>
                @elseif($visitor->converted_at)
                  <span class="badge-tb badge-success">Converted</span>
                @elseif($visitor->user)
                  <span class="badge-tb badge-info">Has an account</span>
                @elseif($visitor->is_bot)
                  <span class="badge-tb">Crawler</span>
                @else
                  <span class="muted">-</span>
                @endif
              </td>
              <td class="muted">{{ $visitor->last_seen_at?->diffForHumans() ?? '-' }}</td>
              <td>
                <a href="{{ route('admin.analytics.visitor', $visitor) }}" wire:navigate
                   class="btn-tb btn-tb-ghost btn-tb-icon" title="Full history"><i class="fas fa-eye"></i></a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8">
              <x-dash.empty icon="fa-user-group" text="No visitors match that. Traffic appears here as soon as somebody opens the site." />
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($visitors->hasPages())
      <div class="tb-card-body">{{ $visitors->links() }}</div>
    @endif
  </div>
</div>
