<div>
  <div class="tb-page-header">
    <div>
      <h1>Course waitlist</h1>
      <div class="tb-breadcrumb">Everyone who asked to be told when a course opens</div>
    </div>
    <a href="{{ route('admin.courses.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-book"></i> Courses</a>
  </div>

  <div class="tb-stats-grid">
    <x-dash.stat :value="number_format($this->counts['total'])" label="On the list" icon="fa-bell" />
    <x-dash.stat :value="number_format($this->counts['waiting'])" label="Still waiting" icon="fa-hourglass-half"
                 :tone="$this->counts['waiting'] ? 'warn' : ''" />
    <x-dash.stat :value="number_format($this->counts['courses'])" label="Courses asked for" icon="fa-book" />
  </div>

  @if($this->demand->isNotEmpty())
    {{-- Which course to finish first. This is the only ranking on the site
         built from what people asked for rather than what was assumed. --}}
    <x-dash.section title="Which course people want most" icon="fa-ranking-star">
      @php $max = $this->demand->max('total') ?: 1; @endphp
      <div class="an-rows">
        @foreach($this->demand as $row)
          <div class="an-row">
            <span class="an-row-bar" style="width:{{ round($row->total / $max * 100) }}%"></span>
            <span class="an-row-label">
              {{ $row->course?->title ?? 'Removed course' }}
              <em>{{ $row->waiting }} still waiting @if(! $row->course?->is_coming_soon)· already open @endif</em>
            </span>
            <span class="an-row-value">
              {{ $row->total }}
              @if($row->waiting > 0)
                <button type="button" class="tk-pull" style="margin-left:8px;"
                        wire:click="markCourseNotified({{ $row->course_id }})"
                        wire:confirm="Mark all {{ $row->waiting }} people on this course as told?">Mark all told</button>
              @endif
            </span>
          </div>
        @endforeach
      </div>
    </x-dash.section>
  @endif

  <div class="tb-card">
    <div class="tb-card-header an-filters">
      <input type="search" class="tb-input" placeholder="Name, email or number" wire:model.live.debounce.400ms="q">
      <select class="tb-select" wire:model.live="courseId">
        <option value="">Every course</option>
        @foreach($courses as $course)
          <option value="{{ $course->id }}">{{ $course->title }}</option>
        @endforeach
      </select>
      <select class="tb-select" wire:model.live="status">
        <option value="waiting">Still waiting</option>
        <option value="notified">Already told</option>
        <option value="all">Everyone</option>
      </select>
    </div>

    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead><tr><th>Who</th><th>WhatsApp</th><th>Course</th><th>Asked</th><th></th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            <tr wire:key="w-{{ $row->id }}">
              <td>
                <b>{{ $row->name }}</b>
                <div class="an-sub">{{ $row->email }}</div>
              </td>
              <td>
                {{-- A number that opens a chat. The whole list is only useful
                     if messaging somebody is one tap from seeing them. --}}
                <a href="https://wa.me/{{ $row->whatsapp }}" target="_blank" rel="noopener" class="tk-client">
                  <i class="fab fa-whatsapp"></i> +{{ $row->whatsapp }}
                </a>
              </td>
              <td class="muted">{{ $row->course?->title ?? 'Removed' }}</td>
              <td class="muted">{{ $row->created_at->diffForHumans() }}</td>
              <td>
                @if($row->notified_at)
                  <span class="badge-tb badge-success">Told {{ $row->notified_at->format('d M') }}</span>
                @else
                  <button type="button" class="btn-tb btn-tb-sm btn-tb-ghost" wire:click="markNotified({{ $row->id }})">
                    Mark told
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5">
              <x-dash.empty icon="fa-bell" text="Nobody on the waitlist yet. Every course page collects names while it is marked coming soon." />
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($rows->hasPages())<div class="tb-card-body">{{ $rows->links() }}</div>@endif
  </div>
</div>
