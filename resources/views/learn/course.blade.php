@extends('layouts.app')
@section('title', $course->title)

@section('content')
<h1>{{ $course->title }}</h1>
<div class="card">
  <p class="muted">This course doesn't have any lessons published yet — check back soon.</p>
</div>
@endsection
