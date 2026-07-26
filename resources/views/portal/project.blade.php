@extends('layouts.app')
@section('title', $project->title)

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('portal.index') }}">My Projects</a></div>
<h1>{{ $project->title }}</h1>
<span class="badge-pill">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span>
@if($project->description)<p style="margin-top:14px;">{{ $project->description }}</p>@endif

<div class="grid-2" style="margin-top:28px;">
  <div class="card">
    <div style="font-weight:600;margin-bottom:12px;">Progress updates</div>
    @forelse($project->updates as $update)
      <div style="padding:10px 0;border-bottom:1px solid var(--line);">
        <div>{{ $update->update_text }}</div>
        <div class="muted" style="font-size:.75rem;margin-top:4px;">{{ $update->created_at->format('d M Y') }} @if($update->percent_complete !== null) · {{ $update->percent_complete }}% complete @endif</div>
      </div>
    @empty
      <p class="muted">No updates posted yet.</p>
    @endforelse
  </div>

  <div class="card">
    <div style="font-weight:600;margin-bottom:12px;">Tasks</div>
    @forelse($project->tasks as $task)
      <div style="padding:8px 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;">
        <span style="{{ $task->isDone() ? 'text-decoration:line-through;color:var(--tx3);' : '' }}">{{ $task->title }}</span>
        <span class="muted" style="font-size:.75rem;">{{ ucfirst($task->status) }}</span>
      </div>
    @empty
      <p class="muted">No tasks listed.</p>
    @endforelse
  </div>
</div>

@if($project->notes->count())
<div class="card" style="margin-top:20px;">
  <div style="font-weight:600;margin-bottom:12px;">Notes</div>
  @foreach($project->notes as $note)
    <div style="padding:8px 0;border-bottom:1px solid var(--line);">{{ $note->note }}
      <div class="muted" style="font-size:.72rem;margin-top:2px;">{{ $note->created_at->diffForHumans() }}</div>
    </div>
  @endforeach
</div>
@endif

<div class="card" style="margin-top:20px;">
  <div style="font-weight:600;margin-bottom:12px;">Documents</div>
  @forelse($project->documents as $doc)
    <div style="padding:8px 0;border-bottom:1px solid var(--line);">
      <a href="{{ route('portal.project.document', [$project, $doc]) }}"><i class="fas fa-file"></i> {{ $doc->title }}</a>
    </div>
  @empty
    <p class="muted">No documents shared yet.</p>
  @endforelse
</div>
@endsection
