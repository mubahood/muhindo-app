<div>
  <div class="tb-page-header">
    <div>
      <h1>{{ $course->title }} — Students</h1>
      <div class="tb-breadcrumb">
        <a href="{{ route('admin.courses.index') }}">Courses</a> <span>/</span>
        <a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> Students
      </div>
    </div>
  </div>

  <div class="tb-filter-bar">
    <input type="text" class="tb-input" wire:model.live.debounce.400ms="search" placeholder="Search by name or email…" style="min-width:220px;">
    <select class="tb-select" wire:model.live="statusFilter">
      <option value="">All statuses</option>
      <option value="active">Active</option>
      <option value="completed">Completed</option>
      <option value="pending">Pending</option>
      <option value="cancelled">Cancelled</option>
    </select>
  </div>

  <div class="tb-card">
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead>
          <tr>
            <th>Student</th>
            <th style="cursor:pointer;" wire:click="sortBy('progress_percent')">
              Progress @include('livewire.partials.sort-caret', ['field' => 'progress_percent'])
            </th>
            <th style="cursor:pointer;" wire:click="sortBy('total_watch_seconds')">
              Watch time @include('livewire.partials.sort-caret', ['field' => 'total_watch_seconds'])
            </th>
            <th>Current lesson</th>
            <th style="cursor:pointer;" wire:click="sortBy('last_accessed_at')">
              Last active @include('livewire.partials.sort-caret', ['field' => 'last_accessed_at'])
            </th>
            <th style="cursor:pointer;" wire:click="sortBy('status')">
              Status @include('livewire.partials.sort-caret', ['field' => 'status'])
            </th>
          </tr>
        </thead>
        <tbody>
          @forelse($enrollments as $enrollment)
            <tr>
              <td>
                <a href="{{ route('admin.enrollments.show', $enrollment) }}" style="font-weight:500;color:var(--tx);">{{ $enrollment->user->name ?? '—' }}</a>
                <div class="muted" style="font-size:.78rem;">{{ $enrollment->user->email ?? '' }}</div>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="flex:1;height:6px;background:var(--surface-2);border-radius:3px;overflow:hidden;min-width:60px;">
                    <div style="height:100%;background:var(--br);width:{{ $enrollment->progress_percent }}%;"></div>
                  </div>
                  <span style="font-size:.8rem;">{{ $enrollment->progress_percent }}%</span>
                </div>
              </td>
              <td>{{ intdiv($enrollment->total_watch_seconds, 60) }}m</td>
              <td>{{ $enrollment->lastLesson->title ?? '—' }}</td>
              <td>
                <span class="{{ $enrollment->at_risk_reason ? 'badge-tb badge-danger' : 'muted' }}" style="{{ $enrollment->at_risk_reason ? '' : 'font-size:.85rem;' }}">
                  {{ $enrollment->last_accessed_at?->diffForHumans() ?? 'Never' }}
                </span>
              </td>
              <td>
                <span class="badge-tb {{ match($enrollment->status) {
                  'active' => 'badge-active',
                  'completed' => 'badge-info',
                  'pending' => 'badge-pending',
                  default => 'badge-danger',
                } }}">{{ ucfirst($enrollment->status) }}</span>
                @if($enrollment->at_risk_reason)
                  <span class="badge-tb badge-danger" style="margin-left:4px;" title="Flagged by the nightly at-risk check">
                    <i class="fas fa-triangle-exclamation"></i> {{ ucfirst($enrollment->at_risk_reason) }}
                  </span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="tb-empty"><p>No students enrolled in this course yet.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div style="margin-top:16px;">{{ $enrollments->links() }}</div>
</div>
