@extends('layouts.learn')
@section('title', 'Grades | ' . $course->title)
@section('page_title', 'Grades')

@push('styles')
<style>
  .quiz-table{width:100%;border-collapse:collapse;font-size:13px;}
  .quiz-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);padding:10px 16px;border-bottom:1px solid var(--line);}
  .quiz-table td{padding:14px 16px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .quiz-table tr:last-child td{border-bottom:none;}
  .grade-summary{display:flex;align-items:baseline;gap:12px;margin-bottom:20px;}
  .grade-summary .num{font-size:32px;font-weight:300;}
</style>
@endpush

@section('learn_content')
<h1>Grades</h1>

<div class="card" style="margin-bottom:20px;">
  <div class="grade-summary">
    <span class="num">{{ $courseGrade !== null ? rtrim(rtrim(number_format($courseGrade, 1), '0'), '.').'%' : '-' }}</span>
    <span class="muted">current course grade{{ $courseGrade === null ? ' (nothing graded yet)' : '' }}</span>
  </div>
</div>

@if(empty($items))
  <div class="card"><p class="muted">No graded items in this course yet.</p></div>
@else
  <div class="card" style="padding:0;">
    <table class="quiz-table">
      <thead><tr><th>Item</th><th>Type</th><th>Grade</th></tr></thead>
      <tbody>
        @foreach($items as $item)
          <tr>
            <td>{{ $item['title'] }}</td>
            <td class="muted">{{ ucfirst($item['type']) }}</td>
            <td>
              @if($item['percent'] !== null)
                {{ rtrim(rtrim(number_format($item['percent'], 1), '0'), '.') }}%
              @else
                <span class="muted">Not graded yet</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
