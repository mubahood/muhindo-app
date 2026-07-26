@extends('layouts.admin')
@section('title', $project->title)

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $project->title }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.projects.index') }}">Projects</a> <span>/</span> {{ $project->title }}</div>
  </div>
  <a href="{{ route('admin.projects.edit', $project) }}" class="btn-tb btn-tb-primary"><i class="fas fa-pen"></i> Edit</a>
</div>

<div class="tb-card" style="margin-bottom:20px;">
  <div class="tb-card-body">
    <span class="badge-tb badge-info">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span>
    <span class="badge-tb badge-neutral">{{ ucfirst($project->priority) }} priority</span>
    @if($project->budget)<span class="badge-tb badge-neutral">{{ $project->currency }} {{ number_format((float) $project->budget) }}</span>@endif
    <p style="margin-top:12px;">
      Client: <a href="{{ route('admin.clients.show', $project->client) }}">{{ $project->client->name }}</a>
      @if($project->due_date) · Due {{ $project->due_date->format('d M Y') }} @endif
    </p>
    @if($project->description)<p style="margin-top:8px;">{{ $project->description }}</p>@endif
  </div>
</div>

<div class="dash-grid cols-2">
  {{-- Tasks --}}
  <div class="tb-card">
    <div class="tb-card-header"><span class="tb-card-title"><i class="fas fa-list-check"></i> Tasks</span></div>
    <div class="tb-card-body" style="padding:0;">
      @forelse($project->tasks as $task)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--bd);">
          <span style="{{ $task->isDone() ? 'text-decoration:line-through;color:var(--mt2);' : '' }}">{{ $task->title }}</span>
          <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}">
            @csrf @method('PUT')
            <select name="status" class="tb-select" style="padding:4px 8px;font-size:.75rem;" onchange="this.form.submit()">
              @foreach(['todo','doing','done'] as $s)
                <option value="{{ $s }}" {{ $task->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
              @endforeach
            </select>
          </form>
        </div>
      @empty
        <div class="tb-empty" style="padding:18px;"><p>No tasks yet.</p></div>
      @endforelse
    </div>
    <div class="tb-card-footer">
      <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" style="display:flex;gap:8px;">
        @csrf
        <input class="tb-input" type="text" name="title" placeholder="New task…" required>
        <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">Add</button>
      </form>
    </div>
  </div>

  {{-- Progress updates (client-visible) --}}
  <div class="tb-card">
    <div class="tb-card-header"><span class="tb-card-title"><i class="fas fa-bullhorn"></i> Progress updates <span class="muted" style="font-weight:400;">(client-visible)</span></span></div>
    <div class="tb-card-body" style="padding:0;">
      @forelse($project->updates as $update)
        <div style="padding:12px 18px;border-bottom:1px solid var(--bd);">
          <div>{{ $update->update_text }}</div>
          <div class="muted" style="font-size:.72rem;margin-top:4px;">{{ $update->user->name ?? 'System' }} · {{ $update->created_at->diffForHumans() }} @if($update->percent_complete !== null) · {{ $update->percent_complete }}% complete @endif</div>
        </div>
      @empty
        <div class="tb-empty" style="padding:18px;"><p>No updates posted yet.</p></div>
      @endforelse
    </div>
    <div class="tb-card-footer">
      <form method="POST" action="{{ route('admin.projects.updates.store', $project) }}" style="display:flex;flex-direction:column;gap:8px;">
        @csrf
        <textarea class="tb-textarea" name="update_text" rows="2" placeholder="Post a progress update…" required></textarea>
        <div style="display:flex;gap:8px;align-items:center;">
          <input class="tb-input" type="number" name="percent_complete" min="0" max="100" placeholder="% complete" style="max-width:140px;">
          <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">Post</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="dash-grid cols-2" style="margin-top:20px;">
  {{-- Internal notes --}}
  <div class="tb-card">
    <div class="tb-card-header"><span class="tb-card-title"><i class="fas fa-note-sticky"></i> Internal notes</span></div>
    <div class="tb-card-body" style="padding:0;">
      @forelse($project->notes as $note)
        <div style="padding:12px 18px;border-bottom:1px solid var(--bd);">
          <div>{{ $note->note }}</div>
          <div class="muted" style="font-size:.72rem;margin-top:4px;">{{ $note->user->name ?? 'System' }} · {{ $note->created_at->diffForHumans() }} @if($note->is_client_visible)<span class="badge-tb badge-info">Client-visible</span>@endif</div>
        </div>
      @empty
        <div class="tb-empty" style="padding:18px;"><p>No notes yet.</p></div>
      @endforelse
    </div>
    <div class="tb-card-footer">
      <form method="POST" action="{{ route('admin.projects.notes.store', $project) }}" style="display:flex;flex-direction:column;gap:8px;">
        @csrf
        <textarea class="tb-textarea" name="note" rows="2" placeholder="Add an internal note…" required></textarea>
        <label class="tb-check-group" style="font-size:.8rem;"><input type="checkbox" name="is_client_visible" value="1"> Visible to client</label>
        <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm" style="align-self:flex-start;">Add note</button>
      </form>
    </div>
  </div>

  {{-- Documents --}}
  <div class="tb-card">
    <div class="tb-card-header"><span class="tb-card-title"><i class="fas fa-folder-open"></i> Documents</span></div>
    <div class="tb-card-body" style="padding:0;">
      @forelse($project->documents as $doc)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--bd);">
          <div>
            <a href="{{ route('admin.projects.documents.download', [$project, $doc]) }}">{{ $doc->title }}</a>
            @if($doc->is_confidential)<span class="badge-tb badge-danger" style="margin-left:4px;">Confidential</span>@endif
          </div>
          <form method="POST" action="{{ route('admin.projects.documents.destroy', [$project, $doc]) }}" onsubmit="return confirm('Remove this document?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon btn-tb-sm"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      @empty
        <div class="tb-empty" style="padding:18px;"><p>No documents uploaded.</p></div>
      @endforelse
    </div>
    <div class="tb-card-footer">
      <form method="POST" action="{{ route('admin.projects.documents.store', $project) }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:8px;">
        @csrf
        <input class="tb-input" type="text" name="title" placeholder="Document title" required>
        <input class="tb-input" type="file" name="file" required>
        <label class="tb-check-group" style="font-size:.8rem;"><input type="checkbox" name="is_confidential" value="1"> Confidential (staff only)</label>
        <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm" style="align-self:flex-start;">Upload</button>
      </form>
    </div>
  </div>
</div>

@endsection
