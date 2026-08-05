@extends('layouts.learn')
@section('title', 'Announcements | ' . $course->title)
@section('page_title', 'Announcements')

@section('learn_content')
<h1>Announcements</h1>

@if($announcements->isEmpty())
  <div class="card"><p class="muted">No announcements yet.</p></div>
@else
  @foreach($announcements as $announcement)
    <div class="card markdown-body" style="margin-bottom:16px;">
      <div style="font-weight:600;margin-bottom:4px;">{{ $announcement->title }}</div>
      <div class="muted" style="font-size:12px;margin-bottom:14px;">{{ $announcement->published_at->format('M j, Y g:ia') }}</div>
      <div>{!! $rendered[$announcement->id] !!}</div>
    </div>
  @endforeach
@endif
@endsection
