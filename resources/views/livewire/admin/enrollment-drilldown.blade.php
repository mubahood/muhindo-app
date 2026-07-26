<div>
  <div class="tb-page-header">
    <div>
      <h1>{{ $enrollment->user->name }}</h1>
      <div class="tb-breadcrumb">
        <a href="{{ route('admin.courses.index') }}">Courses</a> <span>/</span>
        <a href="{{ route('admin.courses.students', $enrollment->course) }}">{{ $enrollment->course->title }}</a> <span>/</span>
        {{ $enrollment->user->name }}
      </div>
    </div>
    <div>
      @if($nudgeSent)
        <span class="badge-tb badge-active"><i class="fas fa-check"></i> Nudge sent</span>
      @else
        <button type="button" class="btn-tb btn-tb-primary" wire:click="sendNudge">
          <i class="fas fa-paper-plane"></i> Send nudge
        </button>
      @endif
    </div>
  </div>

  <div class="tb-card" style="margin-bottom:20px;">
    <div class="tb-card-body" style="display:flex;gap:32px;flex-wrap:wrap;">
      <div>
        <div class="muted" style="font-size:.75rem;">Email</div>
        <div>{{ $enrollment->user->email }}</div>
      </div>
      <div>
        <div class="muted" style="font-size:.75rem;">Status</div>
        <span class="badge-tb {{ match($enrollment->status) {
          'active' => 'badge-active', 'completed' => 'badge-info', 'pending' => 'badge-pending', default => 'badge-danger',
        } }}">{{ ucfirst($enrollment->status) }}</span>
      </div>
      <div>
        <div class="muted" style="font-size:.75rem;">Progress</div>
        <div>{{ $enrollment->progress_percent }}%</div>
      </div>
      <div>
        <div class="muted" style="font-size:.75rem;">Watch time</div>
        <div>{{ intdiv($enrollment->total_watch_seconds, 60) }}m</div>
      </div>
      <div>
        <div class="muted" style="font-size:.75rem;">Last active</div>
        <div>{{ $enrollment->last_accessed_at?->diffForHumans() ?? 'Never' }}</div>
      </div>
      <div>
        <div class="muted" style="font-size:.75rem;">Enrolled</div>
        <div>{{ $enrollment->enrolled_at?->format('d M Y') }}</div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
    <div class="tb-card">
      <div class="tb-card-header"><span class="tb-card-title">Lesson progress</span></div>
      <div class="tb-card-body" style="padding:0;">
        @forelse($lessons as $lesson)
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--bd);">
            <span>{{ $lesson->title }}</span>
            @if($completedLessonIds->contains($lesson->id))
              <i class="fas fa-circle-check" style="color:var(--ok);"></i>
            @else
              <i class="fas fa-circle" style="color:var(--mt2);font-size:.75em;"></i>
            @endif
          </div>
        @empty
          <div class="tb-empty" style="padding:20px;"><p>No lessons in this course yet.</p></div>
        @endforelse
      </div>
    </div>

    <div class="tb-card">
      <div class="tb-card-header"><span class="tb-card-title">Instructor notes (private)</span></div>
      <div class="tb-card-body">
        <form wire:submit="addNote" style="display:flex;gap:8px;margin-bottom:14px;">
          <input type="text" class="tb-input" wire:model="newNote" placeholder="Add a private note about this student…">
          <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">Add</button>
        </form>
        @error('newNote') <div class="field-error" style="margin-bottom:10px;">{{ $message }}</div> @enderror

        @forelse($enrollment->notes as $note)
          <div style="padding:8px 0;border-bottom:1px solid var(--bd);">
            <div>{{ $note->note }}</div>
            <div class="muted" style="font-size:.72rem;">{{ $note->user->name ?? 'Staff' }} · {{ $note->created_at->diffForHumans() }}</div>
          </div>
        @empty
          <p class="muted">No notes yet.</p>
        @endforelse
      </div>
    </div>
  </div>

  <div class="tb-card" style="margin-top:20px;">
    <div class="tb-card-header"><span class="tb-card-title">Activity timeline</span></div>
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead><tr><th>Event</th><th>Lesson</th><th>When</th></tr></thead>
        <tbody>
          @forelse($timeline as $event)
            <tr>
              <td>{{ $event->event->label() }}</td>
              <td>{{ $event->lesson->title ?? '—' }}</td>
              <td>{{ $event->created_at->diffForHumans() }}</td>
            </tr>
          @empty
            <tr><td colspan="3"><div class="tb-empty"><p>No activity recorded yet.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div style="margin-top:16px;">{{ $timeline->links() }}</div>
</div>
