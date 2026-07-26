@extends('layouts.auth')
@section('title', 'Create your account')

@section('form')
<div class="af-eyebrow">Student sign-up</div>
<h2 class="af-title">Create your account</h2>
<p class="af-sub">Sign up to enrol in courses and track your progress.</p>

@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
@endif

<form method="POST" action="{{ route('register') }}">
  @csrf
  <div class="a-field">
    <label class="a-label" for="name">Full name</label>
    <div class="a-inwrap">
      <i class="fas fa-user"></i>
      <input class="a-input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
    </div>
  </div>

  <div class="a-field">
    <label class="a-label" for="email">Email address</label>
    <div class="a-inwrap">
      <i class="fas fa-envelope"></i>
      <input class="a-input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
    </div>
  </div>

  <div class="a-field">
    <label class="a-label" for="password">Password</label>
    <div class="a-inwrap">
      <i class="fas fa-lock"></i>
      <input class="a-input" id="password" type="password" name="password" required autocomplete="new-password">
    </div>
  </div>

  <div class="a-field">
    <label class="a-label" for="password_confirmation">Confirm password</label>
    <div class="a-inwrap">
      <i class="fas fa-lock"></i>
      <input class="a-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
    </div>
  </div>

  <button type="submit" class="a-btn"><i class="fas fa-user-plus"></i> Create account</button>
</form>

<div class="a-alt">Already have an account? <a href="{{ route('login') }}">Sign in</a></div>
<div style="text-align:center;"><a href="{{ route('home') }}" class="a-back"><i class="fas fa-arrow-left"></i> Back to home</a></div>
@endsection
