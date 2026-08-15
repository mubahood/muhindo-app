{{--
  One task, identical in every section, so the eye learns the shape once.

  The checkbox is the whole interaction: everything else on the row is context.
  wire:key is on the outer element because Livewire reuses DOM between renders,
  and without it ticking one row can visually tick a different one.
--}}
@php
  $done = $task->isDone();
  $client = $task->project?->client?->name;
@endphp
<div class="tk-row @if($done) is-done @endif" wire:key="task-{{ $task->id }}">
  <button type="button" class="tk-check @if($done) on @endif"
          wire:click="toggle({{ $task->id }})"
          aria-pressed="{{ $done ? 'true' : 'false' }}"
          aria-label="{{ $done ? 'Mark not done' : 'Mark done' }}: {{ $task->title }}">
    <i class="fas fa-check"></i>
  </button>

  <span class="tk-main">
    <span class="tk-title">{{ $task->title }}</span>
    @if($task->description)
      <em class="tk-desc">{{ \Illuminate\Support\Str::limit($task->description, 120) }}</em>
    @endif
  </span>

  <span class="tk-meta">
    @if($task->priority === 'high')
      <span class="tk-flag high">High</span>
    @elseif($task->priority === 'low')
      <span class="tk-flag low">Low</span>
    @endif

    @if($task->project)
      <a class="tk-client" href="{{ route('admin.projects.show', $task->project) }}" wire:navigate>
        {{ $client ?? $task->project->title }}
      </a>
    @else
      <span class="tk-client is-personal">Personal</span>
    @endif

    @if($task->due_date)
      <span class="tk-due @if($task->isOverdue()) is-late @endif">{{ $task->due_date->format('d M') }}</span>
    @endif

    @if(($showPull ?? false) && ! $done)
      <button type="button" class="tk-pull" wire:click="pullToToday({{ $task->id }})"
              title="Move to today">Today</button>
    @endif
  </span>
</div>
