@extends('layouts.admin')
@section('title', 'Item Analysis — ' . $quiz->title)

@section('content')

<div class="tb-page-header">
  <div><h1>Item Analysis</h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('admin.courses.show', $quiz->course) }}">{{ $quiz->course->title }}</a> <span>/</span>
      <a href="{{ route('admin.quizzes.edit', $quiz) }}">{{ $quiz->title }}</a> <span>/</span> Item Analysis
    </div>
  </div>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead>
        <tr><th>Question</th><th>Type</th><th>Answered</th><th>Correct</th><th>Correct rate</th></tr>
      </thead>
      <tbody>
        @forelse($items as $item)
          <tr>
            <td>{{ \Illuminate\Support\Str::limit(strip_tags($item['prompt']), 80) }}</td>
            <td class="muted">{{ $item['type'] }}</td>
            <td>{{ $item['total_answered'] }}</td>
            <td>{{ $item['correct_count'] }}</td>
            <td>
              @if($item['correct_rate'] === null)
                <span class="muted">No answers yet</span>
              @else
                <span class="badge-tb {{ $item['correct_rate'] < 50 ? 'badge-danger' : ($item['correct_rate'] < 75 ? 'badge-pending' : 'badge-active') }}">
                  {{ rtrim(rtrim(number_format($item['correct_rate'], 1), '0'), '.') }}%
                </span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5"><div class="tb-empty" style="padding:30px;"><p>This quiz has no questions yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
