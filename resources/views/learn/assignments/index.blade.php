@extends('layouts.app')
@section('title', 'Assignments — ' . $course->title)

@push('styles')
<style>
  .quiz-table{width:100%;border-collapse:collapse;font-size:13px;}
  .quiz-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);padding:10px 16px;border-bottom:1px solid var(--line);}
  .quiz-table td{padding:14px 16px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .quiz-table tr:last-child td{border-bottom:none;}
  .status-pill{font-size:10.5px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;padding:3px 8px;}
  .status-pill.ok{background:var(--ok-soft);color:var(--ok);}
  .status-pill.warn{background:var(--gold-soft);color:var(--gold-d);}
  .status-pill.bad{background:#fbe9e9;color:#b91c1c;}
  .status-pill.muted{background:var(--surface-2);color:var(--tx3);}
</style>
@endpush

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('learn.index') }}">My Courses</a> / <a href="{{ route('learn.course', $course) }}">{{ $course->title }}</a> / Assignments</div>
<h1 style="font-size:20px;">Assignments</h1>

@if($assignments->isEmpty())
  <div class="card"><p class="muted">This course has no assignments yet.</p></div>
@else
  <div class="card" style="padding:0;">
    <table class="quiz-table">
      <thead><tr><th>Assignment</th><th>Due</th><th>Points</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach($assignments as $row)
          @php $assignment = $row['assignment']; $latest = $row['latest']; @endphp
          <tr>
            <td>{{ $assignment->title }}</td>
            <td class="muted">{{ $assignment->due_at?->toLocal()->format('M j, Y g:ia') ?? 'No due date' }}</td>
            <td>
              @if($latest?->status?->value === 'returned')
                {{ rtrim(rtrim(number_format((float) $latest->points_awarded, 2), '0'), '.') }} / {{ $assignment->points }}
              @else
                {{ $assignment->points }}
              @endif
            </td>
            <td>
              @if(!$latest || $latest->status->value === 'draft')
                <span class="status-pill muted">{{ $latest ? 'Draft saved' : 'Not started' }}</span>
              @elseif($latest->status->value === 'submitted')
                <span class="status-pill warn">{{ $latest->is_late ? 'Submitted late' : 'Submitted' }}</span>
              @elseif($latest->status->value === 'returned')
                <span class="status-pill ok">Returned</span>
              @endif
            </td>
            <td><a href="{{ route('learn.assignment.show', [$course, $assignment]) }}" class="btn">View</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
