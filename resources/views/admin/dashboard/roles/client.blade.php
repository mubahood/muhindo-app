@php
    $client = $user->client;
    $projects = $client ? $svc->clientProjects($client) : collect();
    $balance = $client ? $svc->clientOutstandingBalance($client) : '0.00';
    $openRequests = $svc->clientOpenRequests($user);
    $taskProgress = $svc->clientTaskProgress($projects);
    $recentUpdates = $svc->clientRecentUpdates($projects);
    $activeProjects = $projects->whereIn('status', ['proposal', 'active', 'on_hold']);
    $completedProjects = $projects->where('status', 'completed');
@endphp

@if($user->isStudent() || $user->isAdmin())
  <div class="dash-section-title" style="margin-top:26px;"><i class="fas fa-briefcase"></i> My projects</div>
@endif

<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($activeProjects->count())" label="Active projects" icon="fa-diagram-project"
    :href="route('portal.index')" />
  <x-dash.stat :value="number_format($completedProjects->count())" label="Delivered" icon="fa-circle-check" tone="ok" />
  <x-dash.stat :value="number_format($openRequests->count())" label="Open requests" icon="fa-paper-plane"
    :tone="$openRequests->count() > 0 ? 'warn' : ''" />
  <x-dash.stat :value="'UGX '.number_format((float) $balance)" label="Outstanding" icon="fa-file-invoice-dollar"
    :tone="(float) $balance > 0 ? 'warn' : 'ok'" :href="route('portal.invoices')" />
</div>

{{-- Step 1 of the journey: a request that hasn't become a project yet. --}}
@if($openRequests->isNotEmpty())
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-paper-plane"></i> Your requests</div>
  <div class="tb-card">
    <div class="todo-list">
      @foreach($openRequests as $inquiry)
        <div class="todo-row" style="cursor:default;">
          <span class="todo-icon"><i class="fas fa-paper-plane"></i></span>
          <span class="todo-main">
            <span class="todo-title">{{ ucfirst(str_replace('_', ' ', $inquiry->project_type)) }}</span>
            <span class="todo-meta">Sent {{ $inquiry->created_at->diffForHumans() }}</span>
          </span>
          <span class="badge-tb {{ $inquiry->status->badge() }}">{{ $inquiry->status->label() }}</span>
        </div>
      @endforeach
    </div>
  </div>
  <p class="muted" style="font-size:.78rem;margin-top:6px;">Every request gets a reply within 24 hours. It appears below as a project once the scope is agreed.</p>
</div>
@endif

@if(! $client && $openRequests->isEmpty())
  <x-dash.empty icon="fa-diagram-project" text="No projects yet, tell me what you'd like built and I'll take it from there." />
@else
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-diagram-project"></i> Projects</div>
  <div class="dash-grid cols-2">
    @forelse($projects as $project)
      @php $tasks = $taskProgress[$project->id] ?? ['done' => 0, 'total' => 0]; @endphp
      <div class="tb-card">
        <div class="tb-card-body">
          <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;">
            <div style="font-weight:600;">{{ $project->title }}</div>
            <span class="badge-tb badge-neutral" style="flex-shrink:0;">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
          </div>
          @if($tasks['total'] > 0)
            <div class="resume-bar" style="margin:9px 0 6px;">
              <i style="width:{{ (int) round($tasks['done'] / $tasks['total'] * 100) }}%"></i>
            </div>
            <div class="muted" style="font-size:.78rem;margin-bottom:10px;">
              {{ $tasks['done'] }} of {{ $tasks['total'] }} tasks done{{ $project->due_date ? ' · due '.$project->due_date->format('d M Y') : '' }}
            </div>
          @else
            <div class="muted" style="font-size:.78rem;margin:8px 0 10px;">
              Scope being agreed{{ $project->due_date ? ' · due '.$project->due_date->format('d M Y') : '' }}
            </div>
          @endif
          <a href="{{ route('portal.project', $project) }}" class="btn-tb btn-tb-primary btn-tb-sm">View progress</a>
        </div>
      </div>
    @empty
      <x-dash.empty icon="fa-diagram-project" text="No projects yet, your request becomes a project once the scope is agreed." />
    @endforelse
  </div>
</div>
@endif

@if($recentUpdates->isNotEmpty())
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-clock-rotate-left"></i> Latest progress</div>
  <div class="tb-card">
    <div class="todo-list">
      @foreach($recentUpdates as $update)
        <a href="{{ route('portal.project', $update->project) }}" class="todo-row">
          <span class="todo-icon"><i class="fas fa-circle-info"></i></span>
          <span class="todo-main">
            <span class="todo-title">{{ \Illuminate\Support\Str::limit($update->update_text, 70) }}</span>
            <span class="todo-meta">
              {{ $update->project->title }} · {{ $update->created_at->diffForHumans() }}{{ $update->percent_complete !== null ? ' · '.$update->percent_complete.'% complete' : '' }}
            </span>
          </span>
          <span class="todo-go"><i class="fas fa-chevron-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</div>
@endif

<div class="dash-section">
  <a href="{{ route('portal.invoices') }}" class="btn-tb btn-tb-ghost"><i class="fas fa-file-invoice-dollar"></i> Invoices</a>
  <a href="{{ route('hire') }}" class="btn-tb btn-tb-ghost"><i class="fas fa-plus"></i> Start a project</a>
</div>
