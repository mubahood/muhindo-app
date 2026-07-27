@extends('layouts.marketing')
@section('title', 'Page not found — Muhindo Mubaraka')
@section('desc', 'That page wandered off.')

@section('content')

<section class="hero">
  <div class="wrap">
    <div class="eyebrow">404</div>
    <h1>That page wandered off</h1>
    <p class="lead">The link you followed might be old, or the address was mistyped. Here's where you probably meant to go:</p>
    <div class="ctas">
      <a href="{{ route('courses.index') }}" wire:navigate class="btn gold lg">Browse courses</a>
      <a href="{{ route('home') }}" wire:navigate class="btn ghost lg">Back to home</a>
    </div>
  </div>
</section>

@endsection
