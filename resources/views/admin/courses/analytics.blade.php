@extends('layouts.admin')
@section('title', $course->title.' | Analytics')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $course->title }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.index') }}">Courses</a> <span>/</span>
      <a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> Analytics</div>
  </div>
  <a href="{{ route('admin.courses.students', $course) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-users"></i> Students</a>
</div>

<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($funnel['enrolled'])" label="Enrolled" icon="fa-user-graduate" />
  <x-dash.stat :value="number_format($funnel['started'])" label="Started" icon="fa-play" />
  <x-dash.stat :value="number_format($funnel['completed'])" label="Completed" icon="fa-flag-checkered" tone="ok" />
  <x-dash.stat :value="number_format($funnel['certified'])" label="Certified" icon="fa-certificate" tone="ok" />
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Enrollment funnel" icon="fa-filter">
    <x-dash.bars :data="$funnel" :labels="[
      'enrolled' => 'Enrolled',
      'started' => 'Started',
      'reached_25' => 'Reached 25%',
      'reached_50' => 'Reached 50%',
      'reached_75' => 'Reached 75%',
      'completed' => 'Completed',
      'certified' => 'Certified',
    ]" />
  </x-dash.section>

  <x-dash.section title="Watch-time distribution" icon="fa-clock">
    <x-dash.bars :data="$watchTime" />
  </x-dash.section>
</div>

<div class="dash-grid">
  <x-dash.section title="Per-lesson drop-off" icon="fa-chart-column">
    @if(empty($dropOff))
      <x-dash.empty text="No published lessons yet." />
    @else
      <div class="tb-card-body"><div class="dash-bars">
        @foreach($dropOff as $row)
          <div class="dash-bar-row">
            <span class="bl" title="{{ $row['title'] }}">{{ $row['title'] }}</span>
            <span class="dash-bar-track"><span class="dash-bar-fill" style="width:{{ $row['completion_rate'] }}%"></span></span>
            <span class="bv">{{ $row['completion_rate'] }}% ({{ $row['completed_count'] }})</span>
          </div>
        @endforeach
      </div></div>
      <p class="muted" style="padding:0 18px 16px;font-size:.8rem;">Share of enrolled students who completed each lesson, in curriculum order, where the line drops sharply is where students quit.</p>
    @endif
  </x-dash.section>
</div>

<div class="dash-grid">
  <x-dash.section title="Quiz performance" icon="fa-list-check">
    @if(empty($quizzes))
      <x-dash.empty text="No quizzes on this course yet." />
    @else
      <div class="tb-table-wrap"><table class="tb-table">
        <thead><tr><th>Quiz</th><th>Graded attempts</th><th>Average score</th><th></th></tr></thead>
        <tbody>
          @foreach($quizzes as $quiz)
            <tr>
              <td>{{ $quiz['title'] }}</td>
              <td>{{ $quiz['graded_attempts'] }}</td>
              <td>{{ $quiz['average_score_percent'] !== null ? $quiz['average_score_percent'].'%' : '-' }}</td>
              <td><a href="{{ route('admin.quizzes.analysis', $quiz['quiz_id']) }}" class="btn-tb btn-tb-ghost btn-tb-sm">Item analysis <i class="fas fa-arrow-right"></i></a></td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
    @endif
  </x-dash.section>
</div>

@endsection
