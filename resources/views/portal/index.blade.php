@extends('layouts.app')
@section('title', 'My Projects')

@section('content')
<h1>My Projects</h1>

<div class="grid-2">
  @forelse($projects as $project)
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div style="font-weight:600;">{{ $project->title }}</div>
        <span class="badge-pill">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span>
      </div>
      @php $latest = $project->updates->first(); @endphp
      @if($latest)
        <p class="muted" style="font-size:.85rem;margin:10px 0;">{{ \Illuminate\Support\Str::limit($latest->update_text, 100) }}</p>
      @endif
      <a href="{{ route('portal.project', $project) }}" class="btn gold" style="margin-top:10px;">View progress</a>
    </div>
  @empty
    <div class="card" style="text-align:center;">
      <p class="muted">No projects yet — reach out if you're expecting to see one here.</p>
    </div>
  @endforelse
</div>
@endsection
