@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<h1 class="sr-only">Dashboard</h1>

<div class="dash-root">
  @include('admin.dashboard.roles.'.$role)
</div>
@endsection
