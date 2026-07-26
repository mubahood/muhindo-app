<div>
  <div class="tb-page-header">
    <div>
      <h1>Reviews</h1>
      <div class="tb-breadcrumb">Course review moderation — unpublished first</div>
    </div>
  </div>

  <div class="tb-card">
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead><tr><th>Course</th><th>Student</th><th>Rating</th><th>Review</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($reviews as $review)
            <tr>
              <td>{{ $review->course->title }}</td>
              <td class="muted">{{ $review->enrollment->user->name }}</td>
              <td>{{ $review->rating }} <i class="fas fa-star" style="color:#b8933f;"></i></td>
              <td>{{ \Illuminate\Support\Str::limit($review->body ?? '—', 80) }}</td>
              <td>
                <span class="badge-tb {{ $review->is_published ? 'badge-active' : 'badge-pending' }}">{{ $review->is_published ? 'Published' : 'Pending' }}</span>
              </td>
              <td>
                <div class="tb-table-actions">
                  @if($review->is_published)
                    <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" wire:click="unpublish({{ $review->id }})">Unpublish</button>
                  @else
                    <button type="button" class="btn-tb btn-tb-primary btn-tb-sm" wire:click="publish({{ $review->id }})">Publish</button>
                  @endif
                  <button type="button" class="btn-tb btn-tb-danger btn-tb-icon" wire:click="delete({{ $review->id }})" wire:confirm="Delete this review?"><i class="fas fa-trash"></i></button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="tb-empty" style="padding:30px;"><p>No reviews yet.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
