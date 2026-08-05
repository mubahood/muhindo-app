<div>
  <div class="tb-page-header">
    <div>
      <h1>Grading Queue</h1>
      <div class="tb-breadcrumb">Ungraded quiz answers and assignment submissions, oldest first</div>
    </div>
  </div>

  <div class="tb-card">
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead>
          <tr><th>Student</th><th>Course</th><th>Item</th><th>Submitted</th><th>Max points</th><th></th></tr>
        </thead>
        <tbody>
          @forelse($queue as $item)
            <tr>
              <td>{{ $item['student'] }}</td>
              <td class="muted">{{ $item['course'] }}</td>
              <td>{{ $item['title'] }}
                <span class="badge-tb {{ $item['type'] === 'submission' ? 'badge-info' : 'badge-pending' }}" style="margin-left:6px;">
                  {{ $item['type'] === 'submission' ? 'Assignment' : 'Quiz' }}
                </span>
              </td>
              <td class="muted">{{ $item['submitted_at']?->diffForHumans() ?? '-' }}</td>
              <td>{{ rtrim(rtrim(number_format($item['max_points'], 2), '0'), '.') }}</td>
              <td>
                @if($gradingType === $item['type'] && $gradingId === $item['id'])
                  <form wire:submit="submitGrade" style="display:flex;gap:8px;align-items:flex-start;">
                    <input type="number" step="0.01" min="0" max="{{ $item['max_points'] }}" class="tb-input" style="width:90px;" wire:model="points" placeholder="Points" autofocus>
                    <input type="text" class="tb-input" style="width:220px;" wire:model="feedback" placeholder="Feedback (optional)">
                    <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-check"></i></button>
                    <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" wire:click="cancelGrading"><i class="fas fa-xmark"></i></button>
                  </form>
                  @error('points') <div class="field-error">{{ $message }}</div> @enderror
                @else
                  <button type="button" class="btn-tb btn-tb-primary btn-tb-sm" wire:click="openGrading('{{ $item['type'] }}', {{ $item['id'] }})">
                    <i class="fas fa-pen"></i> Grade
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="tb-empty" style="padding:30px;"><i class="fas fa-circle-check"></i><p>Nothing to grade, inbox zero.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
