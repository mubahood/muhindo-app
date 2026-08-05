<div>
  <div class="tb-page-header">
    <div>
      <h1>
        <span class="an-avatar lg {{ $visitor->user ? 'is-known' : '' }}">
          {{ \Illuminate\Support\Str::of($visitor->displayName())->substr(0, 1)->upper() }}
        </span>
        {{ $visitor->user?->name ?? 'Anonymous visitor' }}
      </h1>
      <div class="tb-breadcrumb">
        <a href="{{ route('admin.analytics.index') }}" wire:navigate>Analytics</a> <span>/</span>
        <a href="{{ route('admin.analytics.visitors') }}" wire:navigate>Visitors</a> <span>/</span>
        @if($visitor->user)
          {{ $visitor->user->email }}
        @else
          Never signed in, known only by this browser
        @endif
      </div>
    </div>
    @if($visitor->user)
      <div class="an-header-actions">
        <a href="{{ route('admin.users.show', $visitor->user) }}" wire:navigate class="btn-tb btn-tb-ghost">
          <i class="fas fa-id-badge"></i> Account
        </a>
      </div>
    @endif
  </div>

  @if($visitor->is_bot)
    <div class="tb-alert tb-alert-warn">
      <i class="fas fa-robot"></i>
      This is a crawler, not a person. It is recorded so the traffic can be accounted for, and is left out of every figure elsewhere.
    </div>
  @endif

  <div class="tb-stats-grid">
    <x-dash.stat :value="number_format($this->totalSessions)" label="Visits" icon="fa-door-open"
                 :sub="$visitor->first_seen_at?->diffForHumans()" />
    <x-dash.stat :value="number_format($visitor->page_views_count)" label="Pages read" icon="fa-file-lines" />
    <x-dash.stat :value="$visitor->engaged_seconds ? gmdate($visitor->engaged_seconds >= 3600 ? 'H:i:s' : 'i:s', $visitor->engaged_seconds) : '-'"
                 label="Time on the site" icon="fa-clock" />
    <x-dash.stat :value="number_format($visitor->events_count)" label="Things done" icon="fa-hand-pointer" />
    <x-dash.stat :value="$visitor->revenue > 0 ? 'UGX '.number_format((float) $visitor->revenue) : '-'"
                 label="Paid in" icon="fa-sack-dollar" :tone="$visitor->revenue > 0 ? 'ok' : ''" />
  </div>

  <div class="dash-grid cols-2">
    {{-- First touch. The single most useful fact about a customer, and the one
         that is gone forever if it is not captured on the very first request. --}}
    <x-dash.section title="How they first found you" icon="fa-signs-post">
      <div class="tb-card-body">
        <dl class="an-facts">
          <dt>Channel</dt><dd>{{ $this->acquisition['channel'] }}</dd>
          <dt>Source</dt><dd>{{ $this->acquisition['source'] ?? 'Typed the address in' }}</dd>
          @if($this->acquisition['campaign'])
            <dt>Campaign</dt><dd>{{ $this->acquisition['campaign'] }}</dd>
          @endif
          @if($this->acquisition['referrer'])
            <dt>Link</dt>
            <dd class="an-break"><a href="{{ $this->acquisition['referrer'] }}" target="_blank" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($this->acquisition['referrer'], 60) }}</a></dd>
          @endif
          <dt>Landed on</dt>
          <dd><a href="{{ url($this->acquisition['landing'] ?? '/') }}" target="_blank" rel="noopener">{{ $this->acquisition['landing'] ?? '/' }}</a></dd>
          <dt>First seen</dt><dd>{{ $this->acquisition['at']?->format('D d M Y, H:i') ?? '-' }}</dd>
          <dt>Last seen</dt><dd>{{ $visitor->last_seen_at?->format('D d M Y, H:i') ?? '-' }}</dd>
        </dl>
      </div>
    </x-dash.section>

    <x-dash.section title="Their setup" icon="fa-laptop">
      <div class="tb-card-body">
        <dl class="an-facts">
          <dt>Device</dt><dd>{{ ucfirst((string) $visitor->last_device) ?: 'Unknown' }}</dd>
          <dt>Browser</dt><dd>{{ $visitor->last_browser ?? 'Unknown' }}</dd>
          <dt>System</dt><dd>{{ $visitor->last_os ?? 'Unknown' }}</dd>
          <dt>Country</dt>
          <dd>{{ \App\Support\Analytics\Countries::flag($visitor->last_country) }}
              {{ \App\Support\Analytics\Countries::name($visitor->last_country) }}</dd>
          <dt>Address</dt><dd class="an-mono">{{ $visitor->last_ip ?? '-' }}</dd>
          <dt>Browser id</dt><dd class="an-mono">{{ $visitor->token }}</dd>
          @if($visitor->identified_at)
            <dt>Signed in first</dt><dd>{{ $visitor->identified_at->format('D d M Y, H:i') }}</dd>
          @endif
        </dl>
      </div>
    </x-dash.section>
  </div>

  @if($this->interests->isNotEmpty() || $this->favouritePages->isNotEmpty())
    <div class="dash-grid cols-2">
      @if($this->interests->isNotEmpty())
        <x-dash.section title="What they keep looking at" icon="fa-heart">
          {{-- Resolved to the actual course or product, so this reads as a list
               of things you sell rather than a list of URLs. --}}
          @php $maxI = $this->interests->max('views') ?: 1; @endphp
          <x-dash.rows :rows="$this->interests->map(fn($row) => [
              'label' => $row->subject->title ?? $row->subject->name ?? class_basename($row->subject_type),
              'sub' => class_basename($row->subject_type).($row->seconds ? ' · '.gmdate('i:s', (int) $row->seconds).' spent' : ''),
              'value' => $row->views.'x',
              'bar' => $row->views / $maxI * 100,
          ])->all()" />
        </x-dash.section>
      @endif

      <x-dash.section title="Pages they returned to" icon="fa-rotate-right">
        @php $maxF = $this->favouritePages->max('views') ?: 1; @endphp
        <x-dash.rows :rows="$this->favouritePages->map(fn($p) => [
            'label' => $p->path,
            'href' => url($p->path),
            'sub' => $p->seconds ? gmdate('i:s', (int) $p->seconds).' in total' : null,
            'value' => $p->views.'x',
            'bar' => $p->views / $maxF * 100,
        ])->all()" />
      </x-dash.section>
    </div>
  @endif

  @if($this->commercial['known'])
    <div class="dash-grid cols-3">
      <x-dash.section title="Courses" icon="fa-graduation-cap" :count="$this->commercial['enrollments']->count()">
        @if($this->commercial['enrollments']->isEmpty())
          <x-dash.empty text="Not enrolled on anything yet" />
        @else
          <div class="tb-card-body"><div class="an-list">
            @foreach($this->commercial['enrollments'] as $enrollment)
              <div class="an-list-row">
                <span>{{ $enrollment->course?->title ?? 'Course removed' }}</span>
                <span class="badge-tb badge-{{ $enrollment->status === 'active' ? 'success' : 'pending' }}">
                  {{ ucfirst((string) $enrollment->status) }} · {{ $enrollment->progress_percent }}%
                </span>
              </div>
            @endforeach
          </div></div>
        @endif
      </x-dash.section>

      <x-dash.section title="Money" icon="fa-file-invoice-dollar">
        <div class="tb-card-body">
          <dl class="an-facts">
            <dt>Paid</dt><dd>{{ $this->commercial['currency'] }} {{ number_format($this->commercial['paid']) }}</dd>
            <dt>Outstanding</dt><dd>{{ $this->commercial['currency'] }} {{ number_format($this->commercial['outstanding']) }}</dd>
            <dt>Invoices</dt><dd>{{ $this->commercial['invoices']->count() }}</dd>
          </dl>
        </div>
      </x-dash.section>

      <x-dash.section title="Project requests" icon="fa-briefcase" :count="$this->commercial['inquiries']->count()">
        @if($this->commercial['inquiries']->isEmpty())
          <x-dash.empty text="No project requests" />
        @else
          <div class="tb-card-body"><div class="an-list">
            @foreach($this->commercial['inquiries'] as $inquiry)
              <div class="an-list-row">
                <span>{{ \Illuminate\Support\Str::limit($inquiry->title ?? $inquiry->project_type, 34) }}</span>
                <span class="muted">{{ $inquiry->created_at?->format('d M Y') }}</span>
              </div>
            @endforeach
          </div></div>
        @endif
      </x-dash.section>
    </div>
  @endif

  {{-- The history itself, broken at the session boundaries. This is the part
       that answers "what actually happened", and no summary above it can. --}}
  <div class="dash-grid">
    <x-dash.section title="Everything they did, most recent first" icon="fa-timeline"
                    :count="$this->totalSessions">
      @if($this->sessions->isEmpty())
        <x-dash.empty icon="fa-timeline" text="No sessions recorded" />
      @else
        <div class="tb-card-body">
          <div class="an-sessions">
            @foreach($this->sessions as $session)
              @php $visit = $session['visit']; @endphp
              <div class="an-session" wire:key="s-{{ $visit->id }}">
                <div class="an-session-head">
                  <span class="an-session-when">
                    <b>{{ $visit->started_at->format('D d M Y') }}</b>
                    <em>{{ $visit->started_at->format('H:i') }}</em>
                  </span>
                  <span class="an-session-meta">
                    <span class="badge-tb badge-info">{{ \App\Support\Analytics\Channel::label($visit->channel) }}</span>
                    @if($visit->source)<span class="muted">from {{ $visit->source }}</span>@endif
                    <span class="muted">{{ $visit->page_views_count }} {{ \Illuminate\Support\Str::plural('page', $visit->page_views_count) }}</span>
                    @if($visit->engaged_seconds)<span class="muted">{{ gmdate('i:s', $visit->engaged_seconds) }} reading</span>@endif
                    @if($visit->is_bounce)<span class="badge-tb">Left at once</span>@endif
                  </span>
                </div>

                <ol class="an-track">
                  @foreach($session['timeline'] as $entry)
                    <li class="an-track-item {{ $entry['kind'] === 'event' ? 'is-event cat-'.($entry['category'] ?? '') : '' }}">
                      <span class="an-track-time">{{ $entry['at']->format('H:i:s') }}</span>
                      <span class="an-track-dot">
                        @if($entry['kind'] === 'event')<i class="fas {{ $entry['icon'] }}"></i>@endif
                      </span>
                      <span class="an-track-body">
                        @if($entry['kind'] === 'page')
                          <a href="{{ url($entry['path']) }}" target="_blank" rel="noopener">{{ $entry['title'] }}</a>
                          @if(($entry['status'] ?? 200) >= 400)
                            <span class="badge-tb badge-danger">{{ $entry['status'] }}</span>
                          @endif
                          <em class="an-track-detail">
                            {{ $entry['path'] }}
                            @if($entry['seconds'])· read for {{ gmdate('i:s', $entry['seconds']) }}@endif
                            @if($entry['scroll'])· {{ $entry['scroll'] }}% down the page @endif
                          </em>
                        @else
                          <b>{{ $entry['title'] }}</b>
                          @if(!empty($entry['label']))<em class="an-track-detail">{{ $entry['label'] }}</em>@endif
                          @if(!empty($entry['value']))
                            <span class="badge-tb badge-success">{{ $entry['currency'] ?? 'UGX' }} {{ number_format((float) $entry['value']) }}</span>
                          @endif
                        @endif
                      </span>
                    </li>
                  @endforeach
                </ol>
              </div>
            @endforeach
          </div>

          @if($this->totalSessions > $this->sessions->count())
            <button type="button" class="btn-tb btn-tb-ghost an-more" wire:click="showMore">
              Show earlier visits ({{ $this->totalSessions - $this->sessions->count() }} left)
            </button>
          @endif
        </div>
      @endif
    </x-dash.section>
  </div>
</div>
