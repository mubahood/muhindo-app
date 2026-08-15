<div>
  <div class="tb-page-header">
    <div>
      <h1>Today</h1>
      <div class="tb-breadcrumb">{{ now()->format('l, d F Y') }}</div>
    </div>
    <div class="an-header-actions">
      <a href="{{ route('admin.projects.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-diagram-project"></i> Projects</a>
      <a href="{{ route('dashboard') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-gauge"></i> Dashboard</a>
    </div>
  </div>

  <div class="tb-stats-grid">
    <x-dash.stat :value="$this->overdue->count()" label="Overdue" icon="fa-triangle-exclamation"
                 :tone="$this->overdue->count() ? 'bad' : ''" />
    <x-dash.stat :value="$this->today->count()" label="Due today" icon="fa-list-check" />
    <x-dash.stat :value="$this->doneToday" label="Done today" icon="fa-circle-check"
                 :tone="$this->doneToday ? 'ok' : ''" />
    <x-dash.stat :value="$this->undated->count()" label="Unscheduled" icon="fa-inbox" />
  </div>

  {{-- Capture first. A promise made on a call has about ten seconds to get
       written down before the call moves on and it is gone. --}}
  <div class="tb-card tk-capture">
    <form wire:submit="addTask" class="tk-capture-form">
      <input type="text" class="tb-input" wire:model="newTask" maxlength="190"
             placeholder="Write anything down, place it later..." aria-label="New task">
      <select class="tb-select" wire:model="newTaskProject" aria-label="For which project">
        <option value="">Personal</option>
        @foreach($this->projects as $project)
          <option value="{{ $project->id }}">{{ $project->client?->name ?? $project->title }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn-tb"><i class="fas fa-plus"></i> Add</button>
    </form>
  </div>

  @if($this->contactHealth->isNotEmpty())
    {{-- The number that exists to be uncomfortable. Silence with a client is
         not an event, it is an absence that grows while nothing counts it. --}}
    <div class="tb-card">
      <div class="tb-card-header">
        <span class="tb-card-title"><i class="fas fa-comment-dots muted"></i> Days since each client last heard from you</span>
      </div>
      <div class="tk-health">
        @foreach($this->contactHealth as $row)
          <a class="tk-health-row lvl-{{ $row['level'] }}"
             href="{{ route('admin.projects.show', $row['project']) }}" wire:navigate>
            <span class="tk-health-days">{{ $row['days'] !== null ? $row['days'] : '?' }}</span>
            <span class="tk-health-main">
              <b>{{ $row['client']->name }}</b>
              <em>{{ $row['project']->title }}</em>
            </span>
            <span class="tk-health-note">
              @if($row['last_at'])
                last update {{ $row['last_at']->diffForHumans() }}
              @else
                never updated
              @endif
            </span>
          </a>
        @endforeach
      </div>
    </div>
  @endif

  @if($this->overdue->isNotEmpty())
    <div class="tb-card tk-block is-overdue">
      <div class="tb-card-header">
        <span class="tb-card-title"><i class="fas fa-triangle-exclamation"></i> Overdue</span>
        <span class="tk-count">{{ $this->overdue->count() }}</span>
      </div>
      <div class="tk-list">
        @foreach($this->overdue as $task)
          @include('livewire.admin.partials.task-row', ['task' => $task, 'showPull' => true])
        @endforeach
      </div>
    </div>
  @endif

  <div class="tb-card tk-block">
    <div class="tb-card-header">
      <span class="tb-card-title"><i class="fas fa-sun"></i> Today</span>
      <span class="tk-count">{{ $this->today->count() }}</span>
    </div>
    @if($this->today->isEmpty())
      <x-dash.empty icon="fa-mug-hot" text="Nothing scheduled for today. Pull something up from below, or enjoy it." />
    @else
      <div class="tk-list">
        @foreach($this->today as $task)
          @include('livewire.admin.partials.task-row', ['task' => $task, 'showPull' => false])
        @endforeach
      </div>
    @endif
  </div>

  @if($this->upcoming->isNotEmpty())
    <div class="tb-card tk-block is-quiet">
      <div class="tb-card-header">
        <span class="tb-card-title"><i class="fas fa-calendar-days"></i> Next 7 days</span>
      </div>
      <div class="tk-list">
        @foreach($this->upcoming as $date => $tasks)
          <div class="tk-daybreak">
            {{ \Illuminate\Support\Carbon::parse($date)->format('l d M') }}
            <em>{{ $tasks->count() }} {{ \Illuminate\Support\Str::plural('task', $tasks->count()) }}</em>
          </div>
          @foreach($tasks as $task)
            @include('livewire.admin.partials.task-row', ['task' => $task, 'showPull' => true])
          @endforeach
        @endforeach
      </div>
    </div>
  @endif

  @if($this->undated->isNotEmpty())
    <div class="tb-card tk-block is-quiet">
      <div class="tb-card-header">
        <span class="tb-card-title"><i class="fas fa-inbox"></i> Not scheduled yet</span>
        <span class="tk-count">{{ $this->undated->count() }}</span>
      </div>
      <div class="tk-list">
        @foreach($this->undated as $task)
          @include('livewire.admin.partials.task-row', ['task' => $task, 'showPull' => true])
        @endforeach
      </div>
    </div>
  @endif
</div>
