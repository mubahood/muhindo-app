@extends('layouts.admin')
@section('title', $project->title)

@section('content')

@php
  $tasks = $project->tasks;
  $done = $tasks->where('status', 'done')->count();
  $percent = $tasks->count() > 0 ? (int) round($done / $tasks->count() * 100) : null;
@endphp

<div class="tb-page-header">
  <div>
    <h1>{{ $project->title }}</h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span>
      <a href="{{ route('portal.index') }}">My Projects</a> <span>/</span> {{ $project->project_number }}
    </div>
  </div>
  <span class="badge-tb badge-neutral">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
</div>

{{-- Where the project stands, before any of the detail. --}}
<div class="tb-card" style="margin-bottom:16px;">
  <div class="tb-card-body">
    @if($project->description)
      <p style="margin-bottom:14px;">{{ $project->description }}</p>
    @endif

    @if($percent !== null)
      <div class="resume-bar" role="img" aria-label="{{ $percent }} percent of tasks complete">
        <i style="width:{{ $percent }}%"></i>
      </div>
      <p class="mine-meta" style="margin-top:6px;" aria-hidden="true">
        {{ $done }} of {{ $tasks->count() }} tasks done · {{ $percent }}%
      </p>
    @else
      <p class="mine-meta">Scope is still being agreed. The task list appears here once it's set.</p>
    @endif

    @if($project->start_date || $project->due_date)
      <p class="mine-meta" style="margin-top:8px;">
        @if($project->start_date)Started {{ $project->start_date->format('d M Y') }}@endif
        @if($project->start_date && $project->due_date) · @endif
        @if($project->due_date)Due {{ $project->due_date->format('d M Y') }}@endif
      </p>
    @endif
  </div>
</div>

<div class="dash-grid cols-2">

  <section class="tb-card" aria-labelledby="updates-h">
    <div class="tb-card-header"><h2 class="tb-card-title" id="updates-h">Progress updates</h2></div>
    <div class="tb-card-body">
      @forelse($project->updates as $update)
        <div style="padding:9px 0;border-bottom:1px solid var(--line);">
          <div>{{ $update->update_text }}</div>
          <div class="mine-meta">
            {{ $update->created_at->format('d M Y') }}@if($update->percent_complete !== null) · {{ $update->percent_complete }}% complete @endif
          </div>
        </div>
      @empty
        <p class="muted">No updates posted yet.</p>
      @endforelse
    </div>
  </section>

  <section class="tb-card" aria-labelledby="tasks-h">
    <div class="tb-card-header">
      <h2 class="tb-card-title" id="tasks-h">Tasks</h2>
      @if($tasks->count() > 0)<span class="badge-tb badge-neutral">{{ $done }}/{{ $tasks->count() }}</span>@endif
    </div>
    <div class="tb-card-body">
      @forelse($tasks as $task)
        <div style="padding:7px 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:10px;">
          <span style="{{ $task->isDone() ? 'text-decoration:line-through;color:var(--mt2);' : '' }}">
            <i class="fas {{ $task->isDone() ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"
               style="font-size:10px;color:{{ $task->isDone() ? 'var(--ok)' : 'var(--line-2)' }};"></i>
            {{ $task->title }}
          </span>
          <span class="mine-meta" style="flex-shrink:0;margin:0;">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
        </div>
      @empty
        <p class="muted">No tasks listed.</p>
      @endforelse
    </div>
  </section>

</div>

@if($project->notes->count())
<section class="tb-card" style="margin-top:16px;" aria-labelledby="notes-h">
  <div class="tb-card-header"><h2 class="tb-card-title" id="notes-h">Notes</h2></div>
  <div class="tb-card-body">
    @foreach($project->notes as $note)
      <div style="padding:8px 0;border-bottom:1px solid var(--line);">
        {{ $note->note }}
        <div class="mine-meta">{{ $note->created_at->diffForHumans() }}</div>
      </div>
    @endforeach
  </div>
</section>
@endif

<section class="tb-card" style="margin-top:16px;" aria-labelledby="docs-h">
  <div class="tb-card-header"><h2 class="tb-card-title" id="docs-h">Documents</h2></div>
  <div class="tb-card-body">
    @forelse($project->documents as $doc)
      <div style="padding:8px 0;border-bottom:1px solid var(--line);">
        {{-- A file download must be a real navigation, not an SPA swap. --}}
        <a href="{{ route('portal.project.document', [$project, $doc]) }}" data-no-navigate
           style="color:var(--br);">
          <i class="fas fa-file" aria-hidden="true"></i> {{ $doc->title }}
          <span class="sr-only">(downloads a file)</span>
        </a>
      </div>
    @empty
      <p class="muted">No documents shared yet.</p>
    @endforelse
  </div>
</section>

@endsection
