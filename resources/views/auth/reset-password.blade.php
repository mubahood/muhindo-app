@extends('layouts.auth')
@section('title', 'Set new password')

@section('form')
<div class="af-eyebrow">Account recovery</div>
<h2 class="af-title">Set a new password</h2>
<p class="af-sub">Choose a strong password you'll remember. You'll be signed in straight after.</p>

@if($errors->any())
<div class="a-alert err"><i class="fas fa-circle-exclamation"></i>
  <div>{{ $errors->first() }}</div>
</div>
@endif

<form method="POST" action="{{ route('password.store') }}">
  @csrf
  <input type="hidden" name="token" value="{{ $request->route('token') }}">

  <div class="a-field">
    <label class="a-label" for="email">Email address</label>
    <div class="a-inwrap">
      <i class="fas fa-envelope"></i>
      <input class="a-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" placeholder="you@example.com" required autofocus autocomplete="username">
    </div>
  </div>

  <div class="a-field">
    <label class="a-label" for="password">New password</label>
    <div class="a-inwrap">
      <i class="fas fa-lock"></i>
      <input class="a-input" id="password" type="password" name="password" placeholder="••••••••" required autocomplete="new-password" style="padding-right:44px;">
      <button type="button" class="a-eye" data-eye="password"><i class="fas fa-eye"></i></button>
    </div>
  </div>

  <div class="a-field">
    <label class="a-label" for="password_confirmation">Confirm new password</label>
    <div class="a-inwrap">
      <i class="fas fa-lock"></i>
      <input class="a-input" id="password_confirmation" type="password" name="password_confirmation" placeholder="••••••••" required autocomplete="new-password">
    </div>
  </div>

  <button type="submit" class="a-btn"><i class="fas fa-key"></i> Reset password</button>
</form>

<div style="text-align:center;"><a href="{{ url('/admin/login') }}" class="a-back"><i class="fas fa-arrow-left"></i> Back to sign in</a></div>
@endsection
