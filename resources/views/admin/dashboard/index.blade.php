@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<h1 class="sr-only">Dashboard</h1>

<div class="dash-root">
  {{-- Composed by capability: a student-and-client account sees both sections. --}}
  @foreach($sections as $section)
    @include('admin.dashboard.roles.'.$section)
  @endforeach
</div>
@endsection
