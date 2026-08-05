<div>
  <div class="tb-page-header">
    <div>
      <h1>Q&amp;A</h1>
      <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> Q&amp;A</div>
    </div>
  </div>

  @forelse($threads as $thread)
    <div class="tb-card" style="margin-bottom:16px;">
      <div class="tb-card-body">
        <div style="display:flex;justify-content:space-between;">
          <div>
            <strong>{{ $thread->user->name }}</strong>
            <span class="muted" style="font-size:.78rem;">
              · {{ $thread->created_at->diffForHumans() }}
              @if($thread->lesson) · {{ $thread->lesson->title }} @endif
            </span>
          </div>
          <span class="badge-tb {{ $thread->isResolved() ? 'badge-active' : 'badge-pending' }}">{{ $thread->isResolved() ? 'Resolved' : 'Open' }}</span>
        </div>
        <p style="margin-top:8px;">{{ $thread->body }}</p>

        @foreach($thread->replies as $reply)
          <div style="margin-left:20px;padding:10px 0;border-top:1px solid var(--bd);">
            <strong>{{ $reply->user->name }}</strong>
            @if($reply->is_instructor_answer) <span class="badge-tb badge-info">Instructor</span> @endif
            <span class="muted" style="font-size:.78rem;">· {{ $reply->created_at->diffForHumans() }}</span>
            <p style="margin-top:4px;">{{ $reply->body }}</p>
          </div>
        @endforeach

        @if($openThreadId === $thread->id)
          <form wire:submit="submitReply" style="margin-top:12px;">
            <textarea class="tb-textarea" wire:model="reply" rows="3" placeholder="Write a reply..."></textarea>
            @error('reply') <div class="field-error">{{ $message }}</div> @enderror
            <div style="margin-top:8px;display:flex;gap:8px;">
              <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-reply"></i> Send</button>
              <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" wire:click="$set('openThreadId', null)">Cancel</button>
            </div>
          </form>
        @else
          <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="button" class="btn-tb btn-tb-primary btn-tb-sm" wire:click="openThread({{ $thread->id }})"><i class="fas fa-reply"></i> Reply</button>
            @unless($thread->isResolved())
              <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" wire:click="resolve({{ $thread->id }})"><i class="fas fa-check"></i> Mark resolved</button>
            @endunless
          </div>
        @endif
      </div>
    </div>
  @empty
    <div class="tb-card"><div class="tb-empty" style="padding:30px;"><p>No questions yet for this course.</p></div></div>
  @endforelse
</div>
