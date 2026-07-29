@extends('layouts.admin')
@section('title', 'My Projects')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>My Projects</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> My Projects</div>
  </div>
  <a href="{{ route('start-a-project') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Start a project</a>
</div>

<div class="mine-grid">
  @forelse($projects as $project)
    @php
      $latest = $project->updates->first();
      $total = $project->tasks_count ?? 0;
      $done = $project->done_tasks_count ?? 0;
    @endphp
    <article class="mine-card">
      <div class="mine-card-body">
        <div class="mine-card-top">
          <div style="min-width:0;">
            <h2 class="mine-title">{{ $project->title }}</h2>
            <p class="mine-meta">
              {{ $project->project_number }}@if($project->due_date) · due {{ $project->due_date->format('d M Y') }}@endif
            </p>
          </div>
          <span class="badge-tb badge-neutral" style="flex-shrink:0;">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
        </div>

        @if($total > 0)
          <div class="resume-bar" style="margin:11px 0 5px;" role="img"
               aria-label="{{ $done }} of {{ $total }} tasks done">
            <i style="width:{{ (int) round($done / $total * 100) }}%"></i>
          </div>
          <p class="mine-meta" aria-hidden="true">{{ $done }} of {{ $total }} tasks done</p>
        @else
          <p class="mine-meta" style="margin-top:11px;">Scope being agreed</p>
        @endif

        @if($latest)
          <p class="mine-meta" style="margin-top:10px;">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            {{ Str::limit($latest->update_text, 110) }}
          </p>
        @endif

        <div style="margin-top:12px;">
          <a href="{{ route('portal.project', $project) }}" class="btn-tb btn-tb-primary btn-tb-sm">
            View progress <span class="sr-only">for {{ $project->title }}</span>
          </a>
        </div>
      </div>
    </article>
  @empty
    <div class="tb-card" style="grid-column:1/-1;">
      <div class="tb-empty">
        <p>No projects yet — tell me what you'd like built and I'll take it from there.</p>
        <a href="{{ route('start-a-project') }}" class="btn-tb btn-tb-primary" style="margin-top:12px;">
          <i class="fas fa-plus"></i> Start a project
        </a>
      </div>
    </div>
  @endforelse
</div>

@endsection
