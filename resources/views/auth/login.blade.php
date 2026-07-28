@extends('layouts.auth')
@section('title', 'Sign in')

@section('form')
<div class="af-eyebrow">Welcome back</div>
<h2 class="af-title">Sign in</h2>
<p class="af-sub">One account for owner, students and clients — sign in below.</p>

@include('auth.partials.course-context')

@if(session('status'))
<div class="a-alert ok"><i class="fas fa-circle-check"></i><span>{{ session('status') }}</span></div>
@endif

@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('login') }}">
  @csrf
  @if($intendedCourse)
    <input type="hidden" name="intended_course" value="{{ $intendedCourse->slug }}">
    @if(request('coupon_code'))<input type="hidden" name="coupon_code" value="{{ request('coupon_code') }}">@endif
  @endif
  <div class="a-field">
    <label class="a-label" for="email">Email address</label>
    <div class="a-inwrap">
      <i class="fas fa-envelope"></i>
      <input class="a-input" id="email" type="email" name="email" value="{{ old('email') }}"
             placeholder="you@example.com" required autofocus autocomplete="username">
    </div>
  </div>

  <div class="a-field">
    <label class="a-label" for="password">
      Password
      <a href="{{ route('password.request') }}">Forgot password?</a>
    </label>
    <div class="a-inwrap">
      <i class="fas fa-lock"></i>
      <input class="a-input" id="password" type="password" name="password"
             placeholder="••••••••" required autocomplete="current-password" style="padding-right:44px;">
      <button type="button" class="a-eye" data-eye="password"><i class="fas fa-eye"></i></button>
    </div>
  </div>

  {{-- No "remember me" checkbox — staying signed in until sign-out is the default policy. --}}
  <button type="submit" class="a-btn" style="margin-top:6px;"><i class="fas fa-right-to-bracket"></i> Sign in</button>
</form>

<div class="a-alt">New student? <a href="{{ route('register', $intendedCourse ? array_filter(['intended_course' => $intendedCourse->slug, 'coupon_code' => request('coupon_code')]) : []) }}">Create an account</a></div>
<div style="text-align:center;"><a href="{{ route('home') }}" class="a-back"><i class="fas fa-arrow-left"></i> Back to home</a></div>

<div class="a-secure">
  <span><i class="fas fa-shield-halved"></i> Encrypted</span>
  <span class="dot">•</span><span>Activity logged</span>
</div>
@endsection
